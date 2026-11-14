<?php
namespace App\Tests\Controller;

use App\Entity\{Guild,GuildMember,GuildProfile,GuildRank,Planet,Player,PlayerUniverse,Universe};
use App\Service\Player\{DailyLeaderboard,DiplomacyAccessPolicy,DiplomacyService,GuildImageStorage,GuildProfileService,RegistrationService,SocialProfileView};
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class PublicProfilesTest extends WebTestCase
{
    private $client;
    private Player $leader;
    private Player $member;
    private Player $foreign;
    private int $guildId;
    private string $inputs;
    private string $mediaDirectory;
    private array $existingMedia;

    protected function setUp(): void
    {
        $this->client=self::createClient([],['HTTP_HOST'=>'genesis-reborn.localhost']);
        $em=self::getContainer()->get(EntityManagerInterface::class);$params=$em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test' || ($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base non isolée.');
        $schema=new SchemaTool($em);$metadata=$em->getMetadataFactory()->getAllMetadata();$schema->dropSchema($metadata);$schema->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();
        $this->leader=$this->player('PublicLeader');$this->member=$this->player('PublicMember');$this->foreign=$this->player('PublicForeign','turbo-1');
        foreach($em->getRepository(Universe::class)->findAll() as $universe) $universe->gameSettings['features']['guilds_enabled']=true;
        $em->flush();$diplomacy=self::getContainer()->get(DiplomacyService::class);
        $diplomacy->create($this->leader->currentPlayerUniverse,'Public Guild','PUB');
        $guild=$em->getRepository(Guild::class)->findOneBy(['leader'=>$this->leader->currentPlayerUniverse]);$this->guildId=$guild->id;
        $diplomacy->join($this->member->currentPlayerUniverse,$this->guildId);
        $this->inputs=sys_get_temp_dir().'/genesis-profile-input-'.bin2hex(random_bytes(8));mkdir($this->inputs,0700);
        $this->mediaDirectory=dirname(self::getContainer()->get(GuildImageStorage::class)->path(str_repeat('0',48).'.png'));
        if(!str_contains($this->mediaDirectory,'/var/cache/test/')) throw new \RuntimeException('Stockage de test non isolé.');
        $this->existingMedia=glob($this->mediaDirectory.'/*')?:[];
        $this->client->loginUser($this->leader);
    }

    protected function tearDown(): void
    {
        foreach(array_diff(glob($this->mediaDirectory.'/*')?:[],$this->existingMedia) as $file) unlink($file);
        foreach(glob($this->inputs.'/*')?:[] as $file) unlink($file);
        rmdir($this->inputs);parent::tearDown();
    }

    private function player(string $name,string $slug='omega-1'): Player
    {
        $data=new RegistrationData();$data->username=$name;$data->email=strtolower($name).'@example.test';$data->plainPassword='Public-test-2026!';
        $player=self::getContainer()->get(RegistrationService::class)->register($data);
        $universe=self::getContainer()->get(EntityManagerInterface::class)->getRepository(Universe::class)->findOneBy(['slug'=>$slug]);
        self::getContainer()->get(UniverseService::class)->select($player,$universe->id);return $player;
    }

    private function form(): array
    {
        $crawler=$this->client->request('GET','/guildes/'.$this->guildId.'/personnaliser');self::assertResponseIsSuccessful();
        return ['_token'=>$crawler->filter('input[name="_token"]')->attr('value'),'version'=>$crawler->filter('input[name="version"]')->attr('value'),'description'=>'Description publique'];
    }

    private function image(): UploadedFile
    {
        $path=$this->inputs.'/'.bin2hex(random_bytes(6));$image=imagecreatetruecolor(20,20);imagepng($image,$path);imagedestroy($image);
        return new UploadedFile($path,'../../unsafe.php','application/x-php',null,true);
    }

    private function profileRow(): array|false
    {
        return self::getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchAssociative('SELECT * FROM guild_profile WHERE guild_id=?',[$this->guildId]);
    }

    public function testPlayerViewWhitelistsOnlyApprovedDataAndUsesFrozenScores(): void
    {
        $em=self::getContainer()->get(EntityManagerInterface::class);$this->member->preferences['private']='PRIVATE-PREFERENCES-MARKER';$this->member->roles=['ROLE_ADMIN'];$em->flush();
        $viewer=$this->leader->currentPlayerUniverse;$target=$this->member->currentPlayerUniverse;$view=self::getContainer()->get(SocialProfileView::class);
        $profile=$view->player($viewer,$target->id);self::assertSame(['id','username','scores','updated_at','guild'],array_keys($profile));
        self::assertCount(5,$profile['scores']);self::assertSame('PublicMember',$profile['username']);self::assertSame('PUB',$profile['guild']['tag']);
        $planet=$em->getRepository(Planet::class)->findOneBy(['owner'=>$target]);
        $em->getConnection()->executeStatement("INSERT INTO planet_building (planet_id,slug,tier,level) VALUES (?,'mine_metal','basic',5) ON DUPLICATE KEY UPDATE level=5",[$planet->id]);
        self::assertSame($profile['scores'],$view->player($viewer,$target->id)['scores']);
        $this->client->request('GET','/joueurs/'.$target->id);self::assertResponseIsSuccessful();
        $html=$this->client->getResponse()->getContent();
        foreach([$this->member->email,'PRIVATE-PREFERENCES-MARKER','ROLE_ADMIN',$planet->coordinates()] as $private) self::assertStringNotContainsString($private,$html);
        self::assertSelectorTextContains('h1','PublicMember');self::assertSelectorCount(5,'.social-profile-scores > div');
        self::assertStringContainsString('no-store',$this->client->getResponse()->headers->get('Cache-Control'));
    }

    public function testProfilesRequireAuthenticationAndTheSameUniverse(): void
    {
        $this->client->request('GET','/joueurs/'.$this->foreign->currentPlayerUniverse->id);self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($this->foreign);$this->client->request('GET','/guildes/'.$this->guildId);self::assertResponseStatusCodeSame(404);
        $this->client->request('GET','/guildes/'.$this->guildId.'/image/logo');self::assertResponseStatusCodeSame(404);
        $this->client->getCookieJar()->clear();$this->client->request('GET','/joueurs/'.$this->leader->currentPlayerUniverse->id);self::assertResponseRedirects('/connexion');
    }

    public function testUnrankedAndForgottenPlayersDoNotExposeInventedRanks(): void
    {
        self::getContainer()->get(DailyLeaderboard::class)->forUniverse($this->leader->currentPlayerUniverse);
        $new=$this->player('PublicNew');$id=$new->currentPlayerUniverse->id;
        $this->client->request('GET','/joueurs/'.$id);self::assertResponseIsSuccessful();self::assertSelectorTextContains('.social-profile-scores','Pas encore classé');
        self::getContainer()->get(EntityManagerInterface::class)->getConnection()->executeStatement('UPDATE player_universe SET forgotten_at=UTC_TIMESTAMP() WHERE id=?',[$id]);
        $this->client->request('GET','/joueurs/'.$id);self::assertResponseStatusCodeSame(404);
    }

    public function testOnlyCurrentCommanderCanEditEvenWithAllDelegatedPermissions(): void
    {
        $em=self::getContainer()->get(EntityManagerInterface::class);$guild=$em->find(Guild::class,$this->guildId);
        $rank=new GuildRank();$rank->guild=$guild;$rank->name='Delegated';$rank->position=1;$rank->permissions=array_keys(DiplomacyAccessPolicy::PERMISSIONS);$em->persist($rank);
        $member=$em->getRepository(GuildMember::class)->findOneBy(['playerUniverse'=>$this->member->currentPlayerUniverse]);$member->rank=$rank;$em->flush();
        $this->client->loginUser($this->member);$this->client->request('GET','/guildes/'.$this->guildId);self::assertResponseIsSuccessful();self::assertSelectorNotExists('a[href$="/personnaliser"]');
        $this->client->request('POST','/guildes/'.$this->guildId.'/personnaliser',['description'=>'Interdit']);self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($this->leader);$form=$this->form();
        $em=self::getContainer()->get(EntityManagerInterface::class);$leader=$em->find(PlayerUniverse::class,$this->leader->currentPlayerUniverse->id);
        self::getContainer()->get(DiplomacyService::class)->transfer($leader,$this->member->currentPlayerUniverse->id);
        $this->client->request('POST','/guildes/'.$this->guildId.'/personnaliser',$form);self::assertResponseStatusCodeSame(403);
        self::assertFalse($this->profileRow());
    }

    public function testImagesAndDescriptionAreEscapedPrivateAndVersioned(): void
    {
        $form=$this->form();$form['description']="<script>alert(1)</script>\nBonjour";
        $this->client->request('POST','/guildes/'.$this->guildId.'/personnaliser',$form,['logo'=>$this->image(),'banner'=>$this->image()]);self::assertResponseRedirects('/guildes/'.$this->guildId);
        $row=$this->profileRow();self::assertSame(1,$row['version']);$logo=$this->mediaDirectory.'/'.$row['logo_filename'];self::assertFileExists($logo);
        $this->client->followRedirect();self::assertSelectorCount(0,'.guild-public-description script');self::assertSelectorTextContains('.guild-public-description','<script>alert(1)</script>');
        $this->client->request('GET','/guildes/'.$this->guildId.'/image/logo');self::assertResponseIsSuccessful();self::assertResponseHeaderSame('Content-Type','image/png');self::assertResponseHeaderSame('X-Content-Type-Options','nosniff');
        self::assertStringContainsString('no-store',$this->client->getResponse()->headers->get('Cache-Control'));
        $this->client->request('POST','/guildes/'.$this->guildId.'/personnaliser',$form,['logo'=>$this->image()]);self::assertResponseRedirects('/guildes/'.$this->guildId.'/personnaliser');
        self::assertSame($row,$this->profileRow());self::assertCount(2,glob($this->mediaDirectory.'/*')?:[]);
        $fresh=$this->form();$this->client->request('POST','/guildes/'.$this->guildId.'/personnaliser',$fresh,['logo'=>$this->image()]);self::assertResponseRedirects('/guildes/'.$this->guildId);
        self::assertFileDoesNotExist($logo);self::assertSame(2,$this->profileRow()['version']);
        $this->client->loginUser($this->foreign);$this->client->request('GET','/guildes/'.$this->guildId.'/image/logo');self::assertResponseStatusCodeSame(404);
    }

    public function testInvalidSecondImageRollsBackAllChangesAndRemovesTemporaryMedia(): void
    {
        $form=$this->form();$bad=$this->inputs.'/bad.svg';file_put_contents($bad,'<svg onload="alert(1)"></svg>');
        $this->client->request('POST','/guildes/'.$this->guildId.'/personnaliser',$form,['logo'=>$this->image(),'banner'=>new UploadedFile($bad,'banner.png',null,null,true)]);
        self::assertResponseRedirects('/guildes/'.$this->guildId.'/personnaliser');self::assertFalse($this->profileRow());
        self::assertSame($this->existingMedia,glob($this->mediaDirectory.'/*')?:[]);
        $this->client->followRedirect();self::assertSelectorTextContains('[role="alert"]','JPEG, PNG ou WebP');self::assertSelectorTextContains('textarea','Description publique');
    }

    public function testCsrfAndDescriptionLengthAreCheckedWithoutChangingTheProfile(): void
    {
        $form=$this->form();$wrong=$form;$wrong['_token']='forged';
        $this->client->request('POST','/guildes/'.$this->guildId.'/personnaliser',$wrong);self::assertResponseStatusCodeSame(403);
        $form['description']=str_repeat('a',2001);$this->client->request('POST','/guildes/'.$this->guildId.'/personnaliser',$form);self::assertResponseRedirects('/guildes/'.$this->guildId.'/personnaliser');
        self::assertFalse($this->profileRow());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('forgedActors')]
    public function testServiceRejectsForgedActorsBeforeWritingImages(string $scenario): void
    {
        $actor=match($scenario){'foreign'=>$this->foreign->currentPlayerUniverse,'member'=>$this->member->currentPlayerUniverse,default=>$this->leader->currentPlayerUniverse};
        if($scenario==='forgotten') $actor->forgottenAt=new \DateTimeImmutable();
        if($scenario==='locked') $actor->player->lockedAt=new \DateTimeImmutable();
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $db=self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $this->expectException(\DomainException::class);
        try {self::getContainer()->get(GuildProfileService::class)->save($actor,$this->guildId,'Interdit',0,$this->image(),null);}
        finally {self::assertSame(0,(int)$db->fetchOne('SELECT COUNT(*) FROM guild_profile'));self::assertSame($this->existingMedia,glob($this->mediaDirectory.'/*')?:[]);}
    }
    public static function forgedActors(): array {return [['foreign'],['member'],['forgotten'],['locked']];}

    #[\PHPUnit\Framework\Attributes\DataProvider('unavailableGuilds')]
    public function testArchivedAndDisabledGuildsAreUnavailable(string $scenario): void
    {
        $em=self::getContainer()->get(EntityManagerInterface::class);
        if($scenario==='archived') $em->find(Guild::class,$this->guildId)->archivedAt=new \DateTimeImmutable();
        else $this->leader->currentPlayerUniverse->universe->gameSettings['features']['guilds_enabled']=false;
        $em->flush();
        foreach(['','/personnaliser','/image/logo'] as $suffix) {$this->client->request('GET','/guildes/'.$this->guildId.$suffix);self::assertResponseStatusCodeSame(404);}
    }
    public static function unavailableGuilds(): array {return [['archived'],['disabled']];}

    public function testInvalidStoredMediaKeyCannotEscapeTheStorage(): void
    {
        $em=self::getContainer()->get(EntityManagerInterface::class);$profile=new GuildProfile();$profile->guild=$em->find(Guild::class,$this->guildId);$profile->logoFilename='../private.env';$em->persist($profile);$em->flush();
        $this->client->request('GET','/guildes/'.$this->guildId.'/image/logo');self::assertResponseStatusCodeSame(404);
    }
}
