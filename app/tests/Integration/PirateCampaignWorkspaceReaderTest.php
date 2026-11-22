<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\{Galaxy, PirateAttackCampaign, PirateReconnaissance, Player, PlayerUniverse, SolarSystem, Universe};
use App\Repository\PirateCampaignReadRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\PirateCampaign\{PirateCampaignCatalogue, PirateCampaignPresenter, PirateCampaignProgress, PirateCampaignSequence, PirateCampaignWorkspace, PirateCampaignWorkspaceReader};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class PirateCampaignWorkspaceReaderTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;
    private int $siteIndex = 0;
    private PirateCampaignCatalogue $catalogue;
    private PirateCampaignWorkspaceReader $reader;

    protected function setUp(): void
    {
        self::bootKernel(['debug' => true]);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée.');
        }
        $schema = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $this->clock = new MockClock('2026-11-21T09:00:00Z');
        $this->catalogue = self::getContainer()->get(PirateCampaignCatalogue::class);
        $progress = new PirateCampaignProgress($this->catalogue);
        $presenter = new PirateCampaignPresenter($this->catalogue, $progress, self::getContainer()->get(GameCatalog::class));
        $this->reader = new PirateCampaignWorkspaceReader(self::getContainer()->get(Security::class), $this->clock,
            new PirateCampaignReadRepository($this->em), $this->catalogue,
            new PirateCampaignWorkspace($this->catalogue, new PirateCampaignSequence($this->catalogue, $progress), $presenter));
    }

    public function testAuthenticatedOwnerOnlyAndScopeReliesOnFreshSelectionNotTheTokenSnapshot(): void
    {
        $firstUniverse = $this->universe('pirate-fixture-1');
        $secondUniverse = $this->universe('pirate-fixture-2');
        $owner = $this->account('pirate-reader');
        $ally = $this->account('pirate-other');
        $own = $this->seat($owner, $firstUniverse, true);
        $other = $this->seat($ally, $firstUniverse, true);
        $inAnotherWorld = $this->seat($owner, $secondUniverse, false);
        $this->addAttackResult($own, 0);
        // Même clés dans une autre participation : aucune lecture croisée, même pour le même compte.
        $this->addAttackResult($other, 0);
        $this->addAttackResult($other, 1);
        $this->addAttackResult($inAnotherWorld, 0);
        $this->addAttackResult($inAnotherWorld, 1);
        // Donnée hors catalogue et anomalie avec une ancre d'un autre univers : ne pas les joindre.
        $this->addAttackResult($own, 1, 'fixture-not-a-campaign-key');
        $this->addAttackResult($own, 1, null, $secondUniverse);
        $this->em->flush();

        self::assertNull($this->reader->current());
        $this->login($owner);
        self::assertSame([['number' => 1, 'won' => true]], $this->reader->current()['history']);
        self::assertSame('Victoire', $this->reader->current(1)['briefing']['status']);
        self::assertNull($this->reader->current(3));
        $db = $this->em->getConnection();
        $db->executeStatement('UPDATE player SET current_player_universe_id = ? WHERE id = ?', [$inAnotherWorld->id, $owner->id]);
        self::assertNull($this->reader->current(1), 'La session conserve exprès l’ancien empire en mémoire.');

        $this->em->clear();
        $owner = $this->em->find(Player::class, $owner->id);
        $this->login($owner);
        self::assertSame(3, $this->reader->current()['current']);
        $db->executeStatement('UPDATE player_universe SET forgotten_at = ? WHERE id = ?',
            ['2026-11-21 08:59:00', $inAnotherWorld->id]);
        self::assertNull($this->reader->current(1), 'Le détachement en base doit être pris en compte sans attendre la session.');
    }

    public function testBudgetRemainsBoundedAfterFullCampaignAndNoQueryWritesOrRevealsResultBeforeApplication(): void
    {
        $world = $this->universe('pirate-fixture-budget');
        $player = $this->account('pirate-reader-budget');
        $seat = $this->seat($player, $world, true);
        $this->addAttackResult($seat, 0);
        $this->em->flush();
        $id = $player->id;
        [$first, $small] = $this->measure($id, 1);
        self::assertSame('Victoire', $first['briefing']['status']);

        $seat = $this->em->find(PlayerUniverse::class, $seat->id);
        for ($index = 1; $index < 20; ++$index) {
            $this->addAttackResult($seat, $index, pending: $index === 19);
        }
        $this->em->flush();

        [$last, $large] = $this->measure($id, 20);
        self::assertCount(20, $last['history']);
        self::assertSame(20, $last['current']);
        self::assertSame('Combat en cours', $last['briefing']['status']);
        self::assertNull($last['briefing']['losses']);
        self::assertNull($last['briefing']['return']);
        $representation = json_encode($last, JSON_THROW_ON_ERROR);
        foreach (['secret-fixture-checkpoint', 'fixture-private-dispatch', '2099-01-01', 'coords'] as $hidden) {
            self::assertStringNotContainsString($hidden, $representation);
        }
        // Maintenir une marge ORM raisonnable sans imposer un chiffre SQL exact ou un seuil temporel.
        self::assertLessThanOrEqual(6, $large);
        self::assertLessThanOrEqual($small + 1, $large);
    }

    private function login(Player $player): void
    {
        self::getContainer()->get('security.token_storage')->setToken(new UsernamePasswordToken($player, 'main', $player->getRoles()));
    }

    /** @return array{array,int} */
    private function measure(int $id, int $number): array
    {
        $this->em->clear();
        $player = $this->em->find(Player::class, $id);
        self::assertInstanceOf(Player::class, $player);
        $this->login($player);
        $holder = self::getContainer()->get('doctrine.debug_data_holder');
        $holder->reset();
        $read = $this->reader->current($number);
        $queries = $holder->getData()['default'] ?? [];
        self::assertNotEmpty($queries);
        foreach ($queries as $query) {
            self::assertMatchesRegularExpression('/^\s*SELECT\b/i', $query['sql']);
        }

        return [$read, count($queries)];
    }

    private function universe(string $slug): Universe
    {
        $world = new Universe();
        $world->slug = $world->name = $slug;
        $this->em->persist($world);

        return $world;
    }

    private function account(string $name): Player
    {
        $player = new Player();
        $player->username = $name;
        $player->email = $name.'@example.test';
        $player->password = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $player->emailVerifiedAt = $this->clock->now();
        $this->em->persist($player);

        return $player;
    }

    private function seat(Player $player, Universe $world, bool $current): PlayerUniverse
    {
        $seat = new PlayerUniverse();
        $seat->player = $player;
        $seat->universe = $world;
        $seat->joinedAt = $this->clock->now();
        $this->em->persist($seat);
        if ($current) {
            $player->currentPlayerUniverse = $seat;
        }

        return $seat;
    }

    private function addAttackResult(PlayerUniverse $viewer, int $index, ?string $key = null, ?Universe $siteUniverse = null, bool $pending = false): void
    {
        $key ??= $this->catalogue->keys()[$index];
        $galaxy = new Galaxy();
        $galaxy->universe = $siteUniverse ?? $viewer->universe;
        $galaxy->name = 'Système de recette '.++$this->siteIndex;
        $system = new SolarSystem();
        $system->galaxy = $galaxy;
        $system->name = 'Ancre de recette';
        $system->coordX = $system->coordY = 0;
        $this->em->persist($galaxy);
        $this->em->persist($system);
        // Identités présentes avant la vraie livraison de rapport.
        $this->em->flush();
        $at = $this->clock->now();
        $prepared = $this->catalogue->prepareForServer($this->catalogue->keys()[$index], $viewer->universe);
        $recon = new PirateReconnaissance($viewer, $viewer->player, $system, $key, $at->modify('-20 seconds'),
            $at->modify('-16 seconds'), 'fixture-private-dispatch', null, $prepared);
        $recon->reportWhenDue($viewer, 'fixture-private-dispatch', $at->modify('-15 seconds'));
        $attack = new PirateAttackCampaign();
        $attack->reconnaissance = $recon;
        $attack->originId = $system->id;
        $attack->departedAt = $at->modify('-14 seconds');
        $attack->arrivalAt = $at->modify('-13 seconds');
        $attack->travelSeconds = 1;
        $attack->roundSeconds = 60;
        $attack->scenario = [];
        $attack->reservedShips = [];
        if ($pending) {
            $attack->timeline = ['state' => 'scheduled', 'arrival_flow' => 2, 'arrival_observed' => $at->modify('-12 seconds')->format(DATE_ATOM),
                'combatDue' => '2099-01-01T00:00:00+00:00', 'opaque_battle' => ['future_result' => 'secret-fixture-checkpoint']];
            $attack->nextAt = new \DateTimeImmutable('2099-01-01T00:00:00+00:00');
            $attack->dispatchToken = 'fixture-private-dispatch';
        } else {
            $attack->timeline = ['state' => 'won', 'resolved' => $at->modify('-1 second')->format(DATE_ATOM)];
            $attack->report = ['outcome' => 'attacker_wins'];
        }
        $this->em->persist($recon);
        $this->em->persist($attack);
    }
}
