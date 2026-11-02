<?php
namespace App\Tests\Controller;

use App\Entity\{PasswordResetToken,Player};
use App\Service\Player\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class PasswordResetTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private $client;

    protected function setUp():void
    {
        $this->client=static::createClient([],['HTTP_HOST'=>'genesis-reborn.localhost','REMOTE_ADDR'=>'192.0.2.'.random_int(1,254)]);
        $this->em=static::getContainer()->get(EntityManagerInterface::class);
        $params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test'||($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $schema=new SchemaTool($this->em);
        $metadata=$this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
    }

    private function createPlayer():Player
    {
        $player=new Player();
        $player->email='captain@example.test';
        $player->username='Captain';
        $player->roles=[];
        $player->password=static::getContainer()->get('security.user_password_hasher')->hashPassword($player,'Original-Password-2026');
        $this->em->persist($player);
        $this->em->flush();
        return $player;
    }

    public function testTokenIsHashedExpiresAndCanOnlyResetOnce():void
    {
        $player=$this->createPlayer();
        $message=null;
        $mailer=$this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->with(self::callback(function($email)use(&$message):bool{
            $message=$email;
            return $email instanceof Email && $email->getTo()[0]->getAddress()==='captain@example.test';
        }));
        static::getContainer()->set(MailerInterface::class,$mailer);

        $service=static::getContainer()->get(PasswordResetService::class);
        $service->request(' CAPTAIN@example.test ');
        self::assertInstanceOf(Email::class,$message);
        self::assertStringContainsString('valable une heure',$message->getTextBody());
        self::assertMatchesRegularExpression('#https?://[^\s]+/mot-de-passe/reinitialiser/[A-Za-z0-9_-]{40,50}#',$message->getTextBody());

        $token=$this->em->getRepository(PasswordResetToken::class)->findOneBy(['player'=>$player]);
        self::assertNotNull($token);
        self::assertSame(64,strlen($token->tokenHash));
        self::assertGreaterThan(new \DateTimeImmutable(),$token->expiresAt);
        preg_match('#/mot-de-passe/reinitialiser/([A-Za-z0-9_-]{40,50})#',$message->getTextBody(),$matches);
        $plainToken=$matches[1]??'';
        self::assertNotSame($plainToken,$token->tokenHash);
        self::assertTrue($service->isValid($plainToken));
        $crawler=$this->client->request('GET','/mot-de-passe/reinitialiser/'.$plainToken);
        self::assertResponseIsSuccessful();
        self::assertSame('no-referrer',$this->client->getResponse()->headers->get('Referrer-Policy'));
        $form=$crawler->selectButton('Modifier le mot de passe')->form();
        $this->client->submit($form,['password_reset[plainPassword][first]'=>'Replacement-Password-2026','password_reset[plainPassword][second]'=>'Replacement-Password-2026']);
        self::assertResponseRedirects('/connexion');
        $crawler=$this->client->followRedirect();
        self::assertStringContainsString('Mot de passe modifié',$crawler->filter('.flash.success')->text());
        self::assertFalse($service->isValid($plainToken));
        self::assertFalse($service->reset($plainToken,'Another-Password-2026'));
        $playerId=$player->id;
        $currentEm=static::getContainer()->get(EntityManagerInterface::class);
        $currentEm->clear();
        $player=$currentEm->find(Player::class,$playerId);
        self::assertTrue(static::getContainer()->get('security.user_password_hasher')->isPasswordValid($player,'Replacement-Password-2026'));
        self::assertFalse(static::getContainer()->get('security.user_password_hasher')->isPasswordValid($player,'Original-Password-2026'));
    }

    public function testUnknownAddressDoesNotSendMailOrRevealAccountExistence():void
    {
        $this->createPlayer();
        $mailer=$this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send');
        static::getContainer()->set(MailerInterface::class,$mailer);
        $service=static::getContainer()->get(PasswordResetService::class);

        $service->request('missing@example.test');
        self::assertCount(0,$this->em->getRepository(PasswordResetToken::class)->findAll());
        $service->request('captain@example.test');
        self::assertCount(1,$this->em->getRepository(PasswordResetToken::class)->findAll());
    }

    public function testExpiredTokenCannotBeUsed():void
    {
        $player=$this->createPlayer();
        $plainToken=str_repeat('a',43);
        $token=new PasswordResetToken();
        $token->player=$player;
        $token->tokenHash=hash('sha256',$plainToken);
        $token->createdAt=new \DateTimeImmutable('-2 hours');
        $token->expiresAt=new \DateTimeImmutable('-1 hour');
        $this->em->persist($token);
        $this->em->flush();
        $service=static::getContainer()->get(PasswordResetService::class);
        self::assertFalse($service->isValid($plainToken));
        self::assertFalse($service->reset($plainToken,'Replacement-Password-2026'));
    }
    public function testPublicRequestUsesSameConfirmationForKnownAndUnknownAddresses():void
    {
        $this->createPlayer();
        $crawler=$this->client->request('GET','/mot-de-passe/oublie');
        self::assertResponseIsSuccessful();
        $form=$crawler->selectButton('Envoyer le lien')->form();
        $this->client->submit($form,['password_reset_request[email]'=>'missing@example.test']);
        self::assertResponseRedirects('/mot-de-passe/oublie');
        $crawler=$this->client->followRedirect();
        $genericMessage=$crawler->filter('.flash.success')->text();
        self::assertStringContainsString('Si cette adresse correspond à un compte',$genericMessage);
        self::assertCount(0,$this->em->getRepository(PasswordResetToken::class)->findAll());

        $crawler=$this->client->request('GET','/mot-de-passe/oublie');
        $form=$crawler->selectButton('Envoyer le lien')->form();
        $this->client->submit($form,['password_reset_request[email]'=>'captain@example.test']);
        self::assertResponseRedirects('/mot-de-passe/oublie');
        $crawler=$this->client->followRedirect();
        self::assertSame($genericMessage,$crawler->filter('.flash.success')->text());
        self::assertCount(1,$this->em->getRepository(PasswordResetToken::class)->findAll());
    }

}
