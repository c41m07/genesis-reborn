<?php
namespace App\Service\Combat;

use Random\Engine\Mt19937;
use Random\Randomizer;

/** Combat déterministe sur un instantané validé par les services métier. */
final class CombatSimulator
{
    private const MAX_UNITS = 5000;
    /** Puissance figée au début du tour, selon la durabilité restante. */
    private function powerFactor(array $unit):float
    {
        $share=($unit['hp']+$unit['shield'])/($unit['initial_hp']+$unit['max_shield']);
        return max(0.0,min(1.0,$share))**2;
    }

    /**
     * Les participants doivent déjà être regroupés dans leurs camps par l'appelant.
     * Chaque entrée de participant contient id, role, camp, arrival_round et units.
     */
    public function simulate(array $participants, array $stationaryDefenses, array $modifiers, int $maxRounds, int $seed, ?int $roundLimit = null, float $shieldRechargePercent = 5.0, bool $restoreSurvivorShields = true, bool $independentCamps = false): array
    {
        if ($maxRounds < 1 || $maxRounds > 100) {
            throw new \DomainException('Nombre de rounds invalide.');
        }
        if ($seed < 0 || $seed > 0xffffffff) {
            throw new \DomainException('Graine de combat invalide.');
        }
        if (!is_finite($shieldRechargePercent) || $shieldRechargePercent < 0 || $shieldRechargePercent > 100) {
            throw new \DomainException('Pourcentage de recharge des boucliers invalide.');
        }
        $roundLimit = max(1, min($maxRounds, $roundLimit ?? $maxRounds));
        $units = $this->snapshot($participants, $stationaryDefenses, $maxRounds);
        $this->validateModifiers($modifiers);
        $random = new Randomizer(new Mt19937($seed));
        $rounds = [];
        $outcome = 'draw';
        $winnerCamp=null;

        for ($roundNumber = 1; $roundNumber <= $roundLimit; ++$roundNumber) {
            $order = [];
            foreach ($units as $key => $unit) {
                if ($unit['arrival_round'] <= $roundNumber && $unit['hp'] > 0) {
                    $order[] = $key;
                }
            }
            $order = $random->shuffleArray($order);
            $shots = [];

            foreach ($order as $attackerKey) {
                if ($units[$attackerKey]['hp'] <= 0 || (float) ($units[$attackerKey]['base_attack'] ?? 0) <= 0) {
                    continue;
                }
                $targets = $this->targetsFor($attackerKey, $units, $roundNumber, $independentCamps);
                if ($targets === []) {
                    continue;
                }
                $attacker = $units[$attackerKey];
                $baseShotCount = $random->getInt($attacker['shots_min'], $attacker['shots_max']);
                $shotSchedule = [];
                for ($baseShot = 0; $baseShot < $baseShotCount; ++$baseShot) {
                    $shotSchedule[] = false;
                    foreach ($attacker['extra_shot_chances'] ?? [] as $extraChance) {
                        if ($random->getInt(1, 10000) <= max(0, min(1, (float) $extraChance)) * 10000) $shotSchedule[] = true;
                    }
                }
                $targetKey = null;
                foreach ($shotSchedule as $shotIndex => $isExtraShot) {
                    $availableTargets = $this->targetsFor($attackerKey, $units, $roundNumber, $independentCamps);
                    if ($targetKey === null || !isset($units[$targetKey]) || $units[$targetKey]['hp'] <= 0) $targetKey = $this->selectTarget($random, $availableTargets, $units);
                    if ($targetKey === null) break;
                    $target = $units[$targetKey];$modifier=$this->counterMultiplier($attacker,$target,$modifiers);$chance = $this->hitChance($attacker, $target, 0, $modifier);
                    $hit = $random->getInt(1, 10000) <= ($chance * 100);$damage = 0.0;$shieldDamage = 0.0;$structureDamage = 0.0;$destroyed = false;
                    if ($hit && $units[$targetKey]['hp'] > 0) {
                        $crit = max(0.0, 1.0-$units[$targetKey]['hp']/($units[$targetKey]['initial_hp']*0.25));
                        if ($crit > 0 && $random->getInt(1, 10000) <= $crit*10000) {
                            $units[$targetKey]['hp'] = 0.0; $destroyed = true;
                        } else {
                            $damage = $attacker['base_attack'] * $modifier * $this->powerFactor($attacker);
                            $shieldDamage = min($units[$targetKey]['shield'], $damage);$units[$targetKey]['shield'] -= $shieldDamage;
                            $remainingDamage = max(0.0, $damage - $shieldDamage);$structureDamage = min($units[$targetKey]['hp'], $remainingDamage);$units[$targetKey]['hp'] = max(0.0, $units[$targetKey]['hp'] - $structureDamage);
                            $damage = $shieldDamage + $structureDamage;$destroyed = $units[$targetKey]['hp'] <= 0;
                        }
                    }
                    $shots[] = ['attacker'=>$attackerKey,'target'=>$targetKey,'weapon'=>'primary','shot'=>$shotIndex+1,'extra_shot'=>$isExtraShot,'hit'=>$hit,'chance'=>$chance,'damage'=>$damage,'shield_damage'=>$shieldDamage,'structure_damage'=>$structureDamage,'target_hp'=>$units[$targetKey]['hp'],'target_shield'=>$units[$targetKey]['shield'],'destroyed'=>$destroyed];
                }
                $secondaryCount=max(0,(int)($attacker['secondary_shots_min']??0));$secondaryMax=max($secondaryCount,(int)($attacker['secondary_shots_max']??$secondaryCount));
                if($secondaryMax>0 && (float)($attacker['secondary_attack']??0)>0){
                    $secondaryCount=$random->getInt($secondaryCount,$secondaryMax);
                    $secondarySchedule=[];
                    for($i=0;$i<$secondaryCount;$i++){
                        $secondarySchedule[]=false;
                        foreach($attacker['extra_shot_chances']??[] as $extraChance)if($random->getInt(1,10000)<=max(0,min(1,(float)$extraChance))*10000)$secondarySchedule[]=true;
                    }
                    $secondaryCount=count($secondarySchedule);

                    for($secondaryShot=0;$secondaryShot<$secondaryCount;$secondaryShot++){
                        $availableTargets=$this->targetsFor($attackerKey,$units,$roundNumber,$independentCamps);$targetCategory=$attacker['secondary_target_category']??null;
                        $availableTargets=array_values(array_filter($availableTargets,static fn(string $key):bool=>($units[$key]['category']??null)===$targetCategory));
                        $secondaryTarget=$this->selectTarget($random,$availableTargets,$units);if($secondaryTarget===null)break;
                        $target=$units[$secondaryTarget];$modifier=$this->counterMultiplier($attacker,$target,$modifiers);$chance=$this->hitChance($attacker,$target,(int)($attacker['secondary_precision_modifier']??0),$modifier);
                        $hit=$random->getInt(1,10000)<=($chance*100);$damage=0.0;$shieldDamage=0.0;$structureDamage=0.0;$destroyed=false;
                        if($hit&&$units[$secondaryTarget]['hp']>0){
                            $crit=max(0.0,1.0-$units[$secondaryTarget]['hp']/($units[$secondaryTarget]['initial_hp']*0.25));
                            if($crit>0&&$random->getInt(1,10000)<=$crit*10000){
                                $units[$secondaryTarget]['hp']=0.0;$destroyed=true;
                            }else{
                                $damage=(float)$attacker['secondary_attack']*$modifier*$this->powerFactor($attacker);
                                $shieldDamage=min($units[$secondaryTarget]['shield'],$damage);$units[$secondaryTarget]['shield']-=$shieldDamage;
                                $remainingDamage=max(0.0,$damage-$shieldDamage);$structureDamage=min($units[$secondaryTarget]['hp'],$remainingDamage);
                                $units[$secondaryTarget]['hp']=max(0.0,$units[$secondaryTarget]['hp']-$structureDamage);
                                $damage=$shieldDamage+$structureDamage;$destroyed=$units[$secondaryTarget]['hp']<=0;
                            }
                        }
                        $shots[]=['attacker'=>$attackerKey,'target'=>$secondaryTarget,'weapon'=>'anti_light','shot'=>$secondaryShot+1,'extra_shot'=>$secondarySchedule[$secondaryShot],'hit'=>$hit,'chance'=>$chance,'damage'=>$damage,'shield_damage'=>$shieldDamage,'structure_damage'=>$structureDamage,'target_hp'=>$units[$secondaryTarget]['hp'],'target_shield'=>$units[$secondaryTarget]['shield'],'destroyed'=>$destroyed];
                    }
                }
            }

            foreach ($units as &$unit) {
                if ($unit['hp'] > 0 && $unit['shield'] > 0 && $unit['arrival_round'] <= $roundNumber) {
                    $unit['shield'] = min($unit['max_shield'], $unit['shield'] + $unit['max_shield'] * $shieldRechargePercent / 100);
                }
            }
            unset($unit);
            $rounds[] = ['number' => $roundNumber, 'shots' => $shots];
            if($independentCamps) {
                $camps=[];
                foreach($units as $unit) if($unit['hp']>0) $camps[$unit['camp']][]=$unit['role'];
                if(count($camps)<=1) {
                    $winnerCamp=$camps===[]?null:array_key_first($camps);
                    $roles=$winnerCamp===null?[]:$camps[$winnerCamp];
                    $outcome=$winnerCamp===null?'draw':((in_array('defender',$roles,true)||in_array('static_defense',$roles,true))?'defender_wins':'attacker_wins');
                    break;
                }
                // À la limite existante, les attaquants se retirent ; aucun camp unique n'est déclaré vainqueur.
                $outcome=$roundNumber===$maxRounds?'defender_wins':'ongoing';
                continue;
            }
            $aliveAttackers = $this->countAlive($units, 'attacker', $roundNumber);
            $aliveDefenders = $this->countAlive($units, 'defender', $roundNumber) + $this->countAlive($units, 'static_defense', $roundNumber);
            $futureAttackers = $this->countFuture($units, 'attacker', $roundNumber);
            $futureDefenders = $this->countFuture($units, 'defender', $roundNumber) + $this->countFuture($units, 'static_defense', $roundNumber);
            if ($aliveAttackers === 0 && $futureAttackers === 0) {
                $outcome = ($aliveDefenders + $futureDefenders) === 0 ? 'draw' : 'defender_wins';
                break;
            }
            if ($aliveDefenders === 0 && $futureDefenders === 0) {
                $outcome = ($aliveAttackers + $futureAttackers) === 0 ? 'draw' : 'attacker_wins';
                break;
            }
            if ($roundNumber === $maxRounds) {
                // La règle du document prévoit la retraite des attaquants survivants.
                $outcome = 'defender_wins';
            } elseif ($roundNumber === $roundLimit) {
                $outcome = 'ongoing';
            }
        }

        $result = [
            'seed' => $seed,
            'outcome' => $outcome,
            'rounds_played' => count($rounds),
            'rounds' => $rounds,
            'survivors' => array_map(static fn (array $unit): array => [
                'owner' => $unit['owner'],
                'role' => $unit['role'],
                'camp' => $unit['camp'],
                'slug' => $unit['slug'],
                'tier' => $unit['tier'] ?? 'basic',
                'hp' => $unit['hp'],
                'shield' => $restoreSurvivorShields ? $unit['max_shield'] : $unit['shield'],
            ], array_filter($units, static fn (array $unit): bool => $unit['hp'] > 0)),
            'losses' => $this->losses($units),
        ];
        if($independentCamps) $result['winner_camp']=$winnerCamp;
        return $result;
    }

