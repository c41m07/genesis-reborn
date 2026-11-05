<?php
namespace App\Controller;

use App\Entity\{Guild,PlayerUniverse};
use App\Repository\GameRepository;
use App\Service\Player\{RelationResolver,SocialMessageService};
use App\Service\Admin\AdminService;
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;

final class SocialMessageController extends AbstractController
{
    #[Route('/messages/non-lus', name: 'app_messages_unread', methods: ['GET'])]
    public function unread(Request $request, UniverseContext $context, SocialMessageService $messages, RelationResolver $relations, GameRepository $repo): Response
    {
        $membership = $context->membership();
        return $this->json(['unread' => $messages->unreadPrivateCount($membership) + $messages->unreadGuildCount($membership), 'conversations' => $this->renderView('player/_message_contacts.html.twig', ['contacts' => $repo->messageContacts($membership), 'guild' => $relations->guild($membership), 'unreadGuild' => $messages->unreadGuildCount($membership), 'peer' => $repo->find(PlayerUniverse::class, $request->query->getInt('with')), 'activeChannel' => $request->query->getString('channel')])], headers: ['Cache-Control' => 'no-store']);
    }

    #[Route('/messages', name: 'app_messages', methods: ['GET', 'POST'])]
    public function index(Request $request, UniverseContext $context, SocialMessageService $messages, RelationResolver $relations, GameRepository $repo, AdminService $admin): Response
    {
        $membership = $context->membership();
        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('action');
            $tokenId = match ($action) { 'guild' => 'guild_message', 'report' => 'private_message_report', default => 'private_message' };
            if (!$this->isCsrfTokenValid($tokenId, $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Formulaire expiré.');
            }
            try {
                if ($action === 'report') {
                    $admin->reportMessage($membership, (int) $request->request->get('message_id'), (string) $request->request->get('reason'));
                    $this->addFlash('success', 'Signalement transmis à la modération.');
                    return $this->redirectToRoute('app_messages', ['with' => (int) $request->request->get('with')]);
                }
                if ($action === 'private') {
                    $peerId = (int) $request->request->get('recipient');
                    $messages->sendPrivate($membership, $peerId, (string) $request->request->get('content'));
                    $this->addFlash('success', 'Message privé envoyé.');
                    return $this->redirectToRoute('app_messages', ['with' => $peerId]);
                }
                if ($action === 'guild') {
                    $messages->sendGuild($membership, (string) $request->request->get('content'));
                    $this->addFlash('success', 'Message envoyé à la guilde.');
                    return $this->redirectToRoute('app_messages', ['channel' => 'guild']);
                } else {
                    throw new \DomainException('Action de message inconnue.');
                }
            } catch (\DomainException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }

            return $this->redirectToRoute('app_messages');
        }

        $activeChannel = $request->query->getString('channel') === 'guild' ? 'guild' : 'private';
        $peerId = $activeChannel === 'guild' ? 0 : $request->query->getInt('with');
        $peer = null;
        $conversation = [];
        if ($peerId > 0) {
            try {
                $peer = $repo->find(PlayerUniverse::class, $peerId);
                $conversation = $messages->privateConversation($membership, $peerId);
            } catch (\DomainException) {
                $peer = null;
            }
        }

        $guild = $relations->guild($membership);
        $guildConversation = $guild && $activeChannel === 'guild' ? $messages->guildConversation($membership, $guild) : [];
        $search = mb_substr(trim($request->query->getString('q')), 0, 40);

        return $this->render('player/messages.html.twig', [
            'membership' => $membership,
            'contacts' => $repo->messageContacts($membership),
            'search' => $search,
            'searchResults' => $repo->searchMessageRecipients($membership, $search),
            'unreadGuild' => $messages->unreadGuildCount($membership),
            'peer' => $peer,
            'conversation' => $conversation,
            'guild' => $guild,
            'guildConversation' => $guildConversation,
            'unreadPrivate' => $messages->unreadPrivateCount($membership),
            'activeChannel' => $activeChannel,
        ]);
    }
}
