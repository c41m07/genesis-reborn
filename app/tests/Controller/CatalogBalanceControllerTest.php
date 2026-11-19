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
 { $id=$this->universe->id;$url='/admin/universes/'.$id.'/catalogue';$this->client->request('GET',$url);self::assertResponseRedirects('http://genesis-reborn.localhost/connexion');$this->client->loginUser($this->admin);$crawler=$this->client->request('GET',$url);self::assertResponseIsSuccessful();self::assertSelectorNotExists('form[method=post] input[name^=values]');$crawler=$this->client->request('GET',$url.'/ships/fighter');self::assertResponseIsSuccessful();$form=$crawler->filter('form')->reduce(static fn($node)=>$node->filter('input[name="values[precision]"]')->count()>0)->first()->form();$form['values[precision]']='81';$form['reason']='Vérification isolée';$form['confirmed']->tick();$this->client->submit($form);self::assertResponseRedirects('/admin/universes/'.$id.'/catalogue/ships/fighter');$this->em->clear();$universe=$this->em->find(Universe::class,$id);$config=static::getContainer()->get(GameCatalog::class)->getForUniverse($universe,'ships','fighter');self::assertSame(81.0,$config['precision']); }
 public function testInvalidValueReturnsToEntryAndKeepsInputWithoutWriting(): void
 {
  $this->client->loginUser($this->admin);
  $url='/admin/universes/'.$this->universe->id.'/catalogue/ships/fighter';
  $before=$this->universe->gameSettings;
  $crawler=$this->client->request('GET',$url);
  $form=$crawler->filter('form')->reduce(static fn($node)=>$node->filter('input[name="values[precision]"]')->count()>0)->first()->form();
  $form['values[precision]']='1001';
  $form['reason']='Vérification isolée';$form['confirmed']->tick();$this->client->submit($form);
  self::assertResponseRedirects($url);
  $this->client->followRedirect();
  self::assertSelectorExists('input[name="values[precision]"][value="1001"]');
  self::assertSelectorTextContains('.flash.error','Valeur hors limites');
  $this->em->clear();
  self::assertSame($before,$this->em->find(Universe::class,$this->universe->id)->gameSettings);
 }
 public function testOrdinaryPlayerCannotOpenCatalogEditor():void
 { $player=new Player();$player->email='catalog-player@example.test';$player->username='CatalogPlayer';$player->password='test';$this->em->persist($player);$this->em->flush();$this->client->loginUser($player);$this->client->request('GET','/admin/universes/'.$this->universe->id.'/catalogue');self::assertResponseRedirects('/acces-refuse'); }

 public function testStaleSubmissionCannotOverwriteAndKeepsOriginalRevision(): void
 {
  $this->client->loginUser($this->admin);
  $id = $this->universe->id;
  $url = '/admin/universes/'.$id.'/catalogue/ships/fighter';
  $crawler = $this->client->request('GET', $url);
  $form = $crawler->filter('form.admin-catalogue-editor')->form();
  $originalRevision = $form['revision']->getValue();
  $form['values[precision]'] = '81';
  $form['reason'] = 'Première modification';
  $form['confirmed']->tick();
  $this->client->submit($form);
  self::assertResponseRedirects($url);
  $form['values[precision]'] = '90';
  $this->client->submit($form);
  self::assertResponseRedirects($url);
  $this->client->followRedirect();
  self::assertSelectorTextContains('.flash.error', 'a changé');
  self::assertSelectorExists('input[name="values[precision]"][value="90"]');
  self::assertSelectorExists('input[name="revision"][value="'.$originalRevision.'"]');
  $this->em = static::getContainer()->get(EntityManagerInterface::class);
  $this->em->clear();
  $universe = $this->em->find(Universe::class, $id);
  self::assertSame(81.0, static::getContainer()->get(GameCatalog::class)->getForUniverse($universe, 'ships', 'fighter')['precision']);
  self::assertSame(1, $this->em->getRepository(\App\Entity\AdminAuditLog::class)->count());
  $crawler = $this->client->request('GET', $url.'?reload=1');
  self::assertSelectorExists('input[name="values[precision]"][value="81"]');
  self::assertNotSame($originalRevision, $crawler->filter('form.admin-catalogue-editor input[name="revision"]')->attr('value'));
 }

 public function testMissingRevisionIsRejectedWithoutWriting(): void
 {
  $this->client->loginUser($this->admin);
  $url = '/admin/universes/'.$this->universe->id.'/catalogue/ships/fighter';
  $crawler = $this->client->request('GET', $url);
  $form = $crawler->filter('form.admin-catalogue-editor')->form();
  $form['revision'] = '';
  $form['reason'] = 'Essai de formulaire incomplet';
  $form['confirmed']->tick();
  $this->client->submit($form);
  $this->client->followRedirect();
  self::assertSelectorTextContains('.flash.error', 'a changé');
  self::assertSame(0, static::getContainer()->get(EntityManagerInterface::class)->getRepository(\App\Entity\AdminAuditLog::class)->count());
 }

 public function testResetAlsoRejectsOldRevision(): void
 {
  $this->client->loginUser($this->admin);
  $url = '/admin/universes/'.$this->universe->id.'/catalogue/ships/fighter';
  $crawler = $this->client->request('GET', $url);
  $reset = $crawler->filter('form.admin-catalogue-reset')->form();
  $reset['reason'] = 'Retour aux valeurs initiales';
  $reset['confirmed']->tick();
  $form = $crawler->filter('form.admin-catalogue-editor')->form();
  $form['reason'] = 'Modification récente';
  $form['values[precision]'] = '84';
  $form['confirmed']->tick();
  $this->client->submit($form);
  $this->client->submit($reset);
  $this->client->followRedirect();
  self::assertSelectorTextContains('.flash.error', 'a changé');
  self::assertSame(1, static::getContainer()->get(EntityManagerInterface::class)->getRepository(\App\Entity\AdminAuditLog::class)->count());
 }
}
