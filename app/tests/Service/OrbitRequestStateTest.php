<?php
namespace App\Tests\Service;

use App\Entity\{Fleet,Planet,PlayerUniverse};
use App\Service\Fleet\OrbitRequestState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrbitRequestStateTest extends TestCase
{
    private function fleet(): Fleet
    {
        $host=new PlayerUniverse();$host->id=2;
        $planet=new Planet();$planet->id=3;$planet->owner=$host;
        $fleet=new Fleet();$fleet->mission='orbit';$fleet->status='traveling';$fleet->destination=$planet;
        $fleet->departureAt=new \DateTimeImmutable('2026-11-12T12:00:00Z');
        $fleet->arrivalAt=$fleet->departureAt->modify('+1 hour');
        $fleet->navigation=['version'=>1,'active'=>['progress'=>0]];
        return $fleet;
    }

    public function testOpeningKeepsNavigationAndAutomaticConsent(): void
    {
        $fleet=$this->fleet();$state=new OrbitRequestState();$state->open($fleet,true);
        self::assertSame(['progress'=>0],$fleet->navigation['active']);
        self::assertSame('accepted_auto',$state->read($fleet)['status']);
        self::assertSame(2,$state->read($fleet)['recipient_id']);
        self::assertSame($fleet->arrivalAt->format(DATE_ATOM),$state->read($fleet)['expires_at']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/',$state->read($fleet)['key']);
    }

    public function testLegacyFleetIsNeverBackfilledByRescheduleOrClose(): void
    {
        $fleet=$this->fleet();$before=$fleet->navigation;$state=new OrbitRequestState();
        $state->reschedule($fleet);$state->close($fleet,'withdrawn',$fleet->departureAt);
        self::assertNull($state->read($fleet));self::assertSame($before,$fleet->navigation);
    }

    public function testBoostUpdatesDeadlineWithoutReplacingConsentOrKey(): void
    {
        $fleet=$this->fleet();$state=new OrbitRequestState();$state->open($fleet,false);$key=$state->read($fleet)['key'];
        $fleet->arrivalAt=$fleet->departureAt->modify('+30 minutes');$state->reschedule($fleet);
        self::assertSame($fleet->arrivalAt->format(DATE_ATOM),$state->read($fleet)['expires_at']);
        $state->decide($fleet,2,$key,true,$fleet->departureAt->modify('+10 minutes'));
        self::assertSame('accepted_manual',$state->read($fleet)['status']);
    }

    public static function inaccessibleDecisions(): iterable
    {
        yield 'autre propriétaire'=>['actor'];
        yield 'clé périmée'=>['key'];
        yield 'arrivée atteinte'=>['arrival'];
        yield 'changement de propriétaire'=>['owner'];
        yield 'flotte en retour'=>['return'];
        yield 'autre destination'=>['destination'];
    }

    #[DataProvider('inaccessibleDecisions')]
    public function testDecisionRefusesStaleOrUnauthorizedRequestsWithoutChangingState(string $case): void
    {
        $fleet=$this->fleet();$state=new OrbitRequestState();$state->open($fleet,false);
        $key=$state->read($fleet)['key'];$actor=2;$at=$fleet->departureAt;
        if($case==='actor') $actor=9;
        if($case==='key') $key=str_repeat('0',32);
        if($case==='arrival') $at=$fleet->arrivalAt;
        if($case==='owner') $fleet->destination->owner=null;
        if($case==='return') $fleet->status='returning';
        if($case==='destination') $fleet->destination->id=10;
        $before=$fleet->navigation;
        try { $state->decide($fleet,$actor,$key,true,$at);self::fail('La demande devait être refusée.'); }
        catch(\DomainException) { self::assertSame($before,$fleet->navigation); }
    }

    public function testRefusalCannotBeAcceptedOrOverwrittenByRecall(): void
    {
        $fleet=$this->fleet();$state=new OrbitRequestState();$state->open($fleet,false);$key=$state->read($fleet)['key'];
        $state->decide($fleet,2,$key,false,$fleet->departureAt);$before=$fleet->navigation;
        $state->close($fleet,'withdrawn',$fleet->departureAt);self::assertSame($before,$fleet->navigation);
        $this->expectException(\DomainException::class);
        $state->decide($fleet,2,$key,true,$fleet->departureAt);
    }

    public function testExpiryKeepsRequestHistoryAndDeadline(): void
    {
        $fleet=$this->fleet();$state=new OrbitRequestState();$state->open($fleet,false);
        $before=$state->read($fleet);$state->close($fleet,'expired',$fleet->arrivalAt);
        $fleet->status='returning';$fleet->arrivalAt=$fleet->arrivalAt->modify('+1 hour');$state->reschedule($fleet);
        self::assertSame('expired',$state->read($fleet)['status']);
        self::assertSame($before['expires_at'],$state->read($fleet)['expires_at']);
        self::assertSame($before['key'],$state->read($fleet)['key']);
    }
}
