<?php
namespace App\Tests\Controller;

use App\Entity\{Guild, GuildMember, GuildProject, GuildProjectDefinition, GuildRelation, Player, PlayerUniverse, Universe};
use App\Service\Universe\UniverseImporter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminGuildDirectoryTest extends WebTestCase
{
    public function testScopedReadOnlyDirectoryAndPagination(): void
    {
        $client = self::createClient([], ['HTTP_HOST'=>'genesis-reborn.localhost']);
        $client->disableReboot();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée.');
        }
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();
        $universes = $em->getRepository(Universe::class)->findBy([], ['id'=>'ASC'], 2);
        $player = new Player();
        $player->username = 'GuildReader';
        $player->email = 'private-guild@example.test';
        $player->password = 'secret-password-not-for-display';
        $player->roles = ['ROLE_ADMIN'];
        $em->persist($player);
        $leaders = [];
        foreach ($universes as $universe) {
            $membership = new PlayerUniverse();
            $membership->player = $player;
            $membership->universe = $universe;
            $membership->joinedAt = new \DateTimeImmutable();
            $em->persist($membership);
            $leaders[] = $membership;
        }
        $makeGuild = static function (string $name, int $index) use ($em, $universes, $leaders): Guild {
            $guild = new Guild();
            $guild->name = $name;
            $guild->universe = $universes[$index];
            $guild->leader = $leaders[$index];
            $em->persist($guild);
            return $guild;
        };
        $guild = $makeGuild('Audit Alpha', 0);
        $peer = $makeGuild('Alliés visibles', 0);
        $foreign = $makeGuild('Guilde étrangère masquée', 1);
        $archived = $makeGuild('Archive masquée', 0);
        $archived->archivedAt = new \DateTimeImmutable();
        for ($i = 0; $i < 26; ++$i) $makeGuild('Pagination '.$i, 0);
        foreach ($leaders as $leader) {
            $member = new GuildMember();
            $member->guild = $guild;
            $member->playerUniverse = $leader;
            $em->persist($member);
        }
        foreach ([$peer, $foreign] as $target) {
            $relation = new GuildRelation();
            $relation->guildA = $guild;
            $relation->guildB = $target;
            $relation->proposer = $guild;
            $relation->status = 'neutral';
            $em->persist($relation);
        }
        $em->flush();
        foreach ($universes as $i=>$universe) {
            $definition = new GuildProjectDefinition();
            $definition->universe = $universe;
            $definition->name = 'Modèle courant '.$i;
            $definition->createdAt = $definition->updatedAt = new \DateTimeImmutable();
            $em->persist($definition);
            $project = new GuildProject();
            $project->universe = $universe;
            $project->guildId = $guild->id;
            $project->guildName = $guild->name;
            $project->definition = $definition;
            $project->snapshot = ['name'=>$i===0?'Nom historique du projet':'Projet étranger masqué','stages'=>[['name'=>'Palier']]];
            $project->startedAt = new \DateTimeImmutable();
            $project->rewardToken = 'secret-reward-token';
            $em->persist($project);
        }
        $em->flush();
        $before = [];
        foreach (['guild', 'guild_member', 'guild_relation', 'guild_project'] as $table) {
            $before[$table] = $em->getConnection()->fetchAllAssociative('SELECT * FROM '.$table.' ORDER BY id');
        }
        $client->request('GET', '/admin/guildes');
        self::assertResponseRedirects('http://genesis-reborn.localhost/connexion');
        $client->loginUser($player);
        $client->request('GET', '/admin/guildes?universe='.$universes[0]->id);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Audit Alpha');
        self::assertSelectorTextNotContains('main', 'Archive masquée');
        self::assertSelectorTextNotContains('main', 'Guilde étrangère masquée');
        $client->request('GET', '/admin/guildes?universe='.$universes[0]->id.'&q=Audit');
        self::assertSelectorCount(1, 'tbody tr');
        $client->request('GET', '/admin/universes/'.$universes[0]->id.'/guildes/'.$archived->id.'/members');
        self::assertResponseStatusCodeSame(404);
        $crawler = $client->request('GET', '/admin/guildes?q=Pagination');
        self::assertCount(25, $crawler->filter('tbody tr'));
        $client->click($crawler->selectLink('Page suivante')->link());
        self::assertSelectorCount(1, 'tbody tr');
        $base = '/admin/universes/'.$universes[0]->id.'/guildes/'.$guild->id;
        $client->request('GET', $base.'/members');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, 'tbody tr');
        self::assertSelectorTextContains('main', 'Direction');
        self::assertSelectorTextNotContains('main', $player->email);
        self::assertSelectorTextNotContains('main', $player->password);
        $client->request('GET', $base.'/diplomacy');
        self::assertSelectorCount(1, 'tbody tr');
        self::assertSelectorTextContains('tbody', 'Alliés visibles');
        self::assertSelectorTextNotContains('main', $foreign->name);
        $client->request('GET', $base.'/projects');
        self::assertSelectorCount(1, 'tbody tr');
        self::assertSelectorTextContains('tbody', 'Nom historique du projet');
        self::assertSelectorTextNotContains('main', 'Projet étranger masqué');
        self::assertSelectorTextNotContains('main', 'secret-reward-token');
        $client->request('GET', '/admin/universes/'.$universes[1]->id.'/guildes/'.$guild->id.'/members');
        self::assertResponseStatusCodeSame(404);
        foreach ($before as $table=>$rows) {
            self::assertSame($rows, $em->getConnection()->fetchAllAssociative('SELECT * FROM '.$table.' ORDER BY id'));
        }
        $ordinary = new Player();
        $ordinary->username = 'OrdinaryReader';
        $ordinary->email = 'ordinary-reader@example.test';
        $ordinary->password = 'test';
        $em->persist($ordinary);
        $em->flush();
        $client->loginUser($ordinary);
        foreach (['/admin/guildes', $base.'/members', $base.'/diplomacy', $base.'/projects'] as $url) {
            $client->request('GET', $url);
            self::assertResponseRedirects('/acces-refuse');
        }
    }
}
