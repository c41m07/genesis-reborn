<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\{Guild, GuildMember, GuildRank, GuildRecruitment, Notification, Player, PlayerUniverse, Universe};
use App\Repository\GameRepository;
use App\Service\Player\{DiplomacyAccessPolicy, DiplomacyService, GuildRankService, RegistrationService};
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Tests\Support\GuildRecruitmentFixture;
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GuildRecruitmentTest extends WebTestCase
{
    private KernelBrowser $browser;
    private EntityManagerInterface $entities;

    protected function setUp(): void
    {
        $this->browser = self::createClient([], ['HTTP_HOST' => 'genesis-reborn.localhost']);
        $this->browser->disableReboot();
        $this->entities = self::getContainer()->get(EntityManagerInterface::class);
        $params = $this->entities->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée.');
        }
        $schema = new SchemaTool($this->entities);
        $metadata = $this->entities->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();
    }

    public function testApplicationFromProfileWaitsAndOnlyRecruitersSeeOrReceiveTheAlert(): void
    {
        $leader = $this->player('RecruitLeader');
        $officer = $this->player('RecruitOfficer');
        $regular = $this->player('RecruitRegular');
        $candidate = $this->player('RecruitCandidate');
        $guild = $this->guild($leader);
        foreach ([$officer, $regular] as $fixture) {
            GuildRecruitmentFixture::enrol($this->diplomacy(), self::getContainer()->get(GameRepository::class), $fixture->currentPlayerUniverse, $guild->id);
        }
        $ranks = self::getContainer()->get(GuildRankService::class);
        $ranks->save($leader->currentPlayerUniverse, 0, 'Recruteur', 10, [DiplomacyAccessPolicy::RECRUIT], 0);
        $rank = $this->entities->getRepository(GuildRank::class)->findOneBy(['guild' => $guild]);
        $ranks->assign($leader->currentPlayerUniverse, $this->member($officer)->id, $rank->id, 0);

        $this->browser->loginUser($candidate);
        $this->browser->request('GET', '/guildes/'.$guild->id);
        $this->browser->submitForm('Demander à rejoindre');
        self::assertResponseRedirects('/guildes/'.$guild->id, 303);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('form[action$="/withdraw"] button', 'Retirer ma candidature');
        self::assertNull($this->member($candidate));

        // Une seconde soumission ne renvoie pas la notification ni ne crée une adhésion.
        $this->diplomacy()->applyToGuild($this->entities->find(PlayerUniverse::class, $candidate->currentPlayerUniverse->id), $guild->id);
        $pending = $this->entities->getRepository(GuildRecruitment::class)->findOneBy(['candidate' => $candidate->currentPlayerUniverse]);
        foreach ([$officer, $regular] as $recipient) {
            $alerts = $this->entities->getRepository(Notification::class)->count([
                'recipient' => $recipient->currentPlayerUniverse,
                'message' => 'Une nouvelle candidature est en attente dans votre guilde.',
            ]);
            self::assertSame($recipient === $officer ? 1 : 0, $alerts);
        }

        $this->browser->loginUser($regular);
        $this->browser->request('GET', '/guildes');
        self::assertSelectorNotExists('#guild-recruitment');
        self::assertSelectorExists('[data-guild-recruitment-count][hidden]');

        $this->browser->loginUser($officer);
        $this->browser->request('GET', '/guildes');
        self::assertSelectorTextContains('[data-guild-recruitment-count]', '1');
        self::assertSelectorTextContains('#guild-recruitment', 'RecruitCandidate');
        $this->browser->submitForm('Accepter la candidature');
        self::assertResponseRedirects('/guildes', 303);
        self::assertSame($guild->id, $this->member($candidate)->guild->id);
        self::assertNull($this->entities->find(GuildRecruitment::class, $pending->id));
    }

    public function testInvitationNeedsTheInvitedPlayerToAccept(): void
    {
        $leader = $this->player('InviteLeader');
        $candidate = $this->player('InviteCandidate');
        $guild = $this->guild($leader);
        $this->browser->loginUser($leader);
        $this->browser->request('GET', '/joueurs/'.$candidate->currentPlayerUniverse->id);
        $this->browser->submitForm('Inviter dans ma guilde');
        self::assertResponseStatusCodeSame(303);
        self::assertNull($this->member($candidate));
        $this->browser->loginUser($candidate);
        $this->browser->request('GET', '/guildes');
        self::assertSelectorTextContains('#guild-recruitment', $guild->name);
        $this->browser->submitForm('Accepter l’invitation');
        self::assertResponseRedirects('/guildes', 303);
        self::assertSame($guild->id, $this->member($candidate)->guild->id);
    }

    public function testOrdinaryMemberCannotApproveEvenWithAKnownRequest(): void
    {
        $leader = $this->player('RejectLeader');
        $regular = $this->player('RejectRegular');
        $candidate = $this->player('RejectCandidate');
        $guild = $this->guild($leader);
        GuildRecruitmentFixture::enrol($this->diplomacy(), self::getContainer()->get(GameRepository::class), $regular->currentPlayerUniverse, $guild->id);
        $this->diplomacy()->applyToGuild($candidate->currentPlayerUniverse, $guild->id);
        $request = $this->entities->getRepository(GuildRecruitment::class)->findOneBy(['candidate' => $candidate->currentPlayerUniverse]);
        $this->expectExceptionMessage('Votre grade ne permet pas');
        $this->diplomacy()->answerRecruitment($regular->currentPlayerUniverse, $request->id, 'accept');
    }

    private function player(string $name): Player
    {
        $data = new RegistrationData();
        $data->username = $name;
        $data->email = strtolower($name).'@example.test';
        $data->plainPassword = 'Browser-test-2026!';
        $player = self::getContainer()->get(RegistrationService::class)->register($data);
        $universe = $this->entities->getRepository(Universe::class)->findOneBy(['slug' => 'omega-1']);
        self::getContainer()->get(UniverseService::class)->select($player, $universe->id);
        return $player;
    }

    private function guild(Player $leader): Guild
    {
        $this->diplomacy()->create($leader->currentPlayerUniverse, 'Recrutement', 'RCT');
        return $this->entities->getRepository(Guild::class)->findOneBy(['leader' => $leader->currentPlayerUniverse]);
    }

    private function member(Player $player): ?GuildMember
    {
        return $this->entities->getRepository(GuildMember::class)->findOneBy(['playerUniverse' => $player->currentPlayerUniverse]);
    }

    private function diplomacy(): DiplomacyService
    {
        return self::getContainer()->get(DiplomacyService::class);
    }
}
