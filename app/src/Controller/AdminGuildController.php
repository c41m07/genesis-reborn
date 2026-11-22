<?php

namespace App\Controller;

use App\Repository\AdminGuildReadRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminGuildController extends AbstractController
{
    #[Route('/admin/guildes', name: 'app_admin_guild_directory', methods: ['GET'])]
    public function index(Request $request, AdminGuildReadRepository $reads): Response
    {
        $universeId = max(0, $request->query->getInt('universe'));
        $search = mb_substr(trim($request->query->getString('q')), 0, 100);
        $after = max(0, $request->query->getInt('after'));
        return $this->render('admin/guild_directory.html.twig', [
            'guild' => null, 'universes' => $reads->universes(), 'selectedUniverse' => $universeId,
            'query' => $search, 'after' => $after, 'listing' => $reads->listing($universeId, $search, $after),
        ]);
    }

    #[Route('/admin/universes/{universeId}/guildes/{id}/{section}', name: 'app_admin_guild_detail',
        requirements: ['universeId' => '\d+', 'id' => '\d+', 'section' => 'members|diplomacy|projects'],
        defaults: ['section' => 'members'], methods: ['GET'])]
    public function detail(int $universeId, int $id, string $section, Request $request, AdminGuildReadRepository $reads): Response
    {
        $guild = $reads->guild($universeId, $id);
        if ($guild === false) {
            throw $this->createNotFoundException('Guilde active introuvable dans cet univers.');
        }
        $after = max(0, $request->query->getInt('after'));
        return $this->render('admin/guild_directory.html.twig', [
            'guild' => $guild, 'section' => $section, 'after' => $after,
            'listing' => $reads->section($guild, $section, $after),
        ]);
    }
}
