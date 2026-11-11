<?php
namespace App\Tests\Integration;

use App\Entity\{Player,Universe,Planet};
use App\Service\Player\RegistrationService;
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GuildRankTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée : test refusé.');
        }
        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();
    }

    private function player(string $name): Player
    {
        $data = new RegistrationData();
        $data->email = strtolower($name).'@example.test';
        $data->username = $name;
        $data->plainPassword = 'A-test-password-2026';

        return self::getContainer()->get(RegistrationService::class)->register($data);
    }

    private function join(Player $player, string $slug): void
    {
        $universe = $this->em->getRepository(Universe::class)->findOneBy(['slug' => $slug]);
        self::getContainer()->get(UniverseService::class)->select($player, $universe->id);
    }

    private function rankedGuild(): array
    {
        $leader = $this->player('GradeLeader');
        $delegate = $this->player('GradeDelegate');
        $outsider = $this->player('GradeOutsider');
        foreach ([$leader, $delegate, $outsider] as $player) $this->join($player, 'omega-1');
        $diplomacy = self::getContainer()->get(\App\Service\Player\DiplomacyService::class);
        $diplomacy->create($leader->currentPlayerUniverse, 'Grades');
        $guild = $this->em->getRepository(\App\Entity\Guild::class)->findOneBy(['leader' => $leader->currentPlayerUniverse]);
        $diplomacy->join($delegate->currentPlayerUniverse, $guild->id);
        $member = $this->em->getRepository(\App\Entity\GuildMember::class)->findOneBy(['playerUniverse' => $delegate->currentPlayerUniverse]);
        $ranks = self::getContainer()->get(\App\Service\Player\GuildRankService::class);
        $ranks->save($leader->currentPlayerUniverse, 0, 'Diplomate', 10, ['diplomacy.alliance'], 0);
        $rank = $this->em->getRepository(\App\Entity\GuildRank::class)->findOneBy(['guild' => $guild]);
        $ranks->assign($leader->currentPlayerUniverse, $member->id, $rank->id, 0);

        return [$leader->currentPlayerUniverse, $delegate->currentPlayerUniverse, $outsider->currentPlayerUniverse, $guild, $member, $rank, $ranks, $diplomacy];
    }

    public function testCustomRankDelegatesGuildAllianceWithoutControllingPersonalContracts(): void
    {
        [$leader, $delegate, $outsider, $guild, $member, $rank, $ranks, $diplomacy] = $this->rankedGuild();
        $diplomacy->create($outsider, 'Autre guilde');
        $otherGuild = $this->em->getRepository(\App\Entity\Guild::class)->findOneBy(['leader' => $outsider]);
        $diplomacy->relation($delegate, $otherGuild->id, 'allies');
        $diplomacy->relation($outsider, $guild->id, 'allies');
        $relations = self::getContainer()->get(\App\Service\Player\RelationResolver::class);
        self::assertTrue($relations->allied($delegate, $outsider));
        $diplomacy->alliance($delegate, $outsider->id, 'propose');
        $diplomacy->alliance($outsider, $delegate->id, 'accept');
        $ranks->assign($leader, $member->id, 0, $rank->id);
        self::assertSame('accepted', $relations->contract($delegate, $outsider)->status);
        $diplomacy->alliance($delegate, $outsider->id, 'break');
        self::assertNull($relations->contract($delegate, $outsider));
        $this->expectException(\DomainException::class);
        $diplomacy->relation($delegate, $otherGuild->id, 'neutral');
    }

    public function testRankPermissionRevocationPreventsNextDiplomaticAction(): void
    {
        [$leader, $delegate, $outsider, $guild, $member, $rank, $ranks, $diplomacy] = $this->rankedGuild();
        $diplomacy->create($outsider, 'Autre guilde');
        $otherGuild = $this->em->getRepository(\App\Entity\Guild::class)->findOneBy(['leader' => $outsider]);
        $ranks->save($leader, $rank->id, 'Diplomate', 10, [], $rank->version);
        $this->expectException(\DomainException::class);
        $diplomacy->relation($delegate, $otherGuild->id, 'allies');
    }

    public function testDelegateCannotEditRankPermissions(): void
    {
        [$leader, $delegate, $outsider, $guild, $member, $rank, $ranks] = $this->rankedGuild();
        $this->expectException(\DomainException::class);
        $ranks->save($delegate, $rank->id, 'Diplomate', 10, ['diplomacy.hostility'], $rank->version);
    }

    public function testRankEditRejectsStaleVersion(): void
    {
        [$leader, $delegate, $outsider, $guild, $member, $rank, $ranks] = $this->rankedGuild();
        $version = $rank->version;
        $ranks->save($leader, $rank->id, 'Diplomate confirmé', 10, [], $version);
        self::assertGreaterThan($version, $rank->version);
        $this->expectException(\DomainException::class);
        $ranks->save($leader, $rank->id, 'Ancien formulaire', 10, [], $version);
    }

    public function testRankAssignmentRejectsAnotherGuildRank(): void
    {
        [$leader, $delegate, $outsider, $guild, $member, $rank, $ranks, $diplomacy] = $this->rankedGuild();
        $diplomacy->create($outsider, 'Autre guilde');
        $ranks->save($outsider, 0, 'Étranger', 1, [], 0);
        $foreignRank = $this->em->getRepository(\App\Entity\GuildRank::class)->findOneBy(['name' => 'Étranger']);
        $this->expectException(\DomainException::class);
        $ranks->assign($leader, $member->id, $foreignRank->id, $rank->id);
    }

    public function testRankAssignmentRejectsStaleMemberForm(): void
    {
        [$leader, $delegate, $outsider, $guild, $member, $rank, $ranks] = $this->rankedGuild();
        $this->expectException(\DomainException::class);
        $ranks->assign($leader, $member->id, 0, 0);
    }

    public function testRankRejectsUnknownPermissions(): void
    {
        [$leader, $delegate, $outsider, $guild, $member, $rank, $ranks] = $this->rankedGuild();
        $this->expectException(\DomainException::class);
        $ranks->save($leader, 0, 'Administrateur', 1, ['ROLE_ADMIN'], 0);
    }

    public function testDelegateCanAssignLowerRankButCannotChangeOwnRank(): void
    {
        [$leader, $delegate, $outsider, $guild, $member, $rank, $ranks, $diplomacy] = $this->rankedGuild();
        $ranks->save($leader, $rank->id, 'Officier', 10, ['members.assign_rank', 'diplomacy.alliance'], $rank->version);
        $ranks->save($leader, 0, 'Diplomate junior', 20, ['diplomacy.alliance'], 0);
        $junior = $this->em->getRepository(\App\Entity\GuildRank::class)->findOneBy(['name' => 'Diplomate junior']);
        $diplomacy->join($outsider, $guild->id);
        $target = $this->em->getRepository(\App\Entity\GuildMember::class)->findOneBy(['playerUniverse' => $outsider]);
        $ranks->assign($delegate, $target->id, $junior->id, 0);
        self::assertSame($junior->id, $target->rank->id);
        $this->expectException(\DomainException::class);
        $ranks->assign($delegate, $member->id, $junior->id, $rank->id);
    }

    public function testDelegateCannotGrantAPermissionTheyDoNotHave(): void
    {
        [$leader, $delegate, $outsider, $guild, $member, $rank, $ranks, $diplomacy] = $this->rankedGuild();
        $ranks->save($leader, $rank->id, 'Officier', 10, ['members.assign_rank'], $rank->version);
        $ranks->save($leader, 0, 'Hostilité', 20, ['diplomacy.hostility'], 0);
        $hostile = $this->em->getRepository(\App\Entity\GuildRank::class)->findOneBy(['name' => 'Hostilité']);
        $diplomacy->join($outsider, $guild->id);
        $target = $this->em->getRepository(\App\Entity\GuildMember::class)->findOneBy(['playerUniverse' => $outsider]);
        $this->expectException(\DomainException::class);
        $ranks->assign($delegate, $target->id, $hostile->id, 0);
    }

    public function testAllianceDelegateCannotClearHostilityWithoutNeutralityPermission(): void
    {
        [$leader, $delegate, $outsider, $guild, $member, $rank, $ranks, $diplomacy] = $this->rankedGuild();
        $diplomacy->create($outsider, 'Autre guilde');
        $otherGuild = $this->em->getRepository(\App\Entity\Guild::class)->findOneBy(['leader' => $outsider]);
        $diplomacy->relation($outsider, $guild->id, 'enemies');
        $this->expectException(\DomainException::class);
        $diplomacy->relation($delegate, $otherGuild->id, 'allies');
    }
}
