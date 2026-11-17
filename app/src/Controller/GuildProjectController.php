<?php

namespace App\Controller;

use App\Entity\GuildProject;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Player\{GuildProjectPageView, GuildProjectService, RelationResolver};
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class GuildProjectController extends AbstractController
{
    #[Route('/guildes/projets', name: 'app_guild_projects', methods: ['GET'])]
    #[Route('/guildes/projets/disponibles', name: 'app_guild_projects_available', methods: ['GET'])]
    #[Route('/guildes/projets/historique', name: 'app_guild_projects_history', methods: ['GET'])]
    public function index(Request $request, UniverseContext $context, RelationResolver $relations, GuildProjectPageView $pages): Response
    {
        $actor = $context->membership();
        $page = match ($request->attributes->get('_route')) {
            'app_guild_projects_available' => 'available',
            'app_guild_projects_history' => 'history',
            default => 'ongoing',
        };
        return $this->render('player/guild_projects.html.twig', $pages->prepareList(
            $actor, $relations->guild($actor), $page, max(0, $request->query->getInt('before')),
        ));
    }

    #[Route('/guildes/projets/{id}', name: 'app_guild_project', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, Request $request, UniverseContext $context, GameRepository $repo, GameCatalog $catalog, RelationResolver $relations, GuildProjectPageView $pages): Response
    {
        $actor = $context->membership();
        $guild = $relations->guild($actor);
        $project = $repo->find(GuildProject::class, $id);
        if (!$guild || !$catalog->forUniverse($actor->universe)['features']['guilds_enabled'] || !$project || $project->guildId !== $guild->id || $project->universe->id !== $actor->universe->id) {
            throw $this->createNotFoundException('Projet inaccessible.');
        }
        return $this->render('player/guild_project.html.twig', [
            'membership' => $actor, 'guild' => $guild, 'project' => $project,
        ] + $pages->prepareDetail($project, $actor, max(0, $request->query->getInt('before_contribution'))));
    }

    #[Route('/guildes/projets/modeles/{id}/lancer', name: 'app_guild_project_start', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function start(
        int $id,
        Request $request,
        UniverseContext $context,
        GuildProjectService $projects,
    ): Response
    {
        if (!$this->isCsrfTokenValid('guild_project_start_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }
        try {
            $project = $projects->start($context->membership(), $id, $request->request->getInt('version'));
            $this->addFlash('success', 'Projet lancé. Les membres peuvent maintenant contribuer.');
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }
        return isset($project) ? $this->redirectToRoute('app_guild_project', ['id' => $project->id]) : $this->redirectToRoute('app_guild_projects_available');
    }
    #[Route('/guildes/projets/{id}/contribuer', name: 'app_guild_project_contribute', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function contribute(
        int $id,
        Request $request,
        UniverseContext $context,
        GuildProjectService $projects,
    ): Response
    {
        if (!$this->isCsrfTokenValid('guild_project_donation_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }
        try {
            $completed = $projects->contribute(
                $context->membership(),
                $id,
                $request->request->getInt('planet_id'),
                $request->request->all('resources'),
                $request->request->getInt('version')
            );
            $this->addFlash('success', $completed ? 'Don enregistré. Le projet est terminé.' : 'Don enregistré. Merci pour votre contribution.');
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }
        return $this->redirectToRoute('app_guild_project', ['id' => $id]);
    }
}
