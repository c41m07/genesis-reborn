<?php
declare(strict_types=1);
namespace App\Controller;

use App\Entity\EditorialImage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EditorialImageController extends AbstractController
{
    #[Route('/illustrations/edition/{id}.webp', name:'app_editorial_image', requirements:['id'=>'\d+'], methods:['GET'])]
    public function show(int $id, EntityManagerInterface $em): Response
    {
        // Les ébauches restent réservées à la console jusqu’à leur première publication.
        if (!$this->isGranted('ROLE_ADMIN')) {
            $player = $this->getUser();
            $member = $player instanceof \App\Entity\Player ? $player->currentPlayerUniverse : null;
            $world = $member && $member->isPlayerControlled() && $member->player->id === $player->id ? $member->universe->id : null;
            $published = $em->getConnection()->fetchOne(
                "SELECT 1 FROM editorial_revision WHERE publish_at <= UTC_TIMESTAMP() AND (universe_id IS NULL OR universe_id = ?) AND image_id = ? LIMIT 1",
                [$world,$id]
            );
            if (!$published) throw $this->createNotFoundException();
        }
        $image = $em->find(EditorialImage::class,$id) ?? throw $this->createNotFoundException();
        return new Response(base64_decode($image->encoded),200,['Content-Type'=>'image/webp','X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, max-age=3600']);
    }
}
