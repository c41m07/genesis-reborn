<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Intention explicite conservée séparément du résultat d'envoi initial. */
#[ORM\Entity]
#[ORM\Table(name: 'verification_recovery_event')]
#[ORM\UniqueConstraint(name: 'verification_recovery_request_unique', columns: ['recovery_request_id'])]
class VerificationRecoveryEvent
{
    #[ORM\Id, ORM\OneToOne(targetEntity: VerificationDeliveryRequest::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public VerificationDeliveryRequest $originalRequest;

    #[ORM\Column(length: 64)]
    public string $requestKeyHash;

    #[ORM\Column(type: 'bigint')]
    public int $recordedAt;

    #[ORM\OneToOne(targetEntity: VerificationDeliveryRequest::class)]
    #[ORM\JoinColumn(name: 'recovery_request_id', nullable: true, onDelete: 'SET NULL')]
    public ?VerificationDeliveryRequest $recoveryRequest = null;

    #[ORM\Column(nullable: true)]
    public ?int $generationVersion = null;

    #[ORM\Column(type: 'bigint', nullable: true)]
    public ?int $handoffAt = null;

    #[ORM\Column(length: 24, nullable: true)]
    public ?string $deliveryOutcome = null;

    #[ORM\Column(type: 'bigint', nullable: true)]
    public ?int $deliveryResolvedAt = null;
}
