<?php
namespace App\Tests\Integration;

use App\Entity\{Fleet,Guild,GuildRelation,GuildTreatyEvent,Universe};
use App\Service\Combat\CombatEventService;
use App\Service\Player\{DiplomacyService,RegistrationService,RelationResolver};
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GuildTreatyTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private DiplomacyService $diplomacy;
    private RelationResolver $relations;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em=self::getContainer()->get(EntityManagerInterface::class);
        $params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test' || ($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base non isolée : test refusé.');
        $schema=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);$schema->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();
        $this->diplomacy=self::getContainer()->get(DiplomacyService::class);
        $this->relations=self::getContainer()->get(RelationResolver::class);
    }

    private function pair(): array
    {
        $players=[];
        foreach(['TreatyAlpha','TreatyBeta'] as $name) {
            $data=new RegistrationData();$data->email=strtolower($name).'@example.test';$data->username=$name;$data->plainPassword='Treaty-tests-2026!';
            $player=self::getContainer()->get(RegistrationService::class)->register($data);
            $universe=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'omega-1']);
            self::getContainer()->get(UniverseService::class)->select($player,$universe->id);
            $member=$player->currentPlayerUniverse;
            $member->joinedAt=self::getContainer()->get(\Psr\Clock\ClockInterface::class)->now()->modify('-8 days');
            $this->em->flush();
            $this->diplomacy->create($member,$name,$name==='TreatyAlpha'?'TA':'TB');
            $players[]=$member;
        }
        return [$players[0],$players[1],$this->relations->guild($players[0]),$this->relations->guild($players[1])];
    }

    private function relation(): GuildRelation
    {
        return $this->em->getRepository(GuildRelation::class)->findOneBy([]);
    }

    public static function treatyTypes(): array
    {
        return [['allies',true,true],['non_aggression',false,false],['defense',false,true]];
    }

    #[DataProvider('treatyTypes')]
    public function testTreatyRequiresTwoSignaturesAndHasDistinctRights(string $type,bool $allied,bool $defense): void
    {
        [$a,$b,$ga,$gb]=$this->pair();
        self::assertTrue(self::getContainer()->get(CombatEventService::class)->canAttack($a,$this->em->getRepository(\App\Entity\Planet::class)->findOneBy(['owner'=>$b])));
        $this->diplomacy->relation($a,$gb->id,$type,0,0,'propose');
        self::assertFalse($this->relations->allied($a,$b));
        self::assertFalse($this->relations->nonAggression($a,$b));
        $relation=$this->relation();
        $this->diplomacy->relation($b,$ga->id,$type,$relation->id,$relation->version,'accept');
        self::assertSame($type,$relation->status);
        self::assertNull($relation->proposal);
        self::assertSame($allied,$this->relations->allied($a,$b));
        self::assertSame($defense,$this->relations->canStationDefenders($a,$b));
        self::assertSame(!$allied,$this->relations->nonAggression($a,$b));
        $combat=self::getContainer()->get(CombatEventService::class);
        self::assertSame($defense,$combat->canOrbit($a,$this->em->getRepository(\App\Entity\Planet::class)->findOneBy(['owner'=>$b])));
        self::assertFalse($combat->canAttack($a,$this->em->getRepository(\App\Entity\Planet::class)->findOneBy(['owner'=>$b])));
        self::assertFalse($combat->canConquer($a,$this->em->getRepository(\App\Entity\Planet::class)->findOneBy(['owner'=>$b])));
        self::assertFalse($combat->orbitAutoAccepted($a,$this->em->getRepository(\App\Entity\Planet::class)->findOneBy(['owner'=>$b])));
        self::assertCount(2,$this->em->getRepository(GuildTreatyEvent::class)->findAll());
    }

    public function testAcceptanceCannotChangeTheDisplayedTerms(): void
    {
        [$a,$b,$ga,$gb]=$this->pair();
        $this->diplomacy->relation($a,$gb->id,'non_aggression');
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('proposition affichée');
        $this->diplomacy->relation($b,$ga->id,'defense',null,null,'accept');
    }

    public function testDissolutionPreservesTheTreatyHistory(): void
    {
        [$a,$b,$ga,$gb]=$this->pair();
        $this->diplomacy->relation($a,$gb->id,'defense');$this->diplomacy->relation($b,$ga->id,'defense');
        $this->diplomacy->disband($a);
        self::assertNull($this->relations->guild($a));
        self::assertCount(0,$this->em->getRepository(GuildRelation::class)->findAll());
        self::assertCount(2,$this->em->getRepository(GuildTreatyEvent::class)->findAll());
    }

    public function testBreakingATreatyAlsoCancelsItsReplacementProposal(): void
    {
        [$a,$b,$ga,$gb]=$this->pair();
        $this->diplomacy->relation($a,$gb->id,'allies');$this->diplomacy->relation($b,$ga->id,'allies');
        $this->diplomacy->relation($a,$gb->id,'defense',null,null,'propose');
        $this->diplomacy->relation($b,$ga->id,'neutral',null,null,'break');
        self::assertSame('neutral',$this->relation()->status);
        self::assertNull($this->relation()->proposal);
    }

    public function testProposerCannotSignTheirOwnTreaty(): void
    {
        [$a,$b,$ga,$gb]=$this->pair();
        $this->diplomacy->relation($a,$gb->id,'non_aggression');
        $this->expectException(\DomainException::class);
        $this->diplomacy->relation($a,$gb->id,'non_aggression',null,null,'accept');
    }

    public function testPeacePreservesHostilityUntilOtherGuildAccepts(): void
    {
        [$a,$b,$ga,$gb]=$this->pair();
        $this->diplomacy->relation($a,$gb->id,'enemies');
        $this->diplomacy->relation($a,$gb->id,'neutral',null,null,'propose');
        self::assertTrue($this->relations->enemy($a,$b));
        self::assertSame('enemies',$this->relation()->status);
        $this->diplomacy->relation($b,$ga->id,'neutral',null,null,'accept');
        self::assertFalse($this->relations->enemy($a,$b));
        self::assertSame('neutral',$this->relation()->status);
        self::assertFalse($this->relations->allied($a,$b));
    }

    public function testPeaceCannotBeBrokenUnilaterally(): void
    {
        [$a,$b,$ga,$gb]=$this->pair();
        $this->diplomacy->relation($a,$gb->id,'enemies');
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('La paix exige');
        $this->diplomacy->relation($a,$gb->id,'neutral',null,null,'break');
    }

    public function testDecliningPeaceKeepsHostility(): void
    {
        [$a,$b,$ga,$gb]=$this->pair();
        $this->diplomacy->relation($a,$gb->id,'enemies');
        $this->diplomacy->relation($a,$gb->id,'neutral',null,null,'propose');
        $this->diplomacy->relation($b,$ga->id,'neutral',null,null,'decline');
        self::assertTrue($this->relations->enemy($a,$b));
        self::assertNull($this->relation()->proposal);
    }

    public function testProposedReplacementDoesNotRemoveCurrentAlliance(): void
    {
        [$a,$b,$ga,$gb]=$this->pair();
        $this->diplomacy->relation($a,$gb->id,'allies');$this->diplomacy->relation($b,$ga->id,'allies');
        $this->diplomacy->relation($a,$gb->id,'non_aggression',null,null,'propose');
        self::assertTrue($this->relations->allied($a,$b));
        $this->diplomacy->relation($b,$ga->id,'non_aggression',null,null,'accept');
        self::assertFalse($this->relations->allied($a,$b));
        self::assertTrue($this->relations->nonAggression($a,$b));
    }

    public function testNewPactBlocksAnAttackAlreadyInFlightAtArrival(): void
    {
        [$a,$b,$ga,$gb]=$this->pair();
        $fleet=new Fleet();$fleet->playerUniverse=$a;$fleet->destination=$this->em->getRepository(\App\Entity\Planet::class)->findOneBy(['owner'=>$b]);$fleet->mission='attack';
        self::assertTrue(self::getContainer()->get(CombatEventService::class)->canAttack($a,$fleet->destination));
        $this->diplomacy->relation($a,$gb->id,'non_aggression');$this->diplomacy->relation($b,$ga->id,'non_aggression');
        self::assertFalse(self::getContainer()->get(CombatEventService::class)->joinAttack($fleet));
    }

    public function testTreatyCanBeBrokenWithoutEndingPersonalRivalry(): void
    {
        [$a,$b,$ga,$gb]=$this->pair();
        $this->diplomacy->alliance($a,$b->id,'rival');
        $this->diplomacy->relation($a,$gb->id,'defense');$this->diplomacy->relation($b,$ga->id,'defense');
        $this->diplomacy->relation($a,$gb->id,'neutral',null,null,'break');
        self::assertSame('neutral',$this->relation()->status);
        self::assertSame('enemies',$this->relations->contract($a,$b)->status);
        self::assertTrue($this->relations->enemy($a,$b));
    }
}
