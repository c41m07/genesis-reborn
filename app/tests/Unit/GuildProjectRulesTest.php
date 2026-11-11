<?php
namespace App\Tests\Unit;

use App\Service\Player\GuildProjectRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GuildProjectRulesTest extends TestCase
{
    private const LABELS=['metal'=>'Métal','hydrogen'=>'Hydrogène'];

    private function draft(): array
    {
        return ['name'=>'Chantier commun','description'=>'Deux étapes.',
            'stages'=>[['name'=>'Structure','costs'=>['metal'=>'100','hydrogen'=>'0']],['name'=>'Énergie','costs'=>['hydrogen'=>'50']]],
            'minimums'=>['metal'=>'20','hydrogen'=>'10']];
    }

    public function testEachResourceThresholdIsRequiredWithoutCurrencyConversion(): void
    {
        $rules=new GuildProjectRules();$data=$rules->definition($this->draft(),self::LABELS);
        self::assertSame(['metal'=>100],$data['stages'][0]['costs']);
        self::assertFalse($rules->eligible(['metal'=>100],$data['minimums']));
        self::assertFalse($rules->eligible(['metal'=>20,'hydrogen'=>9],$data['minimums']));
        self::assertTrue($rules->eligible(['metal'=>20,'hydrogen'=>10],$data['minimums']));
        self::assertSame(5,$rules->maximumRecipients($data['stages'],$data['minimums']));
        self::assertSame(['metal'=>25],$rules->remaining(['metal'=>100],['metal'=>75]));
    }

    public static function invalidQuantities(): array
    {
        return [[-1],['1.5'],['1e3'],['9223372036854775808'],[true],[[1]],[null],[1000000001]];
    }

    #[DataProvider('invalidQuantities')]
    public function testDonationCannotUseNegativeFractionalOrUnboundedQuantities(mixed $amount): void
    {
        $this->expectException(\DomainException::class);
        (new GuildProjectRules())->quantities(['metal'=>$amount],self::LABELS,GuildProjectRules::MAX_STAGE_AMOUNT);
    }

    public function testUnknownResourcesAreRejected(): void
    {
        $this->expectException(\DomainException::class);
        (new GuildProjectRules())->quantities(['forged'=>1],self::LABELS,100);
    }

    public function testThresholdCannotExceedWhatTheProjectCanReceive(): void
    {
        $draft=$this->draft();$draft['minimums']['metal']=101;
        $this->expectException(\DomainException::class);
        (new GuildProjectRules())->definition($draft,self::LABELS);
    }

    public function testEmptyThresholdCannotGiveFreeRewards(): void
    {
        $draft=$this->draft();$draft['minimums']=['metal'=>'0','hydrogen'=>''];
        $this->expectException(\DomainException::class);
        (new GuildProjectRules())->definition($draft,self::LABELS);
    }

    public function testStageWithoutCostIsRejected(): void
    {
        $draft=$this->draft();$draft['stages'][0]['costs']=['metal'=>0];
        $this->expectException(\DomainException::class);
        (new GuildProjectRules())->definition($draft,self::LABELS);
    }
}
