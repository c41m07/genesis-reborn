<?php
namespace App\Tests\Integration;

use App\Entity\{AllianceContract,Universe};
use App\Repository\GameRepository;
use App\Service\Player\{DiplomacyService,EmpireConversionService,RegistrationService,RelationResolver};
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PersonalDiplomacyTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private DiplomacyService $diplomacy;
    private RelationResolver $relations;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée : test refusé.');
        }
        $schema = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();
        $this->diplomacy = self::getContainer()->get(DiplomacyService::class);
        $this->relations = self::getContainer()->get(RelationResolver::class);
    }

    private function pair(string $slug = 'turbo-1'): array
    {
        $players = [];
        foreach (['PersonalAlpha', 'PersonalBeta'] as $name) {
            $data = new RegistrationData();
            $data->email = strtolower($name).'@example.test';
            $data->username = $name;
            $data->plainPassword = 'Personal-tests-2026!';
            $player = self::getContainer()->get(RegistrationService::class)->register($data);
            $universe = $this->em->getRepository(Universe::class)->findOneBy(['slug' => $slug]);
            self::getContainer()->get(UniverseService::class)->select($player, $universe->id);
            $players[] = $player->currentPlayerUniverse;
        }

        return $players;
    }

    public function testRivalryAndMutualPeaceWorkWithoutGuilds(): void
    {
        [$actor, $target] = $this->pair();
        $this->diplomacy->alliance($actor, $target->id, 'propose');
        $this->diplomacy->alliance($target, $actor->id, 'accept');
        self::assertTrue($this->relations->allied($actor, $target));
        $contractId = $this->relations->contract($actor, $target)->id;
        $this->diplomacy->alliance($actor, $target->id, 'rival');
        self::assertTrue($this->relations->enemy($actor, $target));
        self::assertTrue($this->relations->enemy($target, $actor));
        self::assertFalse($this->relations->allied($actor, $target));
        $this->diplomacy->alliance($target, $actor->id, 'peace_propose');
        self::assertTrue($this->relations->enemy($actor, $target));
        self::assertSame('peace_pending', $this->relations->contract($actor, $target)->status);
        self::assertSame(1, self::getContainer()->get(GameRepository::class)->forgottenEmpirePreviewInventory($actor)['alliances']);
        $this->diplomacy->alliance($actor, $target->id, 'peace_accept');
        self::assertFalse($this->relations->enemy($actor, $target));
        self::assertFalse($this->relations->allied($actor, $target));
        self::assertSame('ended', $this->relations->contract($actor, $target)->status);
        $this->diplomacy->alliance($actor, $target->id, 'propose');
        $this->diplomacy->alliance($target, $actor->id, 'accept');
        self::assertTrue($this->relations->allied($actor, $target));
        self::assertSame($contractId, $this->relations->contract($actor, $target)->id);
        self::assertSame(1, $this->em->getRepository(AllianceContract::class)->count());
    }

    public static function forbiddenTransitions(): array
    {
        return [
            'rivalry cannot be broken' => ['enemies', 'break', false],
            'peace cannot be bypassed' => ['peace_pending', 'break', false],
            'own peace cannot be accepted' => ['peace_pending', 'peace_accept', true],
            'peace needs an offer' => ['enemies', 'peace_accept', false],
            'alliance cannot bypass rivalry' => ['enemies', 'propose', false],
            'accept cannot bypass rivalry' => ['enemies', 'accept', false],
            'ended is not a proposal' => ['ended', 'accept', false],
            'peace needs rivalry' => ['accepted', 'peace_propose', false],
            'unknown action' => ['enemies', 'delete', false],
        ];
    }

    #[DataProvider('forbiddenTransitions')]
    public function testIllegalTransitionsAreRefused(string $status, string $action, bool $proposerActs): void
    {
        [$actor, $target] = $this->pair();
        $contract = new AllianceContract();
        [$contract->playerA, $contract->playerB] = $actor->id < $target->id ? [$actor, $target] : [$target, $actor];
        $contract->proposer = $actor;
        $contract->status = $status;
        $this->em->persist($contract);
        $this->em->flush();
        $this->expectException(\DomainException::class);
        $this->diplomacy->alliance($proposerActs ? $actor : $target, $proposerActs ? $target->id : $actor->id, $action);
    }

    public function testPeaceCanBeWithdrawnOrRefusedWithoutEndingRivalry(): void
    {
        [$actor, $target] = $this->pair();
        $this->diplomacy->alliance($actor, $target->id, 'rival');
        $this->diplomacy->alliance($actor, $target->id, 'peace_propose');
        $this->diplomacy->alliance($actor, $target->id, 'peace_reject');
        self::assertTrue($this->relations->enemy($actor, $target));
        $this->diplomacy->alliance($actor, $target->id, 'peace_propose');
        $this->diplomacy->alliance($target, $actor->id, 'peace_reject');
        self::assertSame('enemies', $this->relations->contract($actor, $target)->status);
        self::assertTrue($this->relations->enemy($target, $actor));
    }

    public function testSameGuildOverridesPersonalRivalryUntilDeparture(): void
    {
        [$actor, $target] = $this->pair('omega-1');
        $this->diplomacy->create($actor, 'Même guilde', 'MEME');
        $guild = $this->relations->guild($actor);
        $this->diplomacy->join($target, $guild->id);
        $this->diplomacy->alliance($actor, $target->id, 'rival');
        self::assertTrue($this->relations->allied($actor, $target));
        self::assertFalse($this->relations->enemy($actor, $target));
        $this->diplomacy->leave($target);
        self::assertFalse($this->relations->allied($actor, $target));
        self::assertTrue($this->relations->enemy($actor, $target));
    }

    public function testAlliedGuildsOverrideRivalryAndGuildHostilityDoesNotEraseIt(): void
    {
        [$actor, $target] = $this->pair('omega-1');
        $this->diplomacy->create($actor, 'Guilde Alpha', 'ALPHA');
        $this->diplomacy->create($target, 'Guilde Beta', 'BETA');
        $actorGuild = $this->relations->guild($actor);
        $targetGuild = $this->relations->guild($target);
        $this->diplomacy->relation($actor, $targetGuild->id, 'allies');
        $this->diplomacy->relation($target, $actorGuild->id, 'allies');
        $this->diplomacy->alliance($actor, $target->id, 'rival');
        self::assertTrue($this->relations->allied($actor, $target));
        self::assertFalse($this->relations->enemy($actor, $target));
        $this->diplomacy->relation($actor, $targetGuild->id, 'enemies');
        self::assertSame('enemies', $this->relations->contract($actor, $target)->status);
        $this->diplomacy->alliance($actor, $target->id, 'peace_propose');
        $this->diplomacy->alliance($target, $actor->id, 'peace_accept');
        self::assertTrue($this->relations->enemy($actor, $target));
        $this->diplomacy->relation($actor, $targetGuild->id, 'neutral');
        self::assertTrue($this->relations->enemy($actor, $target));
        $this->diplomacy->relation($target, $actorGuild->id, 'neutral');
        self::assertFalse($this->relations->enemy($actor, $target));
    }

    public function testConversionEndsRivalryAndPendingPeace(): void
    {
        [$actor, $target] = $this->pair();
        $this->diplomacy->alliance($actor, $target->id, 'rival');
        $this->diplomacy->alliance($actor, $target->id, 'peace_propose');
        $result = self::getContainer()->get(EmpireConversionService::class)->convertForAccountDeletion($actor);
        self::assertSame('converted', $result['status']);
        self::assertSame('ended', $this->em->getRepository(AllianceContract::class)->findOneBy(['playerA' => $actor])->status);
        self::assertFalse($this->relations->enemy($actor, $target));
    }
}
