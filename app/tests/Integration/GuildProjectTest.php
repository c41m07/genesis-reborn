<?php
namespace App\Tests\Integration;

use App\Entity\{GuildProject,GuildProjectContribution,OutboxMessage,Planet,PlanetResource,PlayerBoost,Universe};
use App\Message\GuildProjectRewardsDue;
use App\MessageHandler\GuildProjectRewardsDueHandler;
use App\Service\Boost\BoostGrantService;
use App\Service\Notification\OutboxRelay;
use App\Service\Player\{DiplomacyService,GuildProjectDefinitionService,GuildProjectService,RegistrationService,RelationResolver};
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GuildProjectTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private GuildProjectService $projects;
    private DiplomacyService $diplomacy;

    protected function setUp(): void
    {
        self::bootKernel();$this->em=self::getContainer()->get(EntityManagerInterface::class);
        $params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test' || ($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base non isolée : test refusé.');
        $schema=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);$schema->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();
        $this->projects=self::getContainer()->get(GuildProjectService::class);
        $this->diplomacy=self::getContainer()->get(DiplomacyService::class);
    }

    private function player(string $name): \App\Entity\PlayerUniverse
    {
        $data=new RegistrationData();$data->email=strtolower($name).'@example.test';$data->username=$name;$data->plainPassword='Project-tests-2026!';
        $player=self::getContainer()->get(RegistrationService::class)->register($data);
        $universe=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'omega-1']);
        self::getContainer()->get(UniverseService::class)->select($player,$universe->id);
        return $player->currentPlayerUniverse;
    }

    private function draft(int $universeId): array
    {
        return ['universe_id'=>$universeId,'name'=>'Station commune','description'=>'Deux paliers.', 'enabled'=>true,
            'stages'=>[['name'=>'Structure','costs'=>['metal'=>10]],['name'=>'Énergie','costs'=>['hydrogen'=>4]]],
            'minimums'=>['metal'=>2,'hydrogen'=>1]];
    }

    private function fixture(): array
    {
        $a=$this->player('ProjectAlpha');$b=$this->player('ProjectBeta');
        $a->player->roles=['ROLE_ADMIN'];$this->em->flush();
        $this->diplomacy->create($a,'Bâtisseurs');
        $guild=self::getContainer()->get(RelationResolver::class)->guild($a);
        $this->diplomacy->join($b,$guild->id);
        $definition=self::getContainer()->get(GuildProjectDefinitionService::class)->save($a->player,0,$this->draft($a->universe->id),0);
        $project=$this->projects->start($a,$definition->id,$definition->version);
        return [$a,$b,$definition,$project];
    }

    private function planet(\App\Entity\PlayerUniverse $player): Planet
    {
        return $this->em->getRepository(Planet::class)->findOneBy(['owner'=>$player]);
    }

    private function donate(\App\Entity\PlayerUniverse $player,GuildProject $project,array $costs): bool
    {
        return $this->projects->contribute($player,$project->id,$this->planet($player)->id,$costs,$project->version);
    }

    private function finish(array $fixture): void
    {
        [$a,$b,$definition,$project]=$fixture;
        $this->donate($a,$project,['metal'=>8]);$this->donate($b,$project,['metal'=>2]);
        $this->donate($a,$project,['hydrogen'=>3]);$this->donate($b,$project,['hydrogen'=>1]);
    }

    private function deliver(GuildProject $project): void
    {
        (self::getContainer()->get(GuildProjectRewardsDueHandler::class))(new GuildProjectRewardsDue($project->id,$project->rewardToken));
    }

    private function rewards(\App\Entity\PlayerUniverse $player): array
    {
        return $this->em->getRepository(PlayerBoost::class)->findBy(['playerUniverse'=>$player,'sourceType'=>'guild_project']);
    }

    public function testNonAdminCannotChangeProjectCosts(): void
    {
        [$a,$b,$definition,$project]=$this->fixture();
        $this->expectException(\DomainException::class);$this->expectExceptionMessage('Droits administrateur');
        self::getContainer()->get(GuildProjectDefinitionService::class)->save($b->player,$definition->id,$this->draft($a->universe->id),$definition->version);
    }

    public function testStaleAdministrativeFormCannotOverwriteNewCosts(): void
    {
        [$a,$b,$definition,$project]=$this->fixture();$oldVersion=$definition->version;
        $draft=$this->draft($a->universe->id);$draft['stages'][0]['costs']['metal']=20;
        $service=self::getContainer()->get(GuildProjectDefinitionService::class);
        $service->save($a->player,$definition->id,$draft,$oldVersion);
        $this->expectException(\DomainException::class);$this->expectExceptionMessage('Le modèle a changé');
        $service->save($a->player,$definition->id,$this->draft($a->universe->id),$oldVersion);
    }

    public function testAPlayerInAnotherGuildCannotFundTheProject(): void
    {
        [$a,$b,$definition,$project]=$this->fixture();$this->diplomacy->leave($b);$this->diplomacy->create($b,'Autre guilde');
        $this->expectException(\DomainException::class);$this->expectExceptionMessage('inaccessible à votre guilde');
        $this->donate($b,$project,['metal'=>1]);
    }

    public function testWrongRewardTokenCannotGrantAnything(): void
    {
        $fixture=$this->fixture();[$a,$b,$definition,$project]=$fixture;$this->finish($fixture);
        (self::getContainer()->get(GuildProjectRewardsDueHandler::class))(new GuildProjectRewardsDue($project->id,'forged-token'));
        self::assertSame([],$this->rewards($a));self::assertNull($project->rewardsDeliveredAt);
        $this->deliver($project);self::assertCount(1,$this->rewards($a));
    }

    public function testPartiallyDeliveredRewardsCanResumeWithoutDuplication(): void
    {
        $fixture=$this->fixture();[$a,$b,$definition,$project]=$fixture;$this->finish($fixture);
        self::getContainer()->get(BoostGrantService::class)->awardGuildProject($a,$definition->id);
        $this->deliver($project);
        self::assertCount(1,$this->rewards($a));self::assertCount(1,$this->rewards($b));
        self::assertNotNull($project->rewardsDeliveredAt);
    }

    public function testDisablingAModelKeepsExistingProgressButBlocksNewLaunches(): void
    {
        [$a,$b,$definition,$project]=$this->fixture();$draft=$this->draft($a->universe->id);$draft['enabled']=false;
        self::getContainer()->get(GuildProjectDefinitionService::class)->save($a->player,$definition->id,$draft,$definition->version);
        $this->donate($a,$project,['metal'=>10]);self::assertSame(1,$project->stageIndex);
        $this->diplomacy->leave($b);$this->diplomacy->create($b,'Autre équipe');
        $this->expectException(\DomainException::class);$this->expectExceptionMessage('plus disponible');
        $this->projects->start($b,$definition->id,$definition->version);
    }

    public function testInsufficientStocksLeaveNoProgressOrContribution(): void
    {
        [$a,$b,$definition,$project]=$this->fixture();$planet=$this->planet($a);
        $resource=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$planet,'resourceType'=>'metal']);
        $resource->currentStock=0;$this->em->flush();$connection=$this->em->getConnection();
        try { $this->donate($a,$project,['metal'=>1]);self::fail('Le don sans stock doit être refusé.'); }
        catch(\DomainException $error) { self::assertStringContainsString('insuffisantes',$error->getMessage()); }
        self::assertSame(0,(int)$connection->fetchOne('SELECT COUNT(*) FROM guild_project_contribution WHERE project_id=?',[$project->id]));
        self::assertSame('[]',$connection->fetchOne('SELECT progress FROM guild_project WHERE id=?',[$project->id]));
    }

    public function testProjectLaunchPermissionCanBeDelegatedAndRevoked(): void
    {
        [$a,$b,$definition,$project]=$this->fixture();$guild=self::getContainer()->get(RelationResolver::class)->guild($a);
        $ranks=self::getContainer()->get(\App\Service\Player\GuildRankService::class);
        $ranks->save($a,0,'Coordinateur',10,['projects.start'],0);
        $rank=$this->em->getRepository(\App\Entity\GuildRank::class)->findOneBy(['guild'=>$guild]);
        $member=$this->em->getRepository(\App\Entity\GuildMember::class)->findOneBy(['playerUniverse'=>$b]);
        $ranks->assign($a,$member->id,$rank->id,0);
        $draft=$this->draft($a->universe->id);$draft['name']='Projet délégué';
        $second=self::getContainer()->get(GuildProjectDefinitionService::class)->save($a->player,0,$draft,0);
        self::assertSame($guild->id,$this->projects->start($b,$second->id,$second->version)->guildId);
        $ranks->save($a,$rank->id,'Coordinateur',10,[],$rank->version);
        $this->expectException(\DomainException::class);$this->expectExceptionMessage('Permission de lancement');
        $this->projects->start($b,$definition->id,$definition->version);
    }

    public function testDonationConsumesResourcesAndCompletesOnlyTheCurrentStage(): void
    {
        [$a,$b,$definition,$project]=$this->fixture();
        $resource=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$this->planet($a),'resourceType'=>'metal']);
        $before=$resource->currentStock;
        self::assertFalse($this->donate($a,$project,['metal'=>10]));
        self::assertEquals($before-10,$resource->currentStock);
        self::assertSame(1,$project->stageIndex);self::assertSame([],$project->progress);
        self::assertSame('active',$project->status);self::assertSame([],$this->rewards($a));
    }

    public function testCompletedProjectQueuesAndGrantsExactlyOneBoostPerEligibleMember(): void
    {
        $fixture=$this->fixture();[$a,$b,$definition,$project]=$fixture;$this->finish($fixture);
        self::assertSame('completed',$project->status);self::assertSame([$a->id,$b->id],$project->eligibleIds);
        self::assertSame([],$this->rewards($a));
        self::assertCount(1,$this->em->getRepository(OutboxMessage::class)->findBy(['kind'=>'guild_project']));
        self::getContainer()->get(OutboxRelay::class)->run();
        $messages=array_filter(self::getContainer()->get('messenger.transport.async')->getSent(),static fn($envelope)=>$envelope->getMessage() instanceof GuildProjectRewardsDue);
        self::assertCount(1,$messages);
        $project=$this->em->find(GuildProject::class,$project->id);
        $this->deliver($project);$this->deliver($project);
        self::assertCount(1,$this->rewards($a));self::assertCount(1,$this->rewards($b));
        self::assertNotNull($project->rewardsDeliveredAt);
    }

    public function testOldDonationFormCannotDebitResourcesTwice(): void
    {
        [$a,$b,$definition,$project]=$this->fixture();$oldVersion=$project->version;$planet=$this->planet($a);
        $this->donate($a,$project,['metal'=>2]);
        $connection=$this->em->getConnection();$before=$connection->fetchOne('SELECT current_stock FROM planet_resource WHERE planet_id=? AND resource_type=?',[$planet->id,'metal']);
        try { $this->projects->contribute($a,$project->id,$planet->id,['metal'=>2],$oldVersion);self::fail('Le formulaire périmé doit être refusé.'); }
        catch(\DomainException $error) { self::assertStringContainsString('a progressé',$error->getMessage()); }
        self::assertEquals($before,$connection->fetchOne('SELECT current_stock FROM planet_resource WHERE planet_id=? AND resource_type=?',[$planet->id,'metal']));
    }

    public function testAnotherMembersColonyCannotPayForTheDonation(): void
    {
        [$a,$b,$definition,$project]=$this->fixture();
        $this->expectException(\DomainException::class);$this->expectExceptionMessage('ne vous appartient pas');
        $this->projects->contribute($a,$project->id,$this->planet($b)->id,['metal'=>1],$project->version);
    }

    public function testDonationCannotExceedTheStageCost(): void
    {
        [$a,$b,$definition,$project]=$this->fixture();
        $this->expectException(\DomainException::class);$this->expectExceptionMessage('dépasse les besoins');
        $this->donate($a,$project,['metal'=>11]);
    }

    public function testOrdinaryMemberCannotLaunchAProject(): void
    {
        [$a,$b,$definition,$project]=$this->fixture();
        $this->expectException(\DomainException::class);$this->expectExceptionMessage('Permission de lancement');
        $this->projects->start($b,$definition->id,$definition->version);
    }

    public function testAdministrativeEditDoesNotChangeAnExistingProject(): void
    {
        [$a,$b,$definition,$project]=$this->fixture();$draft=$this->draft($a->universe->id);$draft['stages'][0]['costs']['metal']=100;
        self::getContainer()->get(GuildProjectDefinitionService::class)->save($a->player,$definition->id,$draft,$definition->version);
        self::assertSame(10,$project->snapshot['stages'][0]['costs']['metal']);
        $this->donate($a,$project,['metal'=>10]);self::assertSame(1,$project->stageIndex);
    }

    public function testDepartedContributorKeepsTheirDonationHistoryButNoFutureReward(): void
    {
        [$a,$b,$definition,$project]=$this->fixture();
        $this->donate($a,$project,['metal'=>8]);$this->donate($b,$project,['metal'=>2]);$this->donate($b,$project,['hydrogen'=>1]);
        $resource=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$this->planet($b),'resourceType'=>'hydrogen']);$afterDonation=$resource->currentStock;
        $this->diplomacy->leave($b);$this->donate($a,$project,['hydrogen'=>3]);$this->deliver($project);
        self::assertSame([$a->id],$project->eligibleIds);self::assertCount(0,$this->rewards($b));
        self::assertEquals($afterDonation,$resource->currentStock);
        self::assertCount(2,$this->em->getRepository(GuildProjectContribution::class)->findBy(['project'=>$project]));
    }

    public function testLeavingAfterCompletionKeepsTheAlreadyEarnedReward(): void
    {
        $fixture=$this->fixture();[$a,$b,$definition,$project]=$fixture;$this->finish($fixture);
        $this->diplomacy->leave($b);$this->deliver($project);
        self::assertCount(1,$this->rewards($b));
    }

    public function testContributorBelowOneOfTheMinimumsGetsNoBoost(): void
    {
        [$a,$b,$definition,$project]=$this->fixture();
        $this->donate($a,$project,['metal'=>9]);$this->donate($b,$project,['metal'=>1]);
        $this->donate($b,$project,['hydrogen'=>1]);$this->donate($a,$project,['hydrogen'=>3]);$this->deliver($project);
        self::assertCount(1,$this->rewards($a));self::assertCount(0,$this->rewards($b));
    }

    public function testFullInventoryKeepsTheProjectRewardPending(): void
    {
        $fixture=$this->fixture();[$a,$b,$definition,$project]=$fixture;$boosts=self::getContainer()->get(BoostGrantService::class);
        for($i=0;$i<$boosts->inventoryCapacity();++$i) $boosts->awardEvent($a,'project-stock-'.$i);
        $this->finish($fixture);$this->deliver($project);
        self::assertCount($boosts->inventoryCapacity(),$boosts->inventory($a));
        self::assertSame('pending',$this->rewards($a)[0]->status);self::assertSame(1,$boosts->pendingCount($a));
    }

    public function testChangingGuildCannotEarnTheSameProjectBoostAgain(): void
    {
        $fixture=$this->fixture();[$a,$b,$definition,$project]=$fixture;$this->finish($fixture);$this->deliver($project);
        $this->diplomacy->leave($b);$this->diplomacy->create($b,'Nouvelle équipe');
        $other=$this->projects->start($b,$definition->id,$definition->version);
        $this->donate($b,$other,['metal'=>10]);$this->donate($b,$other,['hydrogen'=>4]);$this->deliver($other);
        self::assertCount(1,$this->rewards($b));self::assertNotNull($other->rewardsDeliveredAt);
    }

    public function testCreatingAnotherEmpireCannotEarnTheProjectRewardAgain(): void
    {
        $fixture=$this->fixture();[$a,$b,$definition,$project]=$fixture;$this->finish($fixture);$this->deliver($project);
        $account=$b->player;$universeId=$b->universe->id;
        $this->diplomacy->leave($b);
        $b->detachPlayer(self::getContainer()->get(\Psr\Clock\ClockInterface::class)->now());$this->em->flush();
        self::getContainer()->get(UniverseService::class)->select($account,$universeId);
        $newEmpire=$account->currentPlayerUniverse;
        self::assertNotSame($b->id,$newEmpire->id);
        $boosts=self::getContainer()->get(BoostGrantService::class);
        self::assertTrue($boosts->hasGuildProjectReward($newEmpire,$definition->id));
        self::assertFalse($boosts->awardGuildProject($newEmpire,$definition->id));
        self::assertSame([],$this->rewards($newEmpire));
    }

    public function testCompletedGuildProjectCannotBeStartedAgain(): void
    {
        $fixture=$this->fixture();[$a,$b,$definition,$project]=$fixture;$this->finish($fixture);
        $this->expectException(\DomainException::class);$this->expectExceptionMessage('déjà lancé');
        $this->projects->start($a,$definition->id,$definition->version);
    }

    public function testDissolutionAbandonsTheProjectWithoutDeletingItsContributions(): void
    {
        [$a,$b,$definition,$project]=$this->fixture();$this->donate($a,$project,['metal'=>1]);
        $this->diplomacy->disband($a);
        self::assertSame('abandoned',$project->status);
        self::assertCount(1,$this->em->getRepository(GuildProjectContribution::class)->findBy(['project'=>$project]));
        self::assertSame([],$this->rewards($a));
    }
}
