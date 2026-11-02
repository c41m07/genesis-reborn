<?php
namespace App\Tests\Controller;
use App\Entity\{Player,Universe};
use App\Service\Catalog\GameCatalog;
use App\Service\Universe\UniverseImporter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
final class CatalogBalanceControllerTest extends WebTestCase
{
 private EntityManagerInterface $em;private $client;private Player $admin;private Universe $universe;
 protected function setUp():void
 { $this->client=static::createClient(['environment'=>'test'],['HTTP_HOST'=>'genesis-reborn.localhost']);$this->em=static::getContainer()->get(EntityManagerInterface::class);$p=$this->em->getConnection()->getParams();if(($p['host']??'')!=='database-test'||($p['dbname']??'')!=='genesis_v2_test')throw new \RuntimeException('Base de test non isolée.');$s=new SchemaTool($this->em);$m=$this->em->getMetadataFactory()->getAllMetadata();$s->dropSchema($m);$s->createSchema($m);static::getContainer()->get(UniverseImporter::class)->import();$this->universe=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'turbo-1']);$this->admin=new Player();$this->admin->email='catalog-admin@example.test';$this->admin->username='CatalogAdmin';$this->admin->password='test';$this->admin->roles=['ROLE_ADMIN'];$this->em->persist($this->admin);$this->em->flush(); }
 public function testCatalogEditorRequiresAdminAndSavesWithCsrf():void
 { $id=$this->universe->id;$url='/admin/universes/'.$id.'/catalogue';$this->client->request('GET',$url);self::assertResponseRedirects('http://genesis-reborn.localhost/connexion');$this->client->loginUser($this->admin);$crawler=$this->client->request('GET',$url);self::assertResponseIsSuccessful();self::assertSelectorExists('details#ships-fighter');$form=$crawler->filter('details#ships-fighter form')->first()->form();$form['values[precision]']='81';$this->client->submit($form);self::assertResponseRedirects('/admin/universes/'.$id.'/catalogue#ships-fighter');$this->em->clear();$universe=$this->em->find(Universe::class,$id);$config=static::getContainer()->get(GameCatalog::class)->getForUniverse($universe,'ships','fighter');self::assertSame(81.0,$config['precision']); }
 public function testOrdinaryPlayerCannotOpenCatalogEditor():void
 { $player=new Player();$player->email='catalog-player@example.test';$player->username='CatalogPlayer';$player->password='test';$this->em->persist($player);$this->em->flush();$this->client->loginUser($player);$this->client->request('GET','/admin/universes/'.$this->universe->id.'/catalogue');self::assertResponseRedirects('/acces-refuse'); }
}
