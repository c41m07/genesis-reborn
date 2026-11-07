<?php
declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\{PatchNote, Player};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PatchNoteControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient(['environment' => 'test'], ['HTTP_HOST' => 'genesis-reborn.localhost']);
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $schema = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata); $schema->createSchema($metadata);
    }

    public function testPlayersOnlySeePublishedPatchnotes(): void
    {
        $player = $this->player(); $this->client->loginUser($player);
        $draft = new PatchNote(); $draft->title='Brouillon'; $draft->version='v0'; $draft->summary='Résumé'; $draft->content='Secret';
        $published = new PatchNote(); $published->title='Publié'; $published->version='v1'; $published->summary='Résumé public'; $published->content='Détail public'; $published->status='published'; $published->publishedAt=new \DateTimeImmutable('-1 hour');
        $this->em->persist($draft); $this->em->persist($published); $this->em->flush();
        $this->client->request('GET', '/patchnotes');
        self::assertResponseIsSuccessful(); self::assertSelectorTextContains('body', 'Publié'); self::assertSelectorTextNotContains('body', 'Brouillon');
    }

    public function testOnlyAdminsCanManagePatchnotes(): void
    {
        $this->client->loginUser($this->player());
        $this->client->request('GET', '/admin/patchnotes');
        self::assertResponseRedirects('/acces-refuse');
    }

    private function player(array $roles = []): Player
    {
        $player = new Player(); $player->email=uniqid('patch-', true).'@example.test'; $player->username=uniqid('Patch'); $player->password='test'; $player->roles=$roles;
        $this->em->persist($player); $this->em->flush();
        return $player;
    }
}
