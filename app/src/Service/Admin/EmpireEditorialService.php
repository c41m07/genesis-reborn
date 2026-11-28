<?php
declare(strict_types=1);
namespace App\Service\Admin;

use App\Entity\{Player,PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Player\{EmpireActivityService,EmpireConversionService,ProfileCosmetics};
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final readonly class EmpireEditorialService
{
    public function __construct(private GameManager $manager, private EditorialActions $audit, private GameRepository $repo,
        private EntityManagerInterface $em, private ProfileCosmetics $cosmetics, private EmpireActivityService $activity,
        private EmpireConversionService $conversion, private ClockInterface $clock) {}

    public function preview(PlayerUniverse $member): array
    {
        $state = $this->activity->snapshot($member);
        $inventory = $this->repo->forgottenEmpirePreviewInventory($member);
        return ['state'=>$state,'inventory'=>$inventory,'days'=>intdiv($this->activity->inactiveSeconds($member) ?? 0,86400),
            'signature'=>hash('sha256',serialize([$member->id,$member->player?->id,$member->lastActivityAt,$member->manualInactiveAt,$state,$inventory]))];
    }

    public function change(Player $actor, int $playerId, PlayerUniverse $member, string $action, string $reason, string $signature): void
    {
        $this->manager->transaction(function () use ($actor,$playerId,$member,$action,$reason,$signature): void {
            $this->manager->lockCurrent($member);
            $this->manager->lockCurrent($member->universe);
            $this->audit->authorizeLocked($actor);
            if (!$member->isPlayerControlled() || $member->player->id !== $playerId) throw new \DomainException('La participation a changé.');
            $preview = $this->preview($member);
            if (!hash_equals($preview['signature'],$signature)) throw new \DomainException('L’état de l’empire a changé. Vérifiez le nouvel aperçu.');
            if (trim($reason)==='' || mb_strlen($reason)>500) throw new \DomainException('Indiquez un motif de 1 à 500 caractères.');
            if ($action === 'convert') {
                $result = $this->conversion->convertManually($member);
                if ($result['status'] !== 'converted') throw new \DomainException('Conversion impossible pendant les vacances ou un combat.');
            } elseif ($action === 'inactive' && $preview['state']['status'] !== 'vacation') {
                $member->manualInactiveAt = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
            } elseif ($action === 'clear') {
                $member->manualInactiveAt = null;
            } else {
                throw new \DomainException('Changement de statut indisponible.');
            }
            $this->audit->log($actor,'empire_'.$action,'player_universe',$member->id,['reason'=>trim($reason),'universe'=>$member->universe->id]);
        });
    }

    public function award(Player $actor, Player $target, array $codes, string $operation): void
    {
        $this->manager->transaction(function () use ($actor,$target,$codes,$operation): void {
            $this->audit->authorizeLocked($actor);
            $this->manager->lockCurrent($target);
            if (!$codes || !in_array($operation,['grant','remove'],true)) throw new \DomainException('Sélectionnez les éléments à modifier.');
            $definitions = $this->cosmetics->definitions();
            foreach ($codes as $code) {
                if (!is_string($code) || !isset($definitions[$code])) throw new \DomainException('Cosmétique inconnu.');
            }
            if ($operation === 'grant') {
                $this->cosmetics->grantLocked($target,$codes,$this->clock->now());
            } else {
                foreach ($codes as $code) $this->em->getConnection()->delete('profile_cosmetic',['player_id'=>$target->id,'code'=>$code]);
            }
            $this->audit->log($actor,'cosmetics_'.$operation,'player',$target->id,['codes'=>$codes]);
        });
    }
}
