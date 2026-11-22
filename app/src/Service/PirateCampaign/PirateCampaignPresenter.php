<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{PirateAttackCampaign, PirateReconnaissance, PlayerUniverse};
use App\Service\Catalog\GameCatalog;

/** Prépare une seule fiche privée, sans charger d'entité ni choisir une mission accessible. */
final readonly class PirateCampaignPresenter
{
    private const RESOURCES = [
        'metal' => 'Métal',
        'nano' => 'Nano',
        'antimatter' => 'Antimatière',
        'hydrogen' => 'Hydrogène',
    ];

    private const DATE_LABELS = [
        'scout_departure' => 'Départ de la sonde',
        'scout_due' => 'Arrivée prévue de la sonde',
        'scout_reported' => 'Rapport de reconnaissance reçu',
        'attack_departure' => 'Départ de l’escorte',
        'attack_arrival_due' => 'Arrivée prévue de l’escorte',
        'attack_arrival_observed' => 'Arrivée de l’escorte confirmée',
        'combat_due' => 'Échéance prévue du combat',
        'combat_reported' => 'Rapport de combat reçu',
        'return_due' => 'Retour prévu des survivants',
        'return_settled' => 'Retour des survivants confirmé',
        'recovery_due' => 'Arrivée prévue du repli',
        'recovery_observed' => 'Arrivée du repli constatée',
    ];

    public function __construct(
        private PirateCampaignCatalogue $catalogue,
        private PirateCampaignProgress $progress,
        private GameCatalog $catalog,
    ) {
    }

    /** Le choix de mission et le site proviennent du futur parcours serveur autorisé, pas d'une requête. */
    public function forSignal(PlayerUniverse $viewer, PirateDistressSite $site, string $key, \DateTimeImmutable $at): ?array
    {
        if (!$viewer->universe->isAccessible($at)) {
            return null;
        }

        $narrative = $this->catalogue->narrativeFor($key, $site, $viewer, PirateCampaignMoment::Signal);
        if ($narrative === null) {
            return null;
        }

        return $this->view(
            $key,
            'Signal de détresse',
            $narrative,
        );
    }

    /** L'appelant doit avoir chargé la reconnaissance courante du propriétaire, sous le bon univers. */
    public function forProgress(
        PlayerUniverse $viewer,
        PirateReconnaissance $reconnaissance,
        ?PirateAttackCampaign $attack,
        \DateTimeImmutable $at,
    ): ?array {
        $progress = $this->progress->forViewer($viewer, $reconnaissance, $attack, $at);
        if ($progress === null) {
            return null;
        }

        $status = match ($progress['phase']) {
            PirateCampaignMoment::ReconnaissanceEnRoute->name => 'Reconnaissance en cours',
            PirateCampaignMoment::RenseignementRecu->name => 'Renseignement reçu',
            PirateCampaignMoment::AttaqueEngagee->name => 'Escorte engagée',
            PirateCampaignMoment::Defaite->name => 'Défaite',
            PirateCampaignMoment::IssueIndecise->name => 'Combat sans vainqueur',
            PirateCampaignMoment::Victoire->name => 'Victoire',
        };
        $return = match ($progress['returning']) {
            'scheduled' => $progress['return_narrative']['message'],
            'blocked' => 'Le retour des survivants n’a pas été confirmé.',
            'returned' => 'Le retour des survivants a été confirmé.',
            'recovery_travel' => 'Les survivants sont en repli vers votre colonie.',
            'recovery_delivered' => 'Les survivants et leur réserve restante ont rejoint votre hangar.',
            'recovery_lost' => 'La colonie de repli a été perdue. Ni les survivants ni leur réserve n’ont été transférés.',
            'recovery_capacity' => 'Le hangar ne peut pas accueillir le lot entier. Les survivants et leur réserve restent en attente.',
            default => null,
        };
        if ($progress['returning'] === 'scheduled' && isset($progress['dates']['return_due'])
            && $at >= new \DateTimeImmutable($progress['dates']['return_due'])) {
            $return = 'L’heure de retour prévue est passée. Le retour n’a pas encore été confirmé.';
        }
        if ($progress['returning'] === 'recovery_travel' && $at >= new \DateTimeImmutable($progress['dates']['recovery_due'])) {
            $return = 'L’arrivée du repli reste à confirmer.';
        }
        $formation = $this->shipRows($viewer, $progress['composition']);
        $losses = $this->shipRows($viewer, $progress['own_losses']);
        $quote = $reconnaissance->convoyQuoteFor($viewer, $at);
        // Le libellé de statut n'est pas une preuve de versement ; le devis est toujours indicatif.
        $narrative = $progress['narrative'];
        if ($progress['phase'] === PirateCampaignMoment::RenseignementRecu->name) {
            $narrative['guidance'] = null; // Affiché avec la formation, pas une seconde fois sous le message.
        } elseif ($progress['phase'] === PirateCampaignMoment::ReconnaissanceEnRoute->name
            && $at >= new \DateTimeImmutable($progress['dates']['scout_due'])) {
            $narrative['message'] = 'L’heure d’arrivée prévue est passée, mais le rapport n’a pas encore été remis.';
            $narrative['guidance'] = null;
        } elseif ($progress['phase'] === PirateCampaignMoment::AttaqueEngagee->name
            && isset($progress['dates']['attack_arrival_due'])
            && $at < new \DateTimeImmutable($progress['dates']['attack_arrival_due'])) {
            $narrative['message'] = 'Votre escorte est en route vers le poste civil.';
        } elseif ($progress['phase'] === PirateCampaignMoment::AttaqueEngagee->name
            && isset($progress['dates']['attack_arrival_observed'])) {
            $status = 'Combat en cours';
            $narrative['message'] = 'L’escorte est arrivée. Le relais attend l’issue du combat.';
        }

        return $this->view(
            $progress['key'],
            $status,
            $narrative,
            $this->dateRows($progress['dates'], $progress['phase'], $progress['returning']),
            $formation,
            $losses,
            $return,
            $progress['tactical_guidance'],
            $quote === null ? null : $this->rewardRows($quote),
        ) + ['may_offer_recovery' => $progress['may_offer_recovery']];
    }

    /**
     * Seules ces lignes lisibles vont au gabarit, jamais les clés de scénario, le checkpoint ou le site.
     * @return array{chapters: list<array>, mission_number: int, status: string, narrative: array, dates: list<array>,
     *   formation: ?array, losses: ?array, return: ?string, tactical_guidance: ?string, quote: ?array}
     */
    private function view(
        string $key,
        string $status,
        array $narrative,
        array $dates = [],
        ?array $formation = null,
        ?array $losses = null,
        ?string $return = null,
        ?string $tacticalGuidance = null,
        ?array $quote = null,
    ): array {
        $chapters = [];
        $offset = 0;
        $missionNumber = null;
        foreach ($this->catalogue->chapters() as $chapter) {
            $index = array_search($key, $chapter['keys'], true);
            if ($index !== false) {
                $missionNumber = $offset + $index + 1;
            }
            $chapters[] = ['title' => $chapter['title'], 'count' => count($chapter['keys']), 'current' => $index !== false];
            $offset += count($chapter['keys']);
        }
        if ($missionNumber === null) {
            throw new \LogicException('Impossible de présenter une mission absente du sommaire.');
        }

        return [
            'chapters' => $chapters,
            'mission_number' => $missionNumber,
            'status' => $status,
            'narrative' => $narrative,
            'dates' => $dates,
            'formation' => $formation,
            'losses' => $losses,
            'return' => $return,
            'tactical_guidance' => $tacticalGuidance,
            'quote' => $quote,
        ];
    }

    private function dateRows(array $dates, string $phase, ?string $returning): array
    {
        $rows = [];
        foreach ($dates as $key => $at) {
            $countdown = match ($key) {
                'scout_due' => $phase === PirateCampaignMoment::ReconnaissanceEnRoute->name,
                'attack_arrival_due' => $phase === PirateCampaignMoment::AttaqueEngagee->name && !isset($dates['attack_arrival_observed']),
                'return_due' => $returning === 'scheduled',
                'recovery_due' => $returning === 'recovery_travel',
                default => false, // Ne pas déduire une issue, un crédit ou une clôture d'un chronomètre.
            };
            $rows[] = ['label' => self::DATE_LABELS[$key], 'at' => $at, 'countdown' => $countdown];
        }

        return $rows;
    }

    /** Les libellés du catalogue sont décoratifs ; les quantités restent celles du rapport. */
    private function shipRows(PlayerUniverse $viewer, ?array $counts): ?array
    {
        if ($counts === null) {
            return null;
        }

        $rows = [];
        foreach ($counts as $family => $tiers) {
            foreach ($tiers as $tier => $quantity) {
                $definition = $this->catalog->getTierForUniverse($viewer->universe, 'ships', $family, $tier);
                $rows[] = ['label' => $definition['label'], 'tier' => $definition['_tier_label'], 'quantity' => $quantity];
            }
        }

        return $rows;
    }

    private function rewardRows(PirateConvoyQuote $quote): array
    {
        $rows = [];
        foreach ($quote->exactParts() as $key => $part) {
            // Ne pas convertir en flottant ni choisir d'arrondi : même une grande somme garde son demi.
            $formatted = preg_replace('/\B(?=(\d{3})+(?!\d))/', ' ', (string) $part['whole']);
            $rows[] = ['label' => self::RESOURCES[$key], 'amount' => $formatted.($part['extra_half'] ? ',5' : '')];
        }

        return $rows;
    }
}
