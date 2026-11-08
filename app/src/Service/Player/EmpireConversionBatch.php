<?php
namespace App\Service\Player;

use App\Entity\PlayerUniverse;
use App\Repository\GameRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Un lot borné ; chaque conversion possède sa transaction et ses contrôles sous verrou. */
final class EmpireConversionBatch
{
    public function __construct(private ForgottenEmpirePreviewService $preview,private EmpireConversionService $conversions,
        private GameRepository $repo,private LoggerInterface $logger,
        #[Autowire('%env(bool:FORGOTTEN_EMPIRES_ENABLED)%')] private bool $enabled) {}

    public function run(bool $apply=false,int $limit=100,int $afterId=0): array
    {
        if($limit<1 || $limit>500 || $afterId<0) throw new \InvalidArgumentException('Limite attendue : 1 à 500 ; curseur positif ou zéro.');
        if($apply && !$this->enabled) throw new \DomainException('Les conversions J30 sont désactivées ; aucune conversion appliquée.');
        $preview=$this->preview->preview($limit,$afterId);
        $result=['preview'=>!$apply,'observed_at'=>$preview['observed_at'],'eligible'=>count($preview['empires']),
            'converted'=>0,'deferred'=>0,'skipped'=>0,'failed'=>0,'failed_membership_id'=>null,
            'last_scanned_id'=>$afterId,'batch_full'=>$preview['batch_full'],'empires'=>[]];
        foreach($preview['empires'] as $empire) {
            $id=$empire['membership_id'];
            $status=$empire['inventory']['open_combats']>0?'deferred_combat':'eligible';
            if($apply) {
                try {
                    $membership=$this->repo->find(PlayerUniverse::class,$id);
                    $status=$membership===null?'not_found':$this->conversions->convert($membership)['status'];
                } catch(\Throwable $error) {
                    $result['failed']=1;$result['failed_membership_id']=$id;
                    $result['empires'][]=['membership_id'=>$id,'universe'=>$empire['universe'],'due_at'=>$empire['due_at'],'status'=>'failed'];
                    $this->logger->error('Conversion J30 interrompue ; reprendre après le dernier identifiant traité.',[
                        'membership_id'=>$id,'after_id'=>$result['last_scanned_id'],'exception'=>$error]);
                    // Après un rollback Doctrine peut fermer son gestionnaire : ne pas poursuivre le lot.
                    return $result;
                }
                if($status==='converted') ++$result['converted'];
                elseif($status==='deferred_combat') ++$result['deferred'];
                else ++$result['skipped'];
            } elseif($status==='deferred_combat') ++$result['deferred'];
            $result['empires'][]=['membership_id'=>$id,'universe'=>$empire['universe'],'due_at'=>$empire['due_at'],'status'=>$status];
            $result['last_scanned_id']=$id;
        }
        $result['last_scanned_id']=$preview['last_scanned_id'];
        return $result;
    }
}
