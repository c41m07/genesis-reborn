<?php
declare(strict_types=1);
namespace App\Service\Admin;

use App\Entity\{EditorialRevision,Player,Universe};
use App\Manager\GameManager;
use App\Repository\EditorialRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\PirateCampaign\PirateCampaignCatalogue;
use Psr\Clock\ClockInterface;

final readonly class PirateMissionEditor
{
    public function __construct(private PirateCampaignCatalogue $campaign, private GameCatalog $catalog, private EditorialActions $actions,
        private GameManager $manager, private ClockInterface $clock, private EditorialRepository $library) {}

    public function illustrations(Universe $world): array
    {
        $art = ['art/genesis-reborn-planet.webp'=>'Planète de la campagne'] + EditorialLibrary::ART;
        foreach ([...$this->library->published('banner',null),...$this->library->published('page',$world)] as $content) $art[$content['art']] = $content['label'];
        return $art;
    }

    public function prepare(Player $admin, Universe $world, array $input): EditorialRevision
    {
        foreach (['title','signal','victory','starts','ends','art'] as $key) {
            if (isset($input[$key]) && !is_string($input[$key])) throw new \DomainException('Champ de mission invalide.');
        }
        if (!is_array($input['waves'] ?? null)) throw new \DomainException('Formation attendue.');
        $title = trim((string)($input['title'] ?? ''));
        $signal = trim((string)($input['signal'] ?? ''));
        $victory = trim((string)($input['victory'] ?? ''));
        if (!$title || mb_strlen($title)>90 || !$signal || !$victory || max(mb_strlen($signal),mb_strlen($victory))>1500) throw new \DomainException('Renseignez un titre (90 caractères), un appel et une conclusion (1 500 caractères maximum).');
        $start = $this->date($input['starts'] ?? '');
        $end = $this->date($input['ends'] ?? '');
        if ($end && $end <= ($start ?? $this->clock->now())) throw new \DomainException('La fin doit suivre le début.');
        $art = $input['art'] ?? '';
        if (!isset($this->illustrations($world)[$art])) throw new \DomainException('Illustration non disponible.');
        $reward = null;
        if (isset($input['customReward'])) {
            foreach (['metal','nano','hydrogen','antimatter'] as $key) {
                $amount = filter_var($input['reward'][$key] ?? 0,FILTER_VALIDATE_INT);
                if ($amount === false || $amount<0 || $amount>1000000000) throw new \DomainException('Quantité de récompense invalide.');
                $reward[$key] = $amount;
            }
        }
        $waves = [];
        foreach ([0,1,2] as $index) {
            $ships = [];
            if (isset($input['waves'][$index]) && !is_array($input['waves'][$index])) throw new \DomainException('Vague invalide.');
            foreach ($input['waves'][$index] ?? [] as $slug=>$tiers) {
                if (!isset($this->catalog->all('ships')[$slug]) || !is_array($tiers)) throw new \DomainException('Vaisseau inconnu.');
                foreach ($tiers as $tier=>$value) {
                    if ($value === '' || $value === '0') continue;
                    $n = filter_var($value,FILTER_VALIDATE_INT);
                    if (!in_array($tier,GameCatalog::TIERS,true) || !$n || $n<1) throw new \DomainException('Effectif ou rang incorrect.');
                    $ships[$slug][$tier] = $n;
                }
            }
            if ($ships) $waves[] = $ships;
        }
        if (!$waves) throw new \DomainException('Ajoutez au moins un vaisseau.');
        $code = 'mission_'.bin2hex(random_bytes(8));
        $entries = [];
        $simulation = clone $world;
        foreach ($waves as $i=>$ships) {
            $key = $code.'_'.($i+1);
            $entries[$key] = ['title'=>$title.(count($waves)>1 ? ' · vague '.($i+1) : ''),'signal'=>$signal,'victory'=>$victory,
                'ships'=>$ships,'art'=>$art,'starts'=>$start?->format(DATE_ATOM),'ends'=>$end?->format(DATE_ATOM),'reward'=>$reward];
            $simulation->gameSettings['pirate_missions'][$key] = $entries[$key];
            $this->campaign->prepareForServer($key,$simulation);
        }
        return $this->manager->transaction(function () use ($admin,$world,$code,$title,$entries): EditorialRevision {
            $this->actions->authorizeLocked($admin);
            $r = new EditorialRevision();
            $r->kind = 'pirate';
            $r->code = $code;
            $r->universe = $world;
            $r->author = $admin;
            $r->content = ['label'=>$title,'waves'=>$entries];
            $r->createdAt = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
            $this->manager->save($r);
            $this->manager->flush();
            $this->actions->log($admin,'pirate_draft','editorial_revision',$r->id,['universe'=>$world->id]);
            return $r;
        });
    }

    public function publish(Player $actor, Universe $world, EditorialRevision $revision): void
    {
        $this->manager->transaction(function () use ($actor,$world,$revision): void {
            $this->manager->lockCurrent($world);
            $this->actions->authorizeLocked($actor);
            $this->manager->lockCurrent($revision);
            if ($revision->kind !== 'pirate' || $revision->universe?->id !== $world->id) throw new \DomainException('Brouillon incompatible avec cet univers.');
            if ($revision->publishAt) return;
            foreach ($revision->content['waves'] as $key=>$wave) {
                $world->gameSettings['pirate_missions'][$key] = $wave;
                $this->campaign->prepareForServer($key,$world);
            }
            $revision->publishAt = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
            $this->actions->log($actor,'pirate_published','editorial_revision',$revision->id,['universe'=>$world->id]);
        });
    }

    private function date(string $value): ?\DateTimeImmutable
    {
        if ($value === '') return null;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$value,new \DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d\TH:i') !== $value) throw new \DomainException('Date UTC invalide.');
        return $date;
    }
}
