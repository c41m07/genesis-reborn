<?php
namespace App\Service\Search;

final class SearchCriteria
{
    public array $values=[];
    public ?string $since=null;
    public ?string $before=null;

    public function __construct(array $query,string $timezone='Europe/Paris')
    {
        foreach(['q'=>150,'planet'=>80,'participant'=>80,'from'=>10,'until'=>10] as $key=>$limit) {
            $value=$query[$key]??'';
            if(!is_string($value) || mb_strlen($value)>$limit) throw new \DomainException('Filtre invalide ou trop long : '.$key.'.');
            $this->values[$key]=trim($value);
        }
        $zone=new \DateTimeZone($timezone);$utc=new \DateTimeZone('UTC');$dates=[];
        foreach(['from','until'] as $key) if($this->values[$key]!=='') {
            $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$this->values[$key],$zone);
            if(!$date || $date->format('Y-m-d')!==$this->values[$key]) throw new \DomainException('Date invalide : utilisez une date du calendrier.');
            $dates[$key]=$date;
        }
        if(isset($dates['from'],$dates['until']) && $dates['from']>$dates['until']) throw new \DomainException('La fin de période doit suivre son début.');
        $this->since=isset($dates['from'])?$dates['from']->setTimezone($utc)->format('Y-m-d H:i:s'):null;
        $this->before=isset($dates['until'])?$dates['until']->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s'):null;
    }

    public static function pattern(string $value): string
    {
        return '%'.str_replace(['!','%','_'],['!!','!%','!_'],mb_strtolower($value)).'%';
    }

    public function url(): array { return array_filter($this->values,static fn(string $value)=>$value!==''); }
}
