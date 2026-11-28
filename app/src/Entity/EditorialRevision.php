<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'editorial_publication_idx', columns: ['kind', 'universe_id', 'code', 'publish_at', 'id'])]
class EditorialRevision
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?Universe $universe = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?Player $author = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true)]
    public ?EditorialImage $image = null;

    #[ORM\Column(length: 16)]
    public string $kind;

    #[ORM\Column(length: 40)]
    public string $code;

    #[ORM\Column(type: 'json')]
    public array $content = [];

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $publishAt = null;
}
