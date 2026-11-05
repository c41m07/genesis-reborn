<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'patch_note_status_published_idx', columns: ['status', 'published_at'])]
class PatchNote
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 160)]
    public string $title = '';

    #[ORM\Column(length: 40)]
    public string $version = '';

    #[ORM\Column(length: 500)]
    public string $summary = '';

    #[ORM\Column(type: 'text')]
    public string $content = '';

    #[ORM\Column(length: 16)]
    public string $status = 'draft';

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
