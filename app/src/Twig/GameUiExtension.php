<?php
namespace App\Twig;
use App\Service\Colony\{CatalogFilters, CatalogueItemLink, GameShell};
use App\Entity\{Player, PlayerUniverse};
use App\Service\PirateCampaign\{PirateCampaignTraffic, PiratePilotGate};
use Psr\Clock\ClockInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
final class GameUiExtension extends AbstractExtension
{
    public function __construct(private GameShell $shell, private \App\Service\Universe\UniverseClosingClock $closingClock, private CatalogueItemLink $catalogueLinks,
        private PiratePilotGate $pirateCampaign, private ClockInterface $clock, private PirateCampaignTraffic $pirateTraffic,
        private \App\Service\Boost\TimedBoostEffects $timedBoosts, private CatalogFilters $filters) {}
    public function getFunctions():array
    {
        return [new TwigFunction('ship_table_groups', $this->filters->shipTableGroups(...)),
            new TwigFunction('catalog_table_groups', $this->filters->groupedDefinitions(...)),
            new TwigFunction('catalog_family_icon', $this->filters->familyIcon(...)),
            new TwigFunction('ship_category_icon', $this->filters->shipIcon(...)),
            new TwigFunction('active_timed_bonus', $this->timedBoosts->activeBonus(...)),
            new TwigFunction('universe_closing', [$this->closingClock, 'snapshot']),new TwigFunction('game_shell',[$this->shell,'get']),new TwigFunction('pirate_campaign_available', $this->pirateCampaignAvailable(...)),new TwigFunction('pirate_campaign_traffic', [$this->pirateTraffic,'forViewer']),new TwigFunction('catalogue_item_url',[$this->catalogueLinks,'focus']),new TwigFunction('duration_label',fn(int $s)=>($s>=3600?intdiv($s,3600).' h ':'').($s>=60?intdiv($s%3600,60).' min ': '').($s%60).' s')];
    }

    public function pirateCampaignAvailable(?Player $account, ?PlayerUniverse $membership): bool
    {
        return $this->pirateCampaign->admits($account, $membership, $this->clock->now());
    }
}
