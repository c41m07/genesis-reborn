<?php

namespace App\Service\Boost;

final class BoxOverflowConfirmation extends \DomainException
{
    /** @param array<string,float> $loss Plafonds de perte arrondis au centième supérieur. */
    public function __construct(public readonly array $estimate, public readonly array $loss, public readonly string $colony)
    {
        parent::__construct('Les entrepôts ne peuvent pas accueillir tout le contenu.');
    }
}
