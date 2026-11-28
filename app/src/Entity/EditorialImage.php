<?php
declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class EditorialImage
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    /** WebP réencodé, conservé dans les sauvegardes avec les révisions qui le référencent. */
    #[ORM\Column(type: 'text', length: 16777215)]
    public string $encoded;
}