    public function hitChance(array $attacker, array $target, int $precisionModifier = 0, float $counterMultiplier = 1.0): float
    {
        if(!is_finite($counterMultiplier)||$counterMultiplier<=0||$counterMultiplier>10)throw new \DomainException('Coefficient de contre invalide.');
        $tierFactors = ['basic' => 1, 'intermediate' => 2, 'superior' => 3];
        $attackerTier = $tierFactors[$attacker['tier'] ?? 'basic'] ?? 1;
        $targetTier = $tierFactors[$target['tier'] ?? 'basic'] ?? 1;
        $value = $attacker['precision'] + $attacker['tracking'] + $precisionModifier - $target['evasion'] - 15;

        // Le contre agit avant le bonus de rang ; chaque étape reste bornée à 15–95 %.
        $baseChance=max(15.0,min(95.0,$value));
        $counterChance=max(15.0,min(95.0,$baseChance*$counterMultiplier));
        return max(15.0,min(95.0,$counterChance+30*($attackerTier-$targetTier)));
    }

    private function counterMultiplier(array $attacker,array $target,array $modifiers):float
    {
        $relations=$modifiers[$attacker['slug']]??[];
        // Une relation précise prime ; les défenses partagent sinon leur famille générique.
        return (float)($relations[$target['slug']]??(($target['role']??'')==='static_defense'?($relations['ground_defense']??1.0):1.0));
    }

