<?php
namespace App\Command;

use App\Entity\Universe;
use App\Service\Catalog\GameCatalog;
use App\Service\Fleet\MovementCalculator;
use App\Service\Queue\OperationCalculator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name:'app:balance:compare-operations',description:'Compare les durées, la taxe électrique et le carburant avec les calculateurs réels, sans écriture en base.')]
final class CompareProgressionOperationsCommand extends Command
{
    public function __construct(
        private GameCatalog $catalog,
        private OperationCalculator $operations,
        private MovementCalculator $movement,
        #[Autowire('%kernel.project_dir%/config/legacy/progression-operations-2026-11-15.json')] private string $referencePath,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input,OutputInterface $output): int
    {
        $reference=json_decode(file_get_contents($this->referencePath),true,512,JSON_THROW_ON_ERROR);
        // Un univers transitoire ×1 évite toute lecture de compte ou personnalisation persistée.
        $universe=new Universe();$universe->slug='progression-measure-x1';$universe->name='Mesure ×1';
        $config=$this->catalog->forUniverse($universe);
        $report=[
            'reference_revision'=>$reference['source_revision'],
            'hypotheses'=>['Univers ×1 ; niveau 1 ou une unité ; aucun événement.',
                'Table principale sans réduction ; stocks et financement non simulés.',
                'Sans bonus est une référence théorique ; prérequis et financement sont vérifiés séparément.',
                'Profils historiques rejoués avec leurs anciennes durées et le calculateur courant.',
                'Maintien uniquement pour les missions qui le facturent déjà ; aucun coût ajouté au stationnement.',
                'Pas de validation de l’équilibre économique ou militaire global.'],
            'operations'=>[], 'colonization_queue'=>[], 'fuel'=>[],
        ];
        foreach ($config['catalog'] as $group=>$definitions) {
            $field=match($group){'buildings'=>'base_build_time','technologies'=>'research_time_base',default=>'build_time_base'};
            foreach ($definitions as $slug=>$base) {
                foreach (GameCatalog::TIERS as $tier) {
                    $definition=$this->catalog->withTier($base,$tier);
                    $current=$this->operations->calculate($definition,$group,1,1,[],$config);
                    $old=null;
                    if (isset($reference['durations'][$group][$slug])) {
                        $previous=$definition;$previous[$field]=$reference['durations'][$group][$slug];
                        $old=$this->operations->calculate($previous,$group,1,1,[],$config);
                    }
                    $report['operations'][]=['group'=>$group,'slug'=>$slug,'tier'=>$tier,
                        'old_seconds'=>$old['duration']??null,'seconds'=>$current['duration'],
                        'old_electricity'=>$old===null?null:($old['costs']['electricity']??0),'electricity'=>$current['costs']['electricity']??0];
                }
            }
        }
        foreach (['none'=>[], 'basic_lab_2'=>['basic'=>2], 'basic_lab_25'=>['basic'=>25],
            'maximum'=>array_fill_keys(GameCatalog::TIERS,25)] as $scenario=>$levels) {
            $buildings=['lab'=>$levels];
            $colony=$this->operations->calculate($this->catalog->getTierForUniverse($universe,'technologies','colonial_expansion','basic'),
                'technologies',1,1,$buildings,$config);
            $next=$this->operations->calculate($this->catalog->getTierForUniverse($universe,'technologies','engineering_foundations','basic'),
                'technologies',3,1,$buildings,$config);
            $report['colonization_queue'][]=['scenario'=>$scenario,'seconds'=>$colony['duration'],
                'electricity'=>$colony['costs']['electricity'],'following_research_level'=>3,
                'following_seconds'=>$next['duration'],'combined_seconds'=>$colony['duration']+$next['duration']];
        }
        $origin=[1,1,0,0,5];
        foreach ($config['catalog']['ships'] as $slug=>$base) {
            // Une unité fixe n'est jamais inventée comme vaisseau mobile pour la mesure.
            if (($base['speed_system']??0)<=0 || ($base['speed_galaxy']??0)<=0) continue;
            foreach (GameCatalog::TIERS as $tier) {
                foreach ([['single_near',1,1,1,0],['fleet_far',20,10,1,0],
                    ['half_speed',20,10,.5,0],['orbit_two_hours',1,1,1,2]] as [$scenario,$quantity,$distance,$ratio,$orbit]) {
                    $ship=$this->catalog->withTier($base,$tier)+['quantity'=>$quantity];
                    $target=[2,1,$distance,0,5];
                    $current=$this->movement->quote($origin,$target,[$ship],$ratio,1,'proportional',$orbit);
                    $old=null;
                    if (isset($reference['fuel_profiles'][$slug])) {
                        $previous=array_replace($ship,$reference['fuel_profiles'][$slug]);
                        $old=$this->movement->quote($origin,$target,[$previous],$ratio,1,'proportional',$orbit);
                    }
                    $report['fuel'][]=['scenario'=>$scenario,'slug'=>$slug,'tier'=>$tier,'quantity'=>$quantity,
                        'coordinate_distance'=>$distance,'speed_ratio'=>$ratio,'orbit_hours'=>$orbit,
                        'old_hydrogen'=>$old['total_fuel']??null,'hydrogen'=>$current['total_fuel'],
                        'seconds'=>$current['seconds'],'fuel_capacity'=>$current['fuel_capacity']];
                }
            }
        }
        $output->writeln(json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        return Command::SUCCESS;
    }
}
