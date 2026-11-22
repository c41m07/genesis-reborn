<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** Uniquement des choix d'affichage validés, aucune identité ni donnée de simulation. */
#[Exclude]
final readonly class PiratePilotCommandInput
{
    /** @param array<int,int> $escortQuantities Ordinal puis quantité exacte (jamais clé catalogue issue du POST). */
    private function __construct(
        public array $escortQuantities,
        public ?int $destinationChoice,
    ) {
    }

    /**
     * Null désigne un formulaire qui n'a pas été soumis ; un vrai POST sans choix fournit [].
     * Le futur contrôleur extrait et vérifie explicitement le seul _token Symfony avant cet appel.
     */
    public static function parse(?array $submitted, PiratePilotCommandOptions $options): ?self
    {
        if ($submitted === null) {
            return null;
        }
        if ($options->action === PiratePilotCommandAction::Attack) {
            if (array_keys($submitted) !== ['escort'] || !is_array($submitted['escort'])
                || count($submitted['escort']) > PiratePilotCommandOptions::MAX_OPTIONS) {
                throw self::invalid();
            }

            return new self(self::escortQuantities($submitted['escort'], $options), null);
        }
        if ($options->action->requiresDestination()) {
            if (array_keys($submitted) !== ['destination_choice']
                || ($choice = self::decimal($submitted['destination_choice'])) === null || $choice < 1
                || $choice > PiratePilotCommandOptions::MAX_OPTIONS || $options->colonyId($choice) === null) {
                throw self::invalid();
            }

            return new self([], $choice);
        }
        if ($submitted !== []) {
            throw self::invalid();
        }

        return new self([], null);
    }

    /** @return array<int,int> */
    private static function escortQuantities(array $submitted, PiratePilotCommandOptions $options): array
    {
        $quantities = [];
        $remaining = $options->fleetLimit;
        foreach ($submitted as $choice => $raw) {
            $quantity = self::decimal($raw);
            if (!is_int($choice) || $choice < 1 || $choice > PiratePilotCommandOptions::MAX_OPTIONS
                || ($max = $options->maxEscortQuantity($choice)) === null
                || $quantity === null || $quantity > $max || $quantity > $remaining) {
                throw self::invalid();
            }
            if ($quantity === 0) {
                continue;
            }

            $quantities[$choice] = $quantity;
            $remaining -= $quantity; // Calcul borné sans addition d'entrées potentiellement trop grandes.
        }
        if ($quantities === []) {
            throw self::invalid();
        }
        ksort($quantities, SORT_NUMERIC);

        return $quantities;
    }

    private static function decimal(mixed $raw): ?int
    {
        if (!is_string($raw) || preg_match('/^(?:0|[1-9][0-9]{0,9})$/D', $raw) !== 1) {
            return null;
        }
        $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => [
            'min_range' => 0, 'max_range' => PiratePilotCommandOptions::MAX_QUANTITY,
        ]]);

        return $value === false ? null : $value;
    }

    private static function invalid(): \DomainException
    {
        return new \DomainException('Les choix de cette commande ne sont plus valides. Rouvrez le dossier.');
    }
}
