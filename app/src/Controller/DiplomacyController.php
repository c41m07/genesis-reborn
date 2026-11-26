<?php

namespace App\Controller;

use App\Entity\GuildRank;
use App\Service\Universe\UniverseContext;
use App\Service\Player\{RelationResolver, DiplomacyService, GuildRankService, DiplomacyPageView, GuildRecruitmentView};
use App\Repository\GameRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class DiplomacyController extends AbstractController
{
    #[Route('/guildes', name: 'app_guilds', defaults: ['page' => 'guilds'], methods: ['GET', 'POST'])]
    #[Route('/guildes/creer', name: 'app_guild_create', defaults: ['page' => 'create'], methods: ['GET', 'POST'])]
    #[Route('/guildes/membres', name: 'app_guild_members', defaults: ['page' => 'members'], methods: ['GET', 'POST'])]
    #[Route('/guildes/grades', name: 'app_guild_ranks', defaults: ['page' => 'ranks'], methods: ['GET', 'POST'])]
    #[Route('/guildes/gestion', name: 'app_guild_management', defaults: ['page' => 'management'], methods: ['GET', 'POST'])]
    #[Route('/diplomatie/commandants', name: 'app_personal_relations', defaults: ['page' => 'relations'], methods: ['GET', 'POST'])]
    #[Route('/diplomatie', name: 'app_diplomacy', defaults: ['page' => 'diplomacy'], methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        UniverseContext $context,
        RelationResolver $relations,
        DiplomacyService $service,
        GameRepository $repository,
        GuildRankService $rankService,
        DiplomacyPageView $view,
        GuildRecruitmentView $recruitment,
    ): Response
    {
        $membership = $context->membership();
        $ownGuild = $relations->guild($membership);
        $page = $request->attributes->getString('page');
        $creationDraftKey = 'guild_creation_draft_'.$membership->id.'_'.$membership->universe->id;
        $personalPage = $page === 'relations' || ($page === 'diplomacy' && !$ownGuild)
            || $request->query->getString('tab') === 'relations';
        $search = mb_substr(trim($request->query->getString('q')), 0, $personalPage ? 40 : 60);
        // Les anciennes adresses restent des points d’entrée, sans conserver l’ancien écran composite.
        if ($request->isMethod('GET') && $request->query->has('tab')) {
            $legacyPage = $request->query->getString('tab');
            $route = match ($legacyPage) {
                'members' => $ownGuild ? 'app_guild_members' : 'app_guild_create',
                'ranks' => $ownGuild ? 'app_guild_ranks' : 'app_guild_create',
                'relations' => 'app_personal_relations',
                'join' => 'app_guilds',
                'diplomacy' => $ownGuild ? 'app_diplomacy' : 'app_guilds',
                default => $ownGuild ? 'app_guilds' : ($search !== '' ? 'app_guilds' : 'app_guild_create'),
            };
            $parameters = array_intersect_key($request->query->all(), array_flip(['q', 'rank']));
            return $this->redirectToRoute($route, $parameters);
        }
        if (!$request->isMethod('POST')) {
            if ($ownGuild && $page === 'create') {
                return $this->redirectToRoute('app_guilds');
            }
            if (!$ownGuild && in_array($page, ['members', 'ranks', 'management'], true)) {
                return $this->redirectToRoute('app_guilds');
            }
        }
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('diplomacy', $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Formulaire expiré.');
            }
            $updated = false;
            try {
                $action = (string) $request->request->get('action');
                $target = (int) $request->request->get('target');
                match ($action) {
                    'rank_save' => $rankService->save(
                        $membership,
                        $target,
                        $request->request->getString('name'),
                        $request->request->getInt('position'),
                        $request->request->all('permissions'),
                        $request->request->getInt('version')
                    ),
                    'rank_assign' => $rankService->assign($membership, $target, $request->request->getInt('rank'), $request->request->getInt('expected_rank')),
                    'create' => $service->create($membership, (string) $request->request->get('name'), $request->request->getString('tag')),
                    'tag' => $service->setTag($membership, $request->request->getString('tag')),
                    'join' => $service->applyToGuild($membership, $target),
                    'leave' => $service->leave($membership),
                    'transfer' => $service->transfer($membership, $target),
                    'disband' => $service->disband($membership),
                    'relation' => $service->relation(
                        $membership,
                        $target,
                        (string) $request->request->get('status'),
                        $request->request->getInt('relation_id'),
                        $request->request->getInt('relation_version'),
                        $request->request->getString('treaty_action', 'legacy')
                    ),
                    'propose', 'accept', 'break', 'rival', 'peace_propose', 'peace_accept', 'peace_reject' => $service->alliance($membership, $target, $action),
                    default => throw new \DomainException('Action inconnue.'),
                };
                $updated = true;
                if ($action === 'create') {
                    $request->getSession()->remove($creationDraftKey);
                }
                $this->addFlash('success', $action === 'join' ? 'Candidature envoyée. Elle doit être acceptée par la guilde.' : 'Diplomatie mise à jour.');
            } catch (\DomainException $e) {
                if ($action === 'create') {
                    // Le retour après erreur conserve la saisie dans ce seul empire, sans valider le brouillon.
                    // Les limites de stockage dépassent les maxima autorisés : une valeur trop longue reste invalide.
                    $request->getSession()->set($creationDraftKey, [
                        'name' => mb_substr($request->request->getString('name'), 0, 120),
                        'tag' => mb_substr($request->request->getString('tag'), 0, 10),
                    ]);
                }
                $this->addFlash('error', $e->getMessage());
            }
            $redirectRoute = match ($action) {
                'rank_save' => 'app_guild_ranks',
                'rank_assign' => 'app_guild_members',
                'create' => $updated ? 'app_guilds' : 'app_guild_create',
                'join' => 'app_guilds',
                'leave', 'disband' => $updated ? 'app_guilds' : 'app_guild_management',
                'tag', 'transfer' => 'app_guild_management',
                'relation' => 'app_diplomacy',
                'propose', 'accept', 'break', 'rival', 'peace_propose', 'peace_accept', 'peace_reject' => 'app_personal_relations',
                default => 'app_guilds',
            };
            $redirect = $search !== '' ? ['q' => $search] : [];
            if ($redirectRoute === 'app_personal_relations' && $request->query->getInt('after') > 0) {
                $redirect['after'] = $request->query->getInt('after');
            }
            if ($action === 'rank_save') {
                $redirect['rank'] = $target;
                if ($updated) {
                    $guild = $relations->guild($membership);
                    $saved = $guild ? $repository->one(GuildRank::class, ['guild' => $guild, 'name' => trim($request->request->getString('name'))]) : null;
                    if ($saved) {
                        $redirect['rank'] = $saved->id;
                    }
                }
            }
            return $this->redirectToRoute($redirectRoute, $redirect);
        }
        $data = $view->prepare(
            $membership,
            $ownGuild,
            $page,
            $search,
            $request->query->getInt('rank', -1),
            max(0, $request->query->getInt('after')),
        );
        if ($data['tab'] === 'create' && !$ownGuild && $data['enabled']) {
            // Le brouillon survit au rechargement ; seule une création réussie l’efface.
            $data['guildCreationDraft'] = $request->getSession()->get($creationDraftKey, []);
        }
        if (in_array($data['tab'], ['guilds', 'join'], true)) {
            $data['recruitmentInbox'] = $recruitment->inbox($membership);
        }
        return $this->render('player/diplomacy.html.twig', $data, new Response(headers: ['Cache-Control' => 'private, no-store']));
    }
}
