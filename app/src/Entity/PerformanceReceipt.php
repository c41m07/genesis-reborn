<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'performance_receipt')]
#[ORM\Index(name: 'performance_receipt_expiry_idx', columns: ['expires_at'])]
final class PerformanceReceipt
{
    #[ORM\Id, ORM\Column(length: 64, options: ['fixed' => true])]
    public string $receipt;

    #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
    public \DateTimeImmutable $expiresAt;
}
