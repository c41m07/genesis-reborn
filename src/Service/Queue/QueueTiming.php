<?php
namespace App\Service\Queue;
final class QueueTiming
{
    public static function boostedDuration(int $duration,int $elapsed,float $divisor): int
    {
        if($duration<1 || $elapsed<0 || !is_finite($divisor) || $divisor<=1) throw new \InvalidArgumentException('Boost invalide.');
        $elapsed=min($elapsed,$duration);
        return max(1,$elapsed+(int)ceil(($duration-$elapsed)/$divisor));
    }
    public static function finishTimes(\DateTimeImmutable $start,array $durations): array
    {
        $times=[];$sum=0;
        foreach($durations as $duration) { if($duration<1) throw new \InvalidArgumentException('Durée invalide.');$sum+=$duration;$times[]=$start->modify('+'.$sum.' seconds'); }
        return $times;
    }
}
