<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Service\Catalog\GameCatalog;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** Instantané canonique d'options, assemblé et gardé côté serveur. */
#[Exclude]
final readonly class PiratePilotCommandOptions
{
    public const MAX_OPTIONS = 256;
    public const MAX_QUANTITY = 2147483647;

    /**
     * @param list<array{key:string,available:int}> $ships
     * @param list<int> $colonies
     */
    private function __construct(
        public PiratePilotCommandAction $action,
        private array $ships,
        private array $colonies,
        public int $fleetLimit,
    ) {
    }

    public static function noChoices(PiratePilotCommandAction $action): self
    {
        if ($action === PiratePilotCommandAction::Attack || $action->requiresDestination()) {
            throw new \InvalidArgumentException('Cette commande exige des options serveur explicites.');
        }

        return new self($action, [], [], 0);
    }

    /** @param array<string,int> $availability Quantités de coques autorisées, par identifiant serveur famille:rang. */
    public static function escort(array $availability, int $fleetLimit): self
    {
        if (count($availability) > self::MAX_OPTIONS || $fleetLimit < 1 || $fleetLimit > self::MAX_QUANTITY) {
            throw new \InvalidArgumentException('Limites d’escorte hors bornes.');
        }

        $ships = [];
        ksort($availability, SORT_STRING);
        foreach ($availability as $key => $available) {
            if (!is_string($key) || preg_match('/^([a-z][a-z0-9_]{0,63}):([a-z_]{1,16})$/D', $key, $parts) !== 1
                || !in_array($parts[2], GameCatalog::TIERS, true)
                || !is_int($available) || $available < 1 || $available > self::MAX_QUANTITY) {
                throw new \InvalidArgumentException('Option d’escorte serveur invalide.');
            }
            // Copier les scalaires pour qu’une référence du tableau de préparation ne change jamais l’intention.
            $ships[] = ['key' => (string) $key, 'available' => (int) $available];
        }

        return new self(PiratePilotCommandAction::Attack, $ships, [], $fleetLimit);
    }

    /** @param list<int> $ownedColonyIds Colonies vues et autorisées par le serveur ; jamais le POST. */
    public static function destinations(PiratePilotCommandAction $action, array $ownedColonyIds): self
    {
        if (!$action->requiresDestination() || !array_is_list($ownedColonyIds) || count($ownedColonyIds) > self::MAX_OPTIONS) {
            throw new \InvalidArgumentException('Liste de destinations serveur invalide.');
        }

        $colonies = [];
        foreach ($ownedColonyIds as $id) {
            if (!is_int($id) || $id < 1 || in_array($id, $colonies, true)) {
                throw new \InvalidArgumentException('Destination serveur non unique ou non enregistrée.');
            }
            $colonies[] = (int) $id;
        }
        sort($colonies, SORT_NUMERIC);

        return new self($action, [], $colonies, 0);
    }

    public function maxEscortQuantity(int $choice): ?int
    {
        return $this->ships[$choice - 1]['available'] ?? null;
    }

    /** Identifiant de domaine à résoudre seulement après revalidation de l'intention et des droits. */
    public function escortKey(int $choice): ?string
    {
        return $this->ships[$choice - 1]['key'] ?? null;
    }

    /** Idem : l'ordinal soumis n'est jamais lui-même une planète. */
    public function colonyId(int $choice): ?int
    {
        return $this->colonies[$choice - 1] ?? null;
    }

    /**
     * Aucune identité ou empreinte n'est renvoyée dans le formulaire ; le numéro visible est
     * juste la position stable dans ce modèle trié. Une insertion/suppression change la version CSRF.
     */
    public function fingerprint(): string
    {
        return hash('sha256', json_encode([$this->action->value, $this->ships, $this->fleetLimit, $this->colonies], JSON_THROW_ON_ERROR));
    }
}
