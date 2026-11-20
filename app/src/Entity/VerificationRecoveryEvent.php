<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Intention explicite conservée séparément du résultat d'envoi initial. */
#[ORM\Entity]
#[ORM\Table(name: 'verification_recovery_event')]
class VerificationRecoveryEvent
{
    #[ORM\Id, ORM\OneToOne(targetEntity: VerificationDeliveryRequest::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public VerificationDeliveryRequest $originalRequest;

    #[ORM\Column(length: 64)]
    public string $requestKeyHash;

    #[ORM\Column(type: 'bigint')]
    public int $recordedAt;
}
