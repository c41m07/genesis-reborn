<?php
namespace App\Service\Player;

final class GuildProjectRules
{
    public const MAX_STAGES = 10;
    public const MAX_STAGE_AMOUNT = 1000000000;

    public function definition(array $draft,array $labels): array
    {
        $name=$draft['name']??'';$description=$draft['description']??'';
        if(!is_string($name) || mb_strlen(trim($name))<1 || mb_strlen(trim($name))>120) throw new \DomainException('Nom du projet : 1 à 120 caractères.');
        if(!is_string($description) || mb_strlen($description)>2000) throw new \DomainException('Description : 2 000 caractères maximum.');
        $input=$draft['stages']??[];
        if(!is_array($input) || count($input)<1 || count($input)>self::MAX_STAGES) throw new \DomainException('Prévoyez de 1 à 10 paliers.');
        $stages=[];$totals=[];
        foreach(array_values($input) as $index=>$stage) {
            $label=is_array($stage)?($stage['name']??''):'';
            if(!is_string($label) || mb_strlen(trim($label))<1 || mb_strlen(trim($label))>80) throw new \DomainException('Chaque palier doit avoir un nom de 1 à 80 caractères.');
            if(!is_array($stage['costs']??null)) throw new \DomainException('Coûts du palier invalides.');
            try { $costs=$this->quantities($stage['costs'],$labels,self::MAX_STAGE_AMOUNT); }
            catch(\DomainException $error) { throw new \DomainException('Palier '.($index+1).' : '.$error->getMessage(),0,$error); }
            foreach($costs as $resource=>$amount) $totals[$resource]=($totals[$resource]??0)+$amount;
            $stages[]=['name'=>trim($label),'costs'=>$costs];
        }
        if(!is_array($draft['minimums']??null)) throw new \DomainException('Minimums de contribution invalides.');
        try { $minimums=$this->quantities($draft['minimums'],$labels,self::MAX_STAGE_AMOUNT*self::MAX_STAGES); }
        catch(\DomainException $error) { throw new \DomainException('Minimums de récompense : '.$error->getMessage(),0,$error); }
        foreach($minimums as $resource=>$minimum) if($minimum>($totals[$resource]??0)) throw new \DomainException('Le minimum de '.$labels[$resource].' dépasse le coût total du projet.');

        return ['name'=>trim($name),'description'=>trim($description),'stages'=>$stages,'minimums'=>$minimums];
    }

    public function quantities(array $input,array $labels,int $maximum): array
    {
        $result=[];
        foreach($input as $resource=>$raw) {
            if(!is_string($resource) || !array_key_exists($resource,$labels)) throw new \DomainException('Ressource inconnue.');
            if($raw==='') $raw=0;
            if((!is_string($raw) && !is_int($raw)) || ($value=filter_var($raw,FILTER_VALIDATE_INT))===false || $value<0 || $value>$maximum) throw new \DomainException('Quantité entière invalide pour '.$labels[$resource].'.');
            if($value>0) $result[$resource]=$value;
        }
        if($result===[]) throw new \DomainException('Indiquez au moins une quantité strictement positive.');

        return $result;
    }

    public function remaining(array $costs,array $progress): array
    {
        $remaining=[];
        foreach($costs as $resource=>$amount) $remaining[$resource]=max(0,$amount-($progress[$resource]??0));

        return $remaining;
    }

    public function eligible(array $contributed,array $minimums): bool
    {
        if($minimums===[]) return false;
        foreach($minimums as $resource=>$minimum) if(($contributed[$resource]??0)<$minimum) return false;

        return true;
    }

    public function maximumRecipients(array $stages,array $minimums): int
    {
        if($minimums===[]) return 0;
        $totals=[];
        foreach($stages as $stage) foreach($stage['costs'] as $resource=>$amount) $totals[$resource]=($totals[$resource]??0)+$amount;
        $limits=[];
        foreach($minimums as $resource=>$amount) $limits[]=intdiv($totals[$resource]??0,$amount);

        return min($limits);
    }
}
