<?php
namespace App\Tests\Integration;

use App\Entity\{Fleet,FleetShip,Guild,GuildMember,LeaderboardSnapshot,Planet,PlayerUniverse,Universe};
use App\Service\Player\{DailyLeaderboard,LeaderboardService,RegistrationService};
use App\Service\Queue\QueueService;
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class DailyLeaderboardTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host']??'') !== 'database-test' || ($params['dbname']??'') !== 'genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);$tool->createSchema($metadata);
        $this->clock = new MockClock('2026-11-10T22:59:58Z');
        self::getContainer()->set('clock', $this->clock);
        self::getContainer()->get(UniverseImporter::class)->import();
    }

    private function join(string $name, string $slug = 'omega-1'): PlayerUniverse
    {
        $data = new RegistrationData();
        $data->email = strtolower($name).'@example.test';$data->username = $name;$data->plainPassword = 'A-test-password-2026';
        $player = self::getContainer()->get(RegistrationService::class)->register($data);
        $universe = $this->em->getRepository(Universe::class)->findOneBy(['slug'=>$slug]);
        self::getContainer()->get(UniverseService::class)->select($player, $universe->id);
        return $player->currentPlayerUniverse;
    }

    private function ship(PlayerUniverse $membership, int $quantity): FleetShip
    {
        $planet = $this->em->getRepository(Planet::class)->findOneBy(['owner'=>$membership]);
        $fleet = new Fleet();$fleet->playerUniverse = $membership;$fleet->homePlanet = $planet;$fleet->status = 'idle';$fleet->mission = 'idle';
        $ship = new FleetShip();$ship->fleet = $fleet;$ship->slug = 'fighter';$ship->quantity = $quantity;
        $this->em->persist($fleet);$this->em->persist($ship);$this->em->flush();
        return $ship;
    }

    private function guild(PlayerUniverse $leader, string $name): Guild
    {
        $guild = new Guild();$guild->universe = $leader->universe;$guild->leader = $leader;$guild->name = $name;
        $member = new GuildMember();$member->guild = $guild;$member->playerUniverse = $leader;
        $this->em->persist($guild);$this->em->persist($member);$this->em->flush();
        return $guild;
    }

    private function row(array $daily, PlayerUniverse $membership): array
    {
        foreach ($daily['players'] as $row) if ($row['membership_id'] === $membership->id) return $row;
        throw new \RuntimeException('Joueur absent du classement.');
    }

    public function testScoresAndRanksStayFrozenUntilParisMidnightWithSignedChanges(): void
    {
        $a = $this->join('Alpha');$b = $this->join('Beta');
        $shipA = $this->ship($a, 10);$shipB = $this->ship($b, 1);
        $this->guild($a, 'Alpha');$this->guild($b, 'Beta');
        $service = self::getContainer()->get(DailyLeaderboard::class);
        $first = $service->forUniverse($a);$beforeA = $this->row($first, $a);$beforeB = $this->row($first, $b);
        self::assertNull($beforeA['score_changes']['points']);
        self::assertNull($beforeA['rank_changes']['points']);
        self::assertNull($first['comparison_at']);
        self::assertSame('2026-11-10T23:00:00+00:00', $first['next_update']->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM));
        $shipA->quantity = 0;$shipB->quantity = 20;$this->em->flush();
        $this->clock->sleep(1);
        $frozen = $service->forUniverse($a);
        self::assertSame($beforeA['total_points'], $this->row($frozen, $a)['total_points']);
        self::assertSame($first['guilds'][0]['total_points'], $frozen['guilds'][0]['total_points']);
        self::assertSame(1, $this->row($frozen, $a)['ranks']['fleet']);
        $this->clock->sleep(1);
        self::assertSame(1, $service->tick());
        self::assertSame(0, $service->tick());
        $after = $service->forUniverse($a);$afterA = $this->row($after, $a);$afterB = $this->row($after, $b);
        self::assertLessThan(0, $afterA['score_changes']['points']);
        self::assertGreaterThan(0, $afterB['score_changes']['points']);
        self::assertSame($afterB['total_points'] - $beforeB['total_points'], $afterB['score_changes']['points']);
        self::assertSame(-1, $afterA['rank_changes']['points']);
        self::assertSame(1, $afterB['rank_changes']['points']);
        self::assertSame(1, $afterB['rank_changes']['fleet']);
        self::assertSame(0, $afterB['score_changes']['planets']);
        self::assertSame('Beta', $after['guilds'][0]['guild']->name);
        self::assertSame(1, $after['guilds'][0]['rank_change']);
        self::assertGreaterThan(0, $after['guilds'][0]['points_change']);
        self::assertEquals($first['updated_at'], $after['comparison_at']);
        $this->clock->sleep(86400);
        $unchanged = $service->forUniverse($a);
        self::assertSame(0, $this->row($unchanged, $a)['score_changes']['points']);
        self::assertSame(0, $this->row($unchanged, $a)['rank_changes']['points']);
        self::assertSame(0, $unchanged['guilds'][0]['points_change']);
    }

    public function testLiveProtectionIgnoresFrozenScoresAndNewPlayersWaitForTheNextUpdate(): void
    {
        $a = $this->join('Protected');$ship = $this->ship($a, 1);
        $service = self::getContainer()->get(DailyLeaderboard::class);
        $first = $service->forUniverse($a);
        self::assertTrue($this->row($first, $a)['protection']['active']);
        $ship->quantity = 100000;$this->em->flush();
        $b = $this->join('Newcomer');
        $frozen = $service->forUniverse($a);
        self::assertSame($this->row($first, $a)['total_points'], $this->row($frozen, $a)['total_points']);
        self::assertFalse($this->row($frozen, $a)['protection']['active']);
        self::assertCount(1, $frozen['players']);
        $live = self::getContainer()->get(LeaderboardService::class)->forUniverse($a);
        self::assertGreaterThan(5000, $live[0]['total_points']);
        $this->clock->sleep(2);
        $next = $service->forUniverse($a);
        self::assertCount(2, $next['players']);
        self::assertNull($this->row($next, $b)['rank_changes']['points']);
    }

    public function testSnapshotSurvivesEntityManagerRestartAndUniversesAreIndependent(): void
    {
        $a = $this->join('Persistent');$other = $this->join('Other', 'turbo-1');
        $service = self::getContainer()->get(DailyLeaderboard::class);
        self::assertSame(2, $service->tick());
        $first = $service->forUniverse($a);$id = $a->id;
        $this->ship($a, 50);$this->em->clear();
        $a = $this->em->find(PlayerUniverse::class, $id);
        self::assertSame($first['players'][0]['total_points'], $service->forUniverse($a)['players'][0]['total_points']);
        self::assertSame(2, $this->em->getRepository(LeaderboardSnapshot::class)->count());
        // Une interruption de plusieurs jours compare avec le dernier calcul réel, sans inventer de journées.
        $this->clock->sleep(3 * 86400);
        $after = $service->forUniverse($a);
        self::assertEquals($first['updated_at'], $after['comparison_at']);
        self::assertGreaterThan(0, $after['players'][0]['score_changes']['points']);
        $this->em->refresh($other = $this->em->find(PlayerUniverse::class, $other->id));
        self::assertSame(1, $service->tick());
        self::assertCount(1, $service->forUniverse($other)['players']);
    }

    public function testDailyRefreshIncludesFinishedOfflineQueues(): void
    {
        $a = $this->join('Offline');
        $service = self::getContainer()->get(DailyLeaderboard::class);
        $first = $service->forUniverse($a);
        $planet = $this->em->getRepository(Planet::class)->findOneBy(['owner'=>$a]);
        self::getContainer()->get(QueueService::class)->add($a, $planet, 'buildings', 'mine_metal', 1, str_repeat('e', 32));
        $this->clock->sleep(86402);
        $after = $service->forUniverse($a);
        self::assertGreaterThan($first['players'][0]['building_points'], $after['players'][0]['building_points']);
    }

    public function testGuildMembershipAndColonyRanksStayFrozenUntilTheNextDay(): void
    {
        $a = $this->join('GuildLeader');$b = $this->join('GuildRecruit');
        $this->ship($a, 10);$this->ship($b, 1);$guild = $this->guild($a, 'Crew');
        $service = self::getContainer()->get(DailyLeaderboard::class);
        $first = $service->forUniverse($a);
        $member = new GuildMember();$member->guild = $guild;$member->playerUniverse = $b;
        $this->em->persist($member);
        $available = self::getContainer()->get(\App\Repository\GameRepository::class)->planets($b->universe, null, true);
        foreach ($available as $planet) if (!$planet->npcPlanet) { $planet->owner = $b;break; }
        $this->em->flush();
        $frozen = $service->forUniverse($a);
        self::assertSame(1, $frozen['guilds'][0]['members']);
        self::assertSame(1, $this->row($frozen, $b)['planets']);
        $this->clock->sleep(2);
        $after = $service->forUniverse($a);
        self::assertSame(2, $after['guilds'][0]['members']);
        self::assertSame(2, $this->row($after, $b)['planets']);
        self::assertSame(1, $this->row($after, $b)['score_changes']['planets']);
        self::assertSame(1, $this->row($after, $b)['rank_changes']['planets']);
        self::assertGreaterThan($first['guilds'][0]['total_points'], $after['guilds'][0]['total_points']);
    }

    public function testBuildingAndResearchCategoriesHaveIndependentFrozenRanksAndChanges(): void
    {
        $a = $this->join('Builder');$b = $this->join('Researcher');
        $planet = $this->em->getRepository(Planet::class)->findOneBy(['owner'=>$a]);
        $building = new \App\Entity\PlanetBuilding();$building->planet=$planet;$building->slug='mine_metal';$building->level=8;
        $technology = new \App\Entity\PlayerTechnology();$technology->playerUniverse=$b;$technology->slug='weapons_engineering';$technology->level=4;
        $this->em->persist($building);$this->em->persist($technology);$this->em->flush();
        $service = self::getContainer()->get(DailyLeaderboard::class);
        $first = $service->forUniverse($a);
        self::assertSame(1, $this->row($first,$a)['ranks']['buildings']);
        self::assertSame(2, $this->row($first,$a)['ranks']['research']);
        self::assertSame(1, $this->row($first,$b)['ranks']['research']);
        $building->level=0;$technology->level=0;$this->em->flush();
        self::assertSame($this->row($first,$a)['building_points'], $this->row($service->forUniverse($a),$a)['building_points']);
        $this->clock->sleep(2);
        $next=$service->forUniverse($a);
        self::assertLessThan(0,$this->row($next,$a)['score_changes']['buildings']);
        self::assertLessThan(0,$this->row($next,$b)['score_changes']['research']);
        // Les scores égaux reprennent le départage existant par pseudonyme.
        self::assertSame(1,$this->row($next,$a)['ranks']['research']);
        self::assertSame(1,$this->row($next,$a)['rank_changes']['research']);
    }

    public function testOldSnapshotsGainNewCategoriesWithoutInventingHistoricalChanges(): void
    {
        $a=$this->join('Legacy');
        $service=self::getContainer()->get(DailyLeaderboard::class);
        $first=$service->forUniverse($a);
        $snapshot=$this->em->getRepository(LeaderboardSnapshot::class)->findOneBy(['universe'=>$a->universe]);
        foreach($snapshot->players as &$row) foreach(['buildings','research'] as $category) {
            unset($row['ranks'][$category],$row['score_changes'][$category],$row['rank_changes'][$category]);
        }
        unset($row);$this->em->flush();
        $legacy=$service->forUniverse($a);
        self::assertEquals($first['updated_at'],$legacy['updated_at']);
        self::assertSame(1,$legacy['players'][0]['ranks']['buildings']);
        self::assertNull($legacy['players'][0]['rank_changes']['research']);
        $this->clock->sleep(2);
        $next=$service->forUniverse($a);
        self::assertNull($next['players'][0]['rank_changes']['buildings']);
        self::assertSame(0,$next['players'][0]['score_changes']['buildings']);
        $this->clock->sleep(86400);
        self::assertSame(0,$service->forUniverse($a)['players'][0]['rank_changes']['research']);
    }

    public function testNextUpdateFollowsParisDaylightSavingAndNotFixedTwentyFourHours(): void
    {
        $a = $this->join('Summer');
        $service = self::getContainer()->get(DailyLeaderboard::class);
        foreach ([
            ['2027-03-27T23:00:00Z', '2027-03-28T22:00:00+00:00'],
            ['2027-10-30T22:00:00Z', '2027-10-31T23:00:00+00:00'],
        ] as [$now, $expected]) {
            $this->clock->modify($now);
            self::assertSame($expected, $service->forUniverse($a)['next_update']->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM));
        }
    }
}