    /** La signature modifie la probabilité de sélection, pas la chance de toucher. */
    private function selectTarget(Randomizer $random,array $targets,array $units):?string
    {
        if($targets===[])return null;
        $injured=array_values(array_filter($targets,static fn(string $key):bool=>$units[$key]['hp']<$units[$key]['initial_hp']||$units[$key]['shield']<$units[$key]['max_shield']));
        if($injured!==[])$targets=$injured;
        $weights=[];$total=0;
        foreach($targets as $key){$weight=max(1,(int)round(max(1.0,(float)($units[$key]['signature']??50))*100));$weights[$key]=$weight;$total+=$weight;}
        $draw=$random->getInt(1,$total);foreach($weights as $key=>$weight){$draw-=$weight;if($draw<=0)return $key;}
        return array_key_first($weights);
    }

    private function snapshot(array $participants, array $stationaryDefenses, int $maxRounds): array
    {
        usort($participants, static fn (array $a, array $b): int => strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? '')));
        usort($stationaryDefenses, static fn (array $a, array $b): int => strcmp((string) ($a['id'] ?? $a['slug'] ?? ''), (string) ($b['id'] ?? $b['slug'] ?? '')));
        $units = [];
        $total = 0;
        foreach ($participants as $participant) {
            $role = $participant['role'] ?? '';
            $arrivalRound = $participant['arrival_round'] ?? null;
            if (!in_array($role, ['attacker', 'defender'], true) || !isset($participant['id'], $participant['camp']) || !is_int($arrivalRound) || $arrivalRound < 1 || $arrivalRound > $maxRounds) {
                throw new \DomainException('Participant de combat invalide.');
            }
            $this->appendUnits($units, $total, $participant['units'] ?? [], (string) $participant['id'], $role, (string) $participant['camp'], $arrivalRound);
        }
        foreach ($stationaryDefenses as $defense) {
            if (!isset($defense['id'], $defense['camp'])) {
                throw new \DomainException('Défense statique invalide.');
            }
            $this->appendUnits($units, $total, [$defense], 'planet:' . $defense['id'], 'static_defense', (string) $defense['camp'], 1);
        }
        if ($total === 0) {
            throw new \DomainException('Un combat doit contenir au moins une unité.');
        }

        return $units;
    }

    private function appendUnits(array &$units, int &$total, array $definitions, string $owner, string $role, string $camp, int $arrivalRound): void
    {
        usort($definitions, static fn (array $a, array $b): int => strcmp((string) ($a['slug'] ?? ''), (string) ($b['slug'] ?? '')));
        foreach ($definitions as $definition) {
            $quantity = $definition['quantity'] ?? null;
            if (!is_int($quantity) || $quantity < 1) {
                throw new \DomainException('Quantité d’unités de combat invalide.');
            }
            $total += $quantity;
            if ($total > self::MAX_UNITS) {
                throw new \DomainException('Combat trop volumineux.');
            }
            $slug = $definition['slug'] ?? '';
            if (!is_string($slug) || $slug === '') {
                throw new \DomainException('Type d’unité de combat invalide.');
            }
            $stats = [];
            foreach (['hp', 'precision', 'tracking', 'evasion', 'signature', 'base_attack'] as $stat) {
                $value = $definition[$stat] ?? null;
                if (!is_numeric($value) || !is_finite((float) $value) || (in_array($stat, ['hp'], true) && $value <= 0) || (in_array($stat, ['precision', 'tracking', 'evasion', 'signature', 'base_attack'], true) && $value < 0)) {
                    throw new \DomainException('Statistique de combat invalide : ' . $stat . '.');
                }
                $stats[$stat] = (float) $value;
            }
            $stats['tier'] = (string) ($definition['tier'] ?? 'basic');
            $shield = $definition['shield'] ?? 0;
            if (!is_numeric($shield) || !is_finite((float) $shield) || $shield < 0) {
                throw new \DomainException('Statistique de combat invalide : shield.');
            }
            $shotsMin = $definition['shots_min'] ?? null;
            $shotsMax = $definition['shots_max'] ?? null;
            if (!is_int($shotsMin) || !is_int($shotsMax) || $shotsMin < 1 || $shotsMax < $shotsMin || $shotsMax > 100) {
                throw new \DomainException('Nombre de tirs invalide.');
            }
            $secondary=[];
            if(isset($definition['secondary_attack'])){
                $attack=$definition['secondary_attack'];
                $min=$definition['secondary_shots_min']??0;$max=$definition['secondary_shots_max']??$min;
                $precision=$definition['secondary_precision_modifier']??0;$category=$definition['secondary_target_category']??null;
                if(!is_numeric($attack)||!is_finite((float)$attack)||$attack<0||!is_int($min)||!is_int($max)||$min<0||$max<$min||$max>100||!is_int($precision)||!is_string($category)||$category==='')throw new \DomainException('Arme secondaire invalide.');
                $secondary=['secondary_attack'=>(float)$attack,'secondary_shots_min'=>$min,'secondary_shots_max'=>$max,'secondary_precision_modifier'=>$precision,'secondary_target_category'=>$category];
            }
            for ($index = 1; $index <= $quantity; ++$index) {
                $tier=(string)($definition['tier']??'basic');
                $tierSuffix=$tier==='basic'?'':':'.$tier;
                $key=$owner.':'.$role.':'.$slug.$tierSuffix.':'.$index;
                if (isset($units[$key])) {
                    throw new \DomainException('Identifiant d’unité de combat dupliqué.');
                }
                $units[$key] = $stats + $secondary + [
                    'category' => (string)($definition['category']??''),
                    'initial_hp' => $stats['hp'],
                    'shield' => (float) $shield,
                    'max_shield' => (float) $shield,
                    'owner' => $owner,
                    'role' => $role,
                    'camp' => $camp,
                    'slug' => $slug,
                    'tier' => $tier,
                    'arrival_round' => $arrivalRound,
                    'shots_min' => $shotsMin,
                    'shots_max' => $shotsMax,
                    'extra_shot_chances' => $this->validatedExtraShotChances($definition['extra_shot_chances'] ?? []),
                ];
            }
        }
    }

    private function validatedExtraShotChances(array $chances): array
    {
        foreach($chances as $chance)if(!is_numeric($chance)||!is_finite((float)$chance)||(float)$chance<0||(float)$chance>1)throw new \DomainException('Chance de tir supplémentaire invalide.');
        return array_map('floatval',$chances);
    }

    private function validateModifiers(array $modifiers): void
    {
        foreach ($modifiers as $byAttacker) {
            if (!is_array($byAttacker)) {
                throw new \DomainException('Matrice de combat invalide.');
            }
            foreach ($byAttacker as $modifier) {
                if (!is_numeric($modifier) || !is_finite((float) $modifier) || $modifier <= 0 || $modifier > 10) {
                    throw new \DomainException('Coefficient de contre invalide.');
                }
            }
        }
    }

    private function targetsFor(string $attackerKey, array $units, int $roundNumber, bool $independentCamps = false): array
    {
        $attacker = $units[$attackerKey];
        $targets = [];
        foreach ($units as $key => $target) {
            if ($key === $attackerKey || $target['hp'] <= 0 || $target['arrival_round'] > $roundNumber) {
                continue;
            }
            $hostile = $independentCamps ? $target['camp'] !== $attacker['camp'] : match ($attacker['role']) {
                'static_defense' => $target['role'] === 'attacker',
                'attacker' => $target['role'] === 'defender' || $target['role'] === 'static_defense' || ($target['role'] === 'attacker' && $target['camp'] !== $attacker['camp']),
                default => $target['role'] === 'attacker',
            };
            if ($hostile) {
                $targets[] = $key;
            }
        }

        return $targets;
    }

    private function countAlive(array $units, string $role, int $roundNumber): int
    {
        $count = 0;
        foreach ($units as $unit) {
            if ($unit['role'] === $role && $unit['hp'] > 0 && $unit['arrival_round'] <= $roundNumber) {
                ++$count;
            }
        }

        return $count;
    }

    private function countFuture(array $units, string $role, int $roundNumber): int
    {
        $count = 0;
        foreach ($units as $unit) {
            if ($unit['role'] === $role && $unit['hp'] > 0 && $unit['arrival_round'] > $roundNumber) {
                ++$count;
            }
        }

        return $count;
    }

    private function losses(array $units): array
    {
        $losses = [];
        foreach ($units as $unit) {
            if ($unit['hp'] > 0) {
                continue;
            }
            $key=$unit['owner'].':'.$unit['slug'].(($unit['tier']??'basic')==='basic'?'':':'.$unit['tier']);
            $losses[$key] = ($losses[$key] ?? 0) + 1;
        }
        ksort($losses);

        return $losses;
    }
}
