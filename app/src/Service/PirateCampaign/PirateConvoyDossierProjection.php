<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{PirateConvoyDelivery, PlayerUniverse};

/** Modèle de lecture des droits déjà acquis, sans changer l'étape courante ni la sélection. */
final class PirateConvoyDossierProjection
{
    /**
     * @param list<string> $canonicalKeys Ordre du catalogue serveur, jamais fourni par le joueur.
     * @return list<string>
     */
    public function visibleKeys(array $dossier, array $canonicalKeys): array
    {
        return array_keys($this->visibleNumbers($dossier, $canonicalKeys));
    }

    /**
     * @param list<string> $canonicalKeys
     * @param list<array{right:PirateConvoyDelivery,missionKey:string,victoryAt:\DateTimeImmutable}> $ownedRows
     * @return array{selected_reward:?array,oldest_pending:?array{mission:int,state:string}}
     */
    public function forDossier(PlayerUniverse $viewer, array $dossier, array $canonicalKeys, array $ownedRows, \DateTimeImmutable $at): array
    {
        $visible = $this->visibleNumbers($dossier, $canonicalKeys);
        $seen = $views = [];
        $selected = $dossier['selected'] ?? null;

        foreach ($ownedRows as $row) {
            $mission = $visible[$row['missionKey']] ?? null;
            $right = $row['right'];
            if ($mission === null || $row['victoryAt'] > $at || !$this->isOwned($viewer, $right, $at)) {
                continue;
            }
            if (isset($seen[$mission])) {
                // Un droit unique par mission est attendu. Ne pas choisir la version qui nous arrange.
                unset($views[$mission]);
                continue;
            }
            $seen[$mission] = true;
            $view = $this->one($right, $at);
            if ($view !== null) {
                $views[$mission] = ['victoryAt' => $row['victoryAt'], 'view' => $view];
            }
        }

        $oldest = null;
        $oldestAt = null;
        foreach ($views as $mission => $candidate) {
            if ($candidate['view']['state'] === 'credited') {
                continue;
            }
            $victoryAt = $candidate['victoryAt'];
            if ($oldestAt === null || $victoryAt < $oldestAt || ($victoryAt == $oldestAt && $mission < $oldest['mission'])) {
                $oldestAt = $victoryAt;
                $oldest = ['mission' => $mission, 'state' => $candidate['view']['state']];
            }
        }

        return [
            'selected_reward' => is_int($selected) ? ($views[$selected]['view'] ?? null) : null,
            'oldest_pending' => $oldest,
        ];
    }

    private function visibleNumbers(array $dossier, array $canonicalKeys): array
    {
        $numbers = [];
        foreach ($dossier['history'] ?? [] as $entry) {
            $number = $entry['number'] ?? null;
            // Un droit doit provenir d'une mission gagnée. Le renseignement ou la mission future n'en possède jamais.
            if (is_int($number) && $number > 0 && isset($canonicalKeys[$number - 1]) && ($entry['won'] ?? null) === true) {
                $numbers[$canonicalKeys[$number - 1]] = $number;
            }
        }

        return $numbers;
    }

    private function isOwned(PlayerUniverse $viewer, PirateConvoyDelivery $right, \DateTimeImmutable $at): bool
    {
        return ($right->id ?? 0) > 0 && $viewer->isPlayerControlled() && $viewer->id === $right->membershipId
            && $viewer->player?->id === $right->ownerId && $viewer->player->currentPlayerUniverse?->id === $viewer->id
            && $viewer->player->lockedAt === null && $viewer->player->emailVerifiedAt !== null
            && $viewer->player->emailVerifiedAt <= $at && $viewer->universe->id === $right->universeId
            && $viewer->universe->isAccessible($at);
    }

    private function one(PirateConvoyDelivery $right, \DateTimeImmutable $at): ?array
    {
        try {
            // Cette réhydratation recoupe le reçu durable de crédit avec le checkpoint et les temps.
            $timeline = $right->timeline();
        } catch (\DomainException) {
            return null;
        }

        if (new \DateTimeImmutable($timeline->checkpoint()['transition_at']) > $at) {
            return null;
        }

        $dates = $timeline->chronology();
        $state = match ($timeline->state()) {
            'eligible' => 'unassigned',
            'traveling' => 'protected_travel',
            'arrived' => 'arrival_confirmed',
            'waiting_capacity' => 'full_pending',
            'destination_lost' => 'destination_lost',
            'settled' => 'credited',
        };
        $rewards = [];
        foreach ($timeline->quote->exactParts() as $resource => $part) {
            // Ne pas convertir en double : garder même les demi-unités à la précision du reçu.
            $rewards[$resource] = (string) $part['whole'].($part['extra_half'] ? ',5' : '');
        }
        $due = $state === 'protected_travel' ? $dates['due_at'] : null;

        return [
            'state' => $state,
            'amounts' => $rewards,
            'expected_arrival_at' => $due?->format(DATE_ATOM),
            'awaiting_arrival_confirmation' => $due !== null && $at >= $due,
            'credited_at' => $state === 'credited' ? $dates['settled_at']?->format(DATE_ATOM) : null,
        ];
    }
}
