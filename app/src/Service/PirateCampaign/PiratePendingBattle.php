<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Service\Catalog\GameCatalog;
use App\Service\Combat\CombatSimulator;

/** Résultat interne scellé après arrivée. À ne jamais passer en vue ou dans un message Messenger. */
final class PiratePendingBattle
{
    private bool $consumed = false;

    private function __construct(
        private readonly int $attempt,
        private readonly \DateTimeImmutable $arrivedAt,
        private readonly \DateTimeImmutable $nominalArrivalAt,
        private readonly \DateTimeImmutable $dueAt,
        private readonly array $payload,
    ) {
    }

    /**
     * L'arrivée est une preuve donnée par le futur worker propriétaire sous verrou, jamais la seule ETA.
     * Les forces et la graine sont celles déjà figées au départ.
     * @param list<array<string,mixed>> $units
     */
    public static function prepareAfterArrival(
        int $attempt,
        ?\DateTimeImmutable $arrivedAt,
        \DateTimeImmutable $at,
        PirateBattleScenario $scenario,
        array $units,
        int $seed,
        CombatSimulator $simulator,
        ?\DateTimeImmutable $nominalArrivalAt = null,
    ): ?self {
        if ($arrivedAt === null || $at < $arrivedAt) {
            return null;
        }

        // Les colonnes et le calcul natif de phase travaillent à la seconde.
        $arrivedAt = $arrivedAt->setTimestamp($arrivedAt->getTimestamp());
        $nominalArrivalAt ??= $arrivedAt;
        if ($nominalArrivalAt > $arrivedAt) {
            throw new \DomainException('Une arrivée ne peut pas être confirmée avant la fin du trajet.');
        }
        $result = $scenario->resolve($simulator, $units, $seed);
        $survivors = [];
        foreach ($result['survivors'] as $unit) {
            if ($unit['camp'] !== 'escort') {
                continue;
            }

            // Pas de liste d'unités, d'HP ou de logs par round dans le checkpoint : seuls les totaux à appliquer.
            $slug = $unit['slug'];
            $tier = $unit['tier'];
            $survivors[$slug][$tier] = ($survivors[$slug][$tier] ?? 0) + 1;
        }

        return self::record($attempt, $arrivedAt, $nominalArrivalAt, [
            'outcome' => $result['outcome'],
            'rounds_played' => $result['rounds_played'],
            'losses' => $result['losses'],
            'survivors' => $survivors,
        ]);
    }

    private static function record(int $attempt, \DateTimeImmutable $arrivedAt, \DateTimeImmutable $nominalArrivalAt, array $payload): self
    {
        if ($attempt < 1 || $nominalArrivalAt > $arrivedAt) {
            throw new \DomainException('Le résultat préparé exige une tentative et une arrivée cohérentes.');
        }
        $schedule = (new PirateCombatSchedule())->afterArrival($nominalArrivalAt, $arrivedAt, $payload);

        self::assertValidCounts($payload['losses'], $payload['survivors']);

        return new self($attempt, $arrivedAt, $nominalArrivalAt, $schedule['resolution_due'], $payload);
    }

    /** Rejeter un checkpoint corrompu plutôt que d'appliquer une restitution partielle. */
    private static function assertValidCounts(array $losses, array $survivors): void
    {
        $total = 0;
        $validCount = static fn (mixed $count): bool => is_int($count) && $count >= 1 && $count <= CombatSimulator::MAX_UNITS;
        foreach ($losses as $key => $quantity) {
            if (!is_string($key) || preg_match('/^(?:escort|pirates):[a-z][a-z0-9_]{0,63}(?::(?:basic|intermediate|superior))?$/D', $key) !== 1
                || !$validCount($quantity)) {
                throw new \DomainException('Pertes privées invalides.');
            }
            $total += $quantity;
        }
        foreach ($survivors as $slug => $tiers) {
            if (!is_string($slug) || preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $slug) !== 1 || !is_array($tiers) || $tiers === []) {
                throw new \DomainException('Survivants privés invalides.');
            }
            foreach ($tiers as $tier => $quantity) {
                if (!in_array($tier, GameCatalog::TIERS, true) || !$validCount($quantity)) {
                    throw new \DomainException('Quantité ou rang des survivants privés invalide.');
                }
                $total += $quantity;
            }
        }
        if ($total > CombatSimulator::MAX_UNITS) {
            throw new \DomainException('Le résultat préparé dépasse la limite du moteur.');
        }
    }

    /** Interne au worker seulement : afficher cette date donnerait un indice sur la durée du combat. */
    public function workerDueAt(): \DateTimeImmutable
    {
        return $this->dueAt;
    }

    /**
     * À prendre sous verrou du propriétaire après contrôle du jeton, puis à consommer dans la même
     * transaction que rapport, pertes et ordre de retour. Si la transaction échoue, relire le
     * checkpoint intact en base ; ne pas conserver cette instance consommée.
     * @return array{outcome: string, rounds_played: int, losses: array<string,int>, survivors: array<string,array<string,int>>}|null
     */
    public function takeWhenDue(int $attempt, \DateTimeImmutable $at): ?array
    {
        if ($this->consumed || $attempt !== $this->attempt || $at < $this->dueAt || $at < $this->arrivedAt) {
            return null;
        }

        $this->consumed = true;

        return $this->payload;
    }

    /**
     * Données privées uniquement : jamais réutiliser comme modèle de vue. Le futur stockage devra
     * conserver le marqueur dans la transaction d'application pour empêcher un double prélèvement.
     * @return array{version:1,attempt:int,arrived_at:string,nominal_arrival_at:string,consumed:bool,payload:array}
     */
    public function checkpointForPersistence(): array
    {
        return [
            'version' => 1,
            'attempt' => $this->attempt,
            'arrived_at' => $this->arrivedAt->format(DATE_ATOM),
            'nominal_arrival_at' => $this->nominalArrivalAt->format(DATE_ATOM),
            'consumed' => $this->consumed,
            'payload' => $this->payload,
        ];
    }

    /** Donnée serveur, lue seulement dans la tentative propriétaire verrouillée. */
    public static function fromPrivateCheckpoint(array $data): self
    {
        if (($data['version'] ?? null) !== 1 || !is_int($data['attempt'] ?? null)
            || !is_string($data['arrived_at'] ?? null) || !is_string($data['nominal_arrival_at'] ?? null) || !is_bool($data['consumed'] ?? null)
            || !is_array($data['payload']['losses'] ?? null) || !is_array($data['payload']['survivors'] ?? null)) {
            throw new \DomainException('Le checkpoint privé du combat n’est pas reconnu.');
        }
        $arrivedAt = self::parseTime($data['arrived_at']);
        $nominalArrivalAt = self::parseTime($data['nominal_arrival_at']);
        $payload = array_intersect_key($data['payload'], array_flip(['outcome', 'rounds_played', 'losses', 'survivors']));
        $battle = self::record($data['attempt'], $arrivedAt, $nominalArrivalAt, $payload);
        $battle->consumed = $data['consumed'];

        return $battle;
    }

    private static function parseTime(string $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat(DATE_ATOM, $value);
        if ($date === false || $date->format(DATE_ATOM) !== $value) {
            throw new \DomainException('La date interne du combat ne peut pas être relue.');
        }

        return $date;
    }
}
