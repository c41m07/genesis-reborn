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

 public function testPreviewNeedsNoConfirmationAndDoesNotWrite(): void
 {
  $this->client->loginUser($this->admin);
  $id = $this->universe->id;
  $before = $this->universe->gameSettings;
  $url = '/admin/universes/'.$id.'/catalogue/ships/fighter';
  $crawler = $this->client->request('GET', $url);
  $form = $crawler->selectButton('Comparer le devis')->form();
  $form['values[build_time_base]'] = '900';
  $form['preview_quantity'] = '3';
  $this->client->submit($form);
  self::assertResponseRedirects($url);
  $this->client->followRedirect();
  self::assertSelectorTextContains('#preview-heading', 'non enregistrée');
  $comparison = $this->client->getCrawler()->filter('table.admin-design-comparison');
  self::assertSame(['Élément', 'Actuel', 'Proposé'], $comparison->filter('th[scope="col"]')->each(static fn($header) => $header->text()));
  $durations = $comparison->filter('tbody tr')->first()->filter('td')->each(static fn($cell) => $cell->text());
  self::assertCount(2, $durations);
  self::assertNotSame($durations[0], $durations[1]);
  self::assertSelectorTextContains('.admin-catalogue-editor', 'stocks disponibles');
  self::assertSelectorExists('input[name="values[build_time_base]"][value="900"]');
  $em = static::getContainer()->get(EntityManagerInterface::class);
  $em->clear();
  self::assertSame($before, $em->find(Universe::class, $id)->gameSettings);
  self::assertSame(0, $em->getRepository(\App\Entity\AdminAuditLog::class)->count());
 }

 public function testPreviewRejectsCsrfAndOrdinaryPlayer(): void
 {
  $this->client->loginUser($this->admin);
  $url = '/admin/universes/'.$this->universe->id.'/catalogue/ships/fighter';
  $crawler = $this->client->request('GET', $url);
  $form = $crawler->selectButton('Comparer le devis')->form();
  $form['_token'] = 'invalid';
  $this->client->submit($form);
  self::assertResponseRedirects('/acces-refuse');
  $player = new Player();
  $player->email = 'preview-player@example.test';
  $player->username = 'PreviewPlayer';
  $player->password = bin2hex(random_bytes(16));
  $em = static::getContainer()->get(EntityManagerInterface::class);
  $em->persist($player);
  $em->flush();
  $this->client->loginUser($player);
  $this->client->request('POST', $url, ['preview' => '1']);
  self::assertResponseRedirects('/acces-refuse');
 }

 public function testHistoryIsScopedPaginatedEscapedAndReadOnly(): void
 {
  $id = $this->universe->id;
  $beforeSettings = $this->universe->gameSettings;
  for ($index = 0; $index < 22; ++$index) {
   $entry = new \App\Entity\AdminAuditLog();
   $entry->actor = $this->admin;
   $entry->action = 'universe_catalog_balance';
   $entry->targetType = 'universe';
   $entry->targetId = $id;
   $entry->createdAt = new \DateTimeImmutable('2026-11-19 10:00:00 UTC');
   $entry->details = ['group' => 'ships', 'slug' => 'fighter', 'reason' => '<script>injection</script>',
    'before' => ['precision' => 12], 'after' => ['precision' => 13]];
   if ($index === 21) {
    $entry->details = ['group' => 'ships', 'slug' => 'fighter', 'values' => ['precision' => 14]];
   }
   $this->em->persist($entry);
  }
  foreach ([[$id + 999, 'ships', 'fighter'], [$id, 'defenses', 'fighter'], [$id, 'ships', 'different']] as [$target, $family, $slug]) {
   $entry = new \App\Entity\AdminAuditLog();
   $entry->action = 'universe_catalog_balance';
   $entry->targetType = 'universe';
   $entry->targetId = $target;
   $entry->createdAt = new \DateTimeImmutable();
   $entry->details = ['group' => $family, 'slug' => $slug, 'reason' => 'NE_PAS_AFFICHER'];
   $this->em->persist($entry);
  }
  $this->em->flush();
  $url = '/admin/universes/'.$id.'/catalogue/ships/fighter/historique';
  $this->client->request('GET', $url);
  self::assertResponseRedirects('http://genesis-reborn.localhost/connexion');
  $this->client->loginUser($this->admin);
  $crawler = $this->client->request('GET', $url);
  self::assertResponseIsSuccessful();
  self::assertCount(20, $crawler->filter('details'));
  self::assertSelectorTextContains('body', 'Non enregistré');
  self::assertSelectorTextContains('body', 'Motif non enregistré');
  self::assertStringNotContainsString('NE_PAS_AFFICHER', $this->client->getResponse()->getContent());
  self::assertStringNotContainsString('<script>injection</script>', $this->client->getResponse()->getContent());
  self::assertStringContainsString('&lt;script&gt;', $this->client->getResponse()->getContent());
  self::assertSelectorNotExists('#admin-main form[method=post]');
  $this->client->click($crawler->filter('a[rel=next]')->link());
  self::assertResponseIsSuccessful();
  self::assertSelectorCount(2, 'details');
  self::assertSelectorNotExists('a[rel=next]');
  $this->client->request('GET', '/admin/universes/'.$id.'/catalogue/ships/inconnu/historique');
  self::assertResponseStatusCodeSame(404);
  $this->client->request('GET', '/admin/universes/999999/catalogue/ships/fighter/historique');
  self::assertResponseStatusCodeSame(404);
  $this->em = static::getContainer()->get(EntityManagerInterface::class);
  $this->em->clear();
  self::assertSame($beforeSettings, $this->em->find(Universe::class, $id)->gameSettings);
  self::assertSame(25, $this->em->getRepository(\App\Entity\AdminAuditLog::class)->count());
 }

 public function testOrdinaryPlayerCannotReadHistory(): void
 {
  $player = new Player();
  $player->email = 'history-player@example.test';
  $player->username = 'HistoryPlayer';
  $player->password = bin2hex(random_bytes(16));
  $this->em->persist($player);
  $this->em->flush();
  $this->client->loginUser($player);
  $this->client->request('GET', '/admin/universes/'.$this->universe->id.'/catalogue/ships/fighter/historique');
  self::assertResponseRedirects('/acces-refuse');
  $this->client->request('GET', '/admin/universes/'.$this->universe->id.'/catalogue/ships/fighter/restaurer/1');
  self::assertResponseRedirects('/acces-refuse');
  $this->client->request('POST', '/admin/universes/'.$this->universe->id.'/catalogue/ships/fighter/restaurer/1');
  self::assertResponseRedirects('/acces-refuse');
 }

 public function testRestorationRequiresPreviewCsrfAndConfirmationAndPublishesOnce(): void
 {
  $this->client->loginUser($this->admin);
  $id = $this->universe->id;
  $edit = '/admin/universes/'.$id.'/catalogue/ships/fighter';
  foreach ([80, 90] as $precision) {
   $crawler = $this->client->request('GET', $edit);
   $form = $crawler->filter('form.admin-catalogue-editor')->form();
   $form['values[precision]'] = (string) $precision;
   $form['reason'] = 'Version de recette';
   $form['confirmed']->tick();
   $this->client->submit($form);
  }
  $this->em = static::getContainer()->get(EntityManagerInterface::class);
  $source = $this->em->getRepository(\App\Entity\AdminAuditLog::class)->findOneBy([], ['id' => 'ASC'])->id;
  $url = $edit.'/restaurer/'.$source;
  $crawler = $this->client->request('GET', $url);
  self::assertResponseIsSuccessful();
  self::assertSelectorTextContains('h1', 'trace #'.$source);
  $form = $crawler->filter('form.admin-catalogue-restore')->form();
  $form['reason'] = 'Restauration de recette';
  $form['confirmed']->tick();
  $valid = $form->getPhpValues();
  $invalid = $valid;
  $invalid['_token'] = 'invalid';
  $this->client->request('POST', $url, $invalid);
  self::assertResponseRedirects('/acces-refuse');
  $invalid = $valid;
  unset($invalid['confirmed']);
  $this->client->request('POST', $url, $invalid);
  self::assertResponseRedirects($edit.'/historique');
  // L’aperçu seul et les deux refus n’ajoutent aucun audit.
  self::assertSame(2, static::getContainer()->get(EntityManagerInterface::class)->getRepository(\App\Entity\AdminAuditLog::class)->count(['action' => 'universe_catalog_balance']));
  $this->client->request('POST', $url, $valid);
  self::assertResponseRedirects($edit.'/historique');
  $this->em = static::getContainer()->get(EntityManagerInterface::class);
  $this->em->clear();
  self::assertSame(80.0, static::getContainer()->get(GameCatalog::class)->getForUniverse($this->em->find(Universe::class, $id), 'ships', 'fighter')['precision']);
  self::assertSame(3, $this->em->getRepository(\App\Entity\AdminAuditLog::class)->count(['action' => 'universe_catalog_balance']));
  $this->client->request('POST', $url, $valid);
  self::assertSame(3, static::getContainer()->get(EntityManagerInterface::class)->getRepository(\App\Entity\AdminAuditLog::class)->count(['action' => 'universe_catalog_balance']));
 }
}
