<?php
namespace App\Service\Player;

use App\Entity\{Guild,PlayerUniverse,SocialMessage};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Notification\NotificationService;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class SocialMessageService
{
    public function __construct(
        private GameRepository $repo,
        private GameManager $manager,
        private RelationResolver $relations,
        private GameCatalog $catalog,
        private NotificationService $notifications,
        private ClockInterface $clock,
        #[Autowire(service: 'limiter.social_message')] private RateLimiterFactory $messageLimiter,
    ) {}

    public function sendPrivate(PlayerUniverse $sender, int $recipientId, string $content): void
    {
        $this->manager->transaction(function () use ($sender, $recipientId, $content): void {
            $this->lockUniverse($sender);
            $recipient = $this->repo->find(PlayerUniverse::class, $recipientId);
            if (!$recipient || $recipient->universe->id !== $sender->universe->id || $recipient->id === $sender->id) {
                throw new \DomainException('Destinataire inaccessible dans cet univers.');
            }
            $content = $this->validateContent($content);
            $this->consumeRateLimit($sender);

            $message = new SocialMessage();
            $message->sender = $sender;
            $message->recipient = $recipient;
            $message->content = $content;
            $message->createdAt = $this->clock->now();
            $this->manager->save($message);
            $this->notifications->enqueue($recipient, 'Nouveau message privé de ' . $sender->player->username . '.');
        });
    }

    public function sendGuild(PlayerUniverse $sender, string $content): void
    {
        $this->manager->transaction(function () use ($sender, $content): void {
            $this->lockUniverse($sender);
            if (!$this->catalog->forUniverse($sender->universe)['features']['guilds_enabled']) {
                throw new \DomainException('Les guildes sont désactivées dans cet univers.');
            }
            $guild = $this->relations->guild($sender);
            if (!$guild || $guild->universe->id !== $sender->universe->id) {
                throw new \DomainException('Rejoignez une guilde pour écrire dans son canal.');
            }
            $content = $this->validateContent($content);
            $this->consumeRateLimit($sender);

            $message = new SocialMessage();
            $message->sender = $sender;
            $message->guild = $guild;
            $message->content = $content;
            $message->createdAt = $this->clock->now();
            $this->manager->save($message);
            foreach ($this->repo->all(\App\Entity\GuildMember::class, ['guild' => $guild]) as $member) {
                if ($member->playerUniverse->id !== $sender->id) {
                    $this->notifications->enqueue($member->playerUniverse, 'Nouveau message dans la guilde ' . $guild->name . '.');
                }
            }
        });
    }

    public function privateConversation(PlayerUniverse $viewer, int $peerId): array
    {
        $peer = $this->repo->find(PlayerUniverse::class, $peerId);
        if (!$peer || $peer->id === $viewer->id || $peer->universe->id !== $viewer->universe->id) {
            throw new \DomainException('Conversation inaccessible dans cet univers.');
        }

        return $this->manager->transaction(function () use ($viewer, $peer): array {
            $this->lockUniverse($viewer);
            $conversation = $this->repo->privateConversation($viewer, $peer);
            $readAt = $this->clock->now();
            foreach ($conversation as $message) {
                if ($message->recipient?->id === $viewer->id && $message->readAt === null) {
                    $message->readAt = $readAt;
                }
            }
            return $conversation;
        });
    }

    public function guildConversation(PlayerUniverse $viewer, Guild $guild): array
    {
        if ($guild->universe->id !== $viewer->universe->id || $this->relations->guild($viewer)?->id !== $guild->id) {
            throw new \DomainException('Vous ne pouvez pas lire ce canal de guilde.');
        }

        return $this->manager->transaction(function () use ($viewer, $guild): array {
            $this->lockUniverse($viewer);
            if ($this->relations->guild($viewer)?->id !== $guild->id) {
                throw new \DomainException('Vous ne pouvez pas lire ce canal de guilde.');
            }
            $conversation = $this->repo->guildConversation($guild);
            $this->manager->lock($viewer->player);
            $key = $this->guildReadKey($viewer, $guild);
            $viewer->player->preferences[$key] = max((int) ($viewer->player->preferences[$key] ?? 0), ...array_merge([0], array_map(static fn (SocialMessage $message): int => $message->id, $conversation)));
            return $conversation;
        });
    }

    public function unreadPrivateCount(PlayerUniverse $viewer): int
    {
        return $this->repo->unreadPrivateMessageCount($viewer);
    }

    public function unreadGuildCount(PlayerUniverse $viewer): int
    {
        $guild = $this->relations->guild($viewer);
        return $guild ? $this->repo->unreadGuildMessageCount($viewer, $guild, (int) ($viewer->player->preferences[$this->guildReadKey($viewer, $guild)] ?? 0)) : 0;
    }

    private function guildReadKey(PlayerUniverse $viewer, Guild $guild): string
    {
        return 'guild_read_' . $viewer->id . '_' . $guild->id;
    }

    private function lockUniverse(PlayerUniverse $membership): void
    {
        $this->manager->lock($membership);
        $this->manager->lock($membership->universe);
        if (!$membership->universe->isAccessible($this->clock->now())) {
            throw new \DomainException('Univers inaccessible.');
        }
    }

    private function validateContent(string $content): string
    {
        $content = trim($content);
        $length = mb_strlen($content);
        if ($length < 1 || $length > 2000) {
            throw new \DomainException('Un message doit contenir entre 1 et 2 000 caractères.');
        }

        return $content;
    }

    private function consumeRateLimit(PlayerUniverse $membership): void
    {
        $limit = $this->messageLimiter->create('player-' . $membership->player->id)->consume();
        if (!$limit->isAccepted()) {
            throw new \DomainException('Limite atteinte : attendez avant d’envoyer un nouveau message.');
        }
    }
}
