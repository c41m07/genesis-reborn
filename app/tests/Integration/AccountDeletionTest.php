<?php
namespace App\Tests\Integration;

use App\Entity\{EmailVerificationToken, PasswordResetToken, Planet, Player, PlayerUniverse, Universe};
use App\Service\Player\{AccountDeletionService, RegistrationService};
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class AccountDeletionTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Player $player;
    private MockClock $clock;
    private const PASSWORD='A-test-password-2026';

    protected function setUp(): void
    {
        self::bootKernel();$this->em=self::getContainer()->get(EntityManagerInterface::class);
        $params=$this->em->getConnection()->getParams();
        if (($params['host']??'')!=='database-test' || ($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $tool=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();$tool->dropSchema($metadata);$tool->createSchema($metadata);
        $this->clock=new MockClock('2026-11-09T12:00:00Z');self::getContainer()->set('clock',$this->clock);
        self::getContainer()->get(UniverseImporter::class)->import();
        $data=new RegistrationData();$data->email='delete@example.test';$data->username='DeleteTester';$data->plainPassword=self::PASSWORD;
        $this->player=self::getContainer()->get(RegistrationService::class)->register($data);
        $universe=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'omega-1']);
        self::getContainer()->get(UniverseService::class)->select($this->player,$universe->id);
    }

    public function testErasesIdentityAndTokensButPreservesAllEmpiresAndColonies(): void
    {
        $first=$this->player->currentPlayerUniverse;
        $other=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'turbo']);
        if (!$other) $other=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'omega-2']);
        if (!$other) { $other=new Universe();$other->slug='delete-second';$other->name='Second';$this->em->persist($other);$this->em->flush(); }
        $second=new PlayerUniverse();$second->player=$this->player;$second->universe=$other;$second->joinedAt=$this->clock->now();$this->em->persist($second);
        $verification=new EmailVerificationToken();$verification->player=$this->player;$verification->tokenHash=str_repeat('a',64);$verification->expiresAt=$this->clock->now()->modify('+1 day');$this->em->persist($verification);
        $reset=new PasswordResetToken();$reset->player=$this->player;$reset->tokenHash=str_repeat('b',64);$reset->createdAt=$this->clock->now();$reset->expiresAt=$this->clock->now()->modify('+1 hour');$this->em->persist($reset);
        $this->player->preferences=['example'=>'private'];$this->em->flush();$id=$this->player->id;$oldPassword=$this->player->password;
        self::getContainer()->get(AccountDeletionService::class)->delete($this->player,self::PASSWORD,true);
        $this->em->clear();$player=$this->em->find(Player::class,$id);
        self::assertNotSame('delete@example.test',$player->email);self::assertStringEndsWith('@deleted.invalid',$player->email);
        self::assertSame('Compte supprimé #'.$id,$player->username);self::assertNotSame($oldPassword,$player->password);
        self::assertNotNull($player->lockedAt);self::assertNull($player->emailVerifiedAt);self::assertNull($player->currentPlayerUniverse);self::assertSame([],$player->preferences);self::assertSame([],$player->roles);
        foreach ([$first,$second] as $membership) {
            $stored=$this->em->find(PlayerUniverse::class,$membership->id);self::assertTrue($stored->isForgotten());self::assertNull($stored->player);self::assertNull($stored->formerPlayer);
        }
        self::assertNotNull($this->em->getRepository(Planet::class)->findOneBy(['owner'=>$first->id]));
        self::assertSame(0,$this->em->getRepository(EmailVerificationToken::class)->count([]));self::assertSame(0,$this->em->getRepository(PasswordResetToken::class)->count([]));
    }

    public function testWrongPasswordDoesNotChangeIdentityOrEmpire(): void
    {
        $id=$this->player->id;
        try { self::getContainer()->get(AccountDeletionService::class)->delete($this->player,'wrong',true);self::fail(); }
        catch (\DomainException $error) { self::assertStringContainsString('Mot de passe',$error->getMessage()); }
        self::assertSame('delete@example.test',$this->em->getConnection()->fetchOne('SELECT email FROM player WHERE id=?',[$id]));
        self::assertSame(1,(int)$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM player_universe WHERE player_id=? AND forgotten_at IS NULL',[$id]));
    }

    public function testConfirmationIsRequired(): void
    {
        $this->expectException(\DomainException::class);self::getContainer()->get(AccountDeletionService::class)->delete($this->player,self::PASSWORD,false);
    }

    public function testLastAdministratorCannotDeleteAccount(): void
    {
        $this->player->roles=['ROLE_ADMIN'];$this->em->flush();
        $this->expectException(\DomainException::class);$this->expectExceptionMessage('dernier administrateur');
        self::getContainer()->get(AccountDeletionService::class)->delete($this->player,self::PASSWORD,true);
    }

    public function testClosedUniverseAndActiveVacationDoNotPreventDeletion(): void
    {
        $membership=$this->player->currentPlayerUniverse;
        $vacation=new \App\Entity\VacationPeriod();$vacation->membership=$membership;$vacation->startedAt=$this->clock->now()->modify('-2 days');$vacation->scheduledEndAt=$this->clock->now()->modify('+10 days');$this->em->persist($vacation);
        $membership->universe->status='closed';$this->em->flush();
        self::getContainer()->get(AccountDeletionService::class)->delete($this->player,self::PASSWORD,true);
        self::assertTrue($membership->isForgotten());self::assertEquals($this->clock->now(),$vacation->endedAt);
    }

    public function testCombatRefusesDeletionWithoutChangingTheAccount(): void
    {
        $membership=$this->player->currentPlayerUniverse;$planet=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$membership]);
        $combat=new \App\Entity\CombatEvent();$combat->planet=$planet;$combat->seed=42;$combat->startedAt=$this->clock->now();$combat->resolveAt=$this->clock->now()->modify('+1 minute');$this->em->persist($combat);$this->em->flush();
        try { self::getContainer()->get(AccountDeletionService::class)->delete($this->player,self::PASSWORD,true);self::fail(); }
        catch (\DomainException $error) { self::assertStringContainsString('combat en cours',$error->getMessage()); }
        self::assertSame('delete@example.test',$this->em->getConnection()->fetchOne('SELECT email FROM player WHERE id=?',[$this->player->id]));
        self::assertNull($this->em->getConnection()->fetchOne('SELECT forgotten_at FROM player_universe WHERE id=?',[$membership->id]));
    }
    public function testCombatInSecondUniverseRollsBackTheFirstConversion(): void
    {
        $first=$this->player->currentPlayerUniverse;
        $other=null;
        foreach ($this->em->getRepository(Universe::class)->findBy(['status'=>'active']) as $candidate) {
            if ($candidate->id!==$first->universe->id && $candidate->isAccessible($this->clock->now())) { $other=$candidate;break; }
        }
        self::assertNotNull($other);self::getContainer()->get(UniverseService::class)->select($this->player,$other->id);
        $second=$this->player->currentPlayerUniverse;$planet=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$second]);
        $combat=new \App\Entity\CombatEvent();$combat->planet=$planet;$combat->seed=42;$combat->startedAt=$this->clock->now();$combat->resolveAt=$this->clock->now()->modify('+1 minute');$this->em->persist($combat);$this->em->flush();
        try { self::getContainer()->get(AccountDeletionService::class)->delete($this->player,self::PASSWORD,true);self::fail(); }
        catch (\DomainException $error) { self::assertStringContainsString('combat en cours',$error->getMessage()); }
        $connection=$this->em->getConnection();
        self::assertSame(2,(int)$connection->fetchOne('SELECT COUNT(*) FROM player_universe WHERE player_id=? AND forgotten_at IS NULL',[$this->player->id]));
        self::assertSame(0,(int)$connection->fetchOne('SELECT COUNT(*) FROM empire_conversion'));
        self::assertSame('delete@example.test',$connection->fetchOne('SELECT email FROM player WHERE id=?',[$this->player->id]));
    }

    public function testDeletionResumesVacationQueuesWithTheirRemainingWorkAndProduction(): void
    {
        $membership=$this->player->currentPlayerUniverse;$planet=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$membership]);
        $manager=self::getContainer()->get(\App\Manager\GameManager::class);$queues=self::getContainer()->get(\App\Service\Queue\QueueService::class);
        $resources=self::getContainer()->get(\App\Service\Colony\ResourceService::class);
        $head=new \App\Entity\QueueItem();$head->playerUniverse=$membership;$head->planet=$planet;$head->queueType='building';$head->itemSlug='mine_metal';$head->itemLevel=4;
        $head->quantity=1;$head->durationSeconds=600;$head->queuedAt=$this->clock->now();$head->startedAt=$this->clock->now();$head->dispatchToken='delete-vacation-queue';
        $planet->buildingQueueStartedAt=$head->startedAt;$this->em->persist($head);$this->em->flush();
        foreach($this->em->getRepository(\App\Entity\PlanetResource::class)->findBy(['planet'=>$planet]) as $row)$row->currentStock=1000;
        $this->em->flush();
        $before=$resources->snapshot($planet);
        $vacation=new \App\Entity\VacationPeriod();$vacation->membership=$membership;$vacation->startedAt=$this->clock->now();$vacation->scheduledEndAt=$this->clock->now()->modify('+90 days');
        $manager->transaction(function () use ($manager,$queues,$resources,$membership,$planet,$vacation): void {
            $queues->pauseForVacationLocked($membership,$vacation->startedAt,$vacation->scheduledEndAt);$resources->materialize($planet,$vacation->startedAt);
            $manager->save($vacation);$manager->flush();$resources->refreshProduction($planet);
        });
        $pausedToken=$head->dispatchToken;$this->clock->sleep(3600);
        self::getContainer()->get(AccountDeletionService::class)->delete($this->player,self::PASSWORD,true);
        self::assertTrue($membership->isForgotten());self::assertSame(600,$head->finishAt()->getTimestamp()-$this->clock->now()->getTimestamp());
        self::assertNotSame($pausedToken,$head->dispatchToken);self::assertEquals($head->startedAt,$planet->buildingQueueStartedAt);
        $after=$resources->snapshot($planet);self::assertGreaterThan(0,$after['metal']['rate']);self::assertEqualsWithDelta($before['metal']['stock'],$after['metal']['stock'],0.000001);
    }

}
