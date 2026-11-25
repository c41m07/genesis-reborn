<?php

namespace App\Service\Combat;

/** Le calcul exact dépasse les moyens alloués, sans retirer d'unité au scénario. */
final class CombatCapacityExceeded extends \DomainException
{
}
