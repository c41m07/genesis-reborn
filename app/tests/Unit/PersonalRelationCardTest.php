<?php
namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class PersonalRelationCardTest extends TestCase
{
    private function card(string $status, bool $isProposer = false, string $guildStatus = 'neutral'): string
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2).'/templates'), ['strict_variables' => true]);
        $twig->addFunction(new TwigFunction('path', static fn (string $route, array $parameters): string => '/messages?with='.$parameters['with']));
        $twig->addFunction(new TwigFunction('csrf_token', static fn (string $name): string => 'test-token'));

        return $twig->render('player/_relation_card.html.twig', [
            'membership' => (object) ['id' => 1],
            'row' => [
                'player' => (object) ['id' => 2, 'player' => (object) ['username' => 'Beta']],
                'contract' => (object) ['status' => $status, 'proposer' => (object) ['id' => $isProposer ? 1 : 2]],
                'enemy' => $guildStatus === 'enemies' || ($guildStatus !== 'allies' && in_array($status, ['enemies', 'peace_pending'], true)),
                'allied' => $guildStatus === 'allies' || ($guildStatus !== 'enemies' && $status === 'accepted'),
                'guildStatus' => $guildStatus,
            ],
        ]);
    }

    public function testEndedContractIsNotPresentedAsAnInvitation(): void
    {
        $html = $this->card('ended');
        self::assertStringContainsString('Relation personnelle terminée', $html);
        self::assertStringNotContainsString('Proposition reçue', $html);
        self::assertStringNotContainsString('value="accept"', $html);
        self::assertStringNotContainsString('value="break"', $html);
        self::assertStringContainsString('value="propose"', $html);
    }

    public function testPeaceNeedsTheOtherPlayerAndNeverShowsBreak(): void
    {
        $sent = $this->card('peace_pending', true);
        self::assertStringContainsString('Proposition de paix envoyée', $sent);
        self::assertStringNotContainsString('value="peace_accept"', $sent);
        self::assertStringNotContainsString('value="break"', $sent);
        $received = $this->card('peace_pending');
        self::assertStringContainsString('Proposition de paix reçue', $received);
        self::assertStringContainsString('value="peace_accept"', $received);
        self::assertStringContainsString('value="peace_reject"', $received);
    }

    public function testGuildPriorityIsExplainedInsteadOfCallingAnAllyAnEnemy(): void
    {
        $html = $this->card('enemies', false, 'allies');
        self::assertStringContainsString('>Allié<', $html);
        self::assertStringContainsString('les liens de guilde sont prioritaires', $html);
        self::assertStringNotContainsString('>Rival personnel<', $html);
    }

    public function testExistingAllianceConsentAndBreakActionsRemainAvailable(): void
    {
        self::assertStringContainsString('value="accept"', $this->card('pending'));
        self::assertStringNotContainsString('value="accept"', $this->card('pending', true));
        self::assertStringContainsString('Rompre le contrat', $this->card('accepted'));
    }
}
