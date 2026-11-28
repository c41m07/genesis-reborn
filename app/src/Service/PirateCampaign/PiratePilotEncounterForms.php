<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\Planet;
use App\Service\Catalog\GameCatalog;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\Security\Csrf\{CsrfToken, CsrfTokenManagerInterface};

/** Adaptateur de présentation des devis : aucun contrôleur ni route et aucune commande exécutée. */
#[When(env: 'dev')]
#[When(env: 'prod')]
final readonly class PiratePilotEncounterForms
{
    public function __construct(
        private PiratePilotScoutCommand $scout,
        private PiratePilotAttackCommand $attack,
        private CsrfTokenManagerInterface $csrf,
        private GameCatalog $catalog,
    ) {
    }

    public function scoutConfirmation(Planet $origin, int $mission): ?array
    {
        $plan = $this->scout->preview($origin, $mission);
        if ($plan === null) {
            return null;
        }

        $probe = $this->catalog->getTierForUniverse($origin->solarSystem->galaxy->universe, 'ships', 'probe', $plan['quote']['tier']);

        return self::confirmationCard(PiratePilotCommandAction::Reconnaissance, $plan['quote'],
            $this->csrf->getToken($plan['intent']->csrfId())->getValue()) + [
                'notice' => sprintf('Vous perdrez une sonde de rang %s (%s) lors de cette reconnaissance.', $probe['_tier_label'], $probe['label']),
            ];
    }

    public function attackChoices(Planet $origin, int $mission): ?array
    {
        $state = $this->attack->options($origin, $mission);
        if ($state === null) {
            return null;
        }
        $options = $state['options'];
        $universe = $origin->solarSystem->galaxy->universe;
        $definitions = $this->catalog->forUniverse($universe)['catalog']['ships'];
        $ships = [];
        $catalog = [];
        for ($ordinal = 1; $ordinal <= PiratePilotCommandOptions::MAX_OPTIONS; ++$ordinal) {
            $key = $options->escortKey($ordinal);
            if ($key === null) {
                break;
            }
            [$slug, $tier] = explode(':', $key, 2);
            // Le tableau partage le rendu de Flottes, mais ne reçoit que les choix
            // d’escorte autorisés et conserve leur ordinal couvert par le jeton.
            $ships[] = [
                'slug' => $slug,
                'tier' => $tier,
                'quantity' => $options->maxEscortQuantity($ordinal),
                'ordinal' => $ordinal,
            ];
            $catalog[$slug] = $definitions[$slug];
        }

        return [
            'kind' => 'selection', 'action' => PiratePilotCommandAction::Attack->value,
            'ships' => $ships, 'catalog' => $catalog, 'total_limit' => $options->fleetLimit,
            'token' => $this->csrf->getToken($this->previewId($origin, $mission, $state))->getValue(),
        ];
    }

    /** Prépare une confirmation à partir d'un formulaire d'aperçu, sans écrire dans le jeu. */
    public function attackConfirmation(Planet $origin, int $mission, ?array $submitted, string $previewToken): ?array
    {
        $state = $this->attack->options($origin, $mission);
        if ($state === null || !$this->csrf->isTokenValid(new CsrfToken($this->previewId($origin, $mission, $state), $previewToken))) {
            return null;
        }
        $plan = $this->attack->preview($origin, $mission, $submitted);
        if ($plan === null) {
            return null;
        }

        // Seuls les ordinaux validés par le devis de la véritable commande sont resoumis.
        // Le writer recalculera son propre devis ; aucune donnée du tableau d'affichage n'est un ordre.
        $selected = [];
        foreach ($plan['selection']->composition as $slug => $tiers) {
            foreach ($tiers as $tier => $quantity) {
                $profile = $this->catalog->getTierForUniverse($origin->solarSystem->galaxy->universe, 'ships', $slug, $tier);
                $selected[] = ['label' => $profile['label'], 'quantity' => $quantity];
            }
        }

        return self::confirmationCard(PiratePilotCommandAction::Attack, $plan['quote'],
            $this->csrf->getToken($plan['intent']->csrfId())->getValue(), $submitted['escort']) + ['selected' => $selected];
    }

    /** Liste fermée de données affichables. Ne projette ni preuve, ni composition catalogue, ni champ CSRF d'identité. */
    public static function confirmationCard(PiratePilotCommandAction $action, array $nativeQuote, string $token, array $ordinals = []): array
    {
        if (!in_array($action, [PiratePilotCommandAction::Reconnaissance, PiratePilotCommandAction::Attack], true)
            || !is_int($nativeQuote['seconds'] ?? null) || $nativeQuote['seconds'] < 1 || $token === ''
            || count($ordinals) > PiratePilotCommandOptions::MAX_OPTIONS
            || ($action === PiratePilotCommandAction::Attack) === ($ordinals === [])) {
            throw new \LogicException('La confirmation n’a pas de devis autorisé.');
        }
        $schema = ['hydrogen', 'hold', 'planet'];
        if ($action === PiratePilotCommandAction::Attack) {
            $schema[] = 'orbit';
        }
        $quote = ['seconds' => $nativeQuote['seconds']];
        foreach ($schema as $field) {
            $amount = $nativeQuote[$field] ?? null;
            if ((!is_int($amount) && !is_float($amount)) || !is_finite($amount) || $amount < 0.0) {
                throw new \LogicException('Montant du devis non affichable.');
            }
            $quote[$field] = (float) $amount;
        }
        foreach ($ordinals as $ordinal => $amount) {
            if (!is_int($ordinal) || $ordinal < 1 || $ordinal > PiratePilotCommandOptions::MAX_OPTIONS
                || !is_string($amount) || preg_match('/^(?:0|[1-9][0-9]{0,9})$/D', $amount) !== 1
                || (int) $amount > PiratePilotCommandOptions::MAX_QUANTITY) {
                throw new \LogicException('Seuls les ordinaux et nombres validés peuvent être affichés.');
            }
        }
        ksort($ordinals, SORT_NUMERIC);

        return [
            'kind' => 'confirmation', 'action' => $action->value, 'quote' => $quote,
            'hidden_escort' => $ordinals, 'token' => $token,
        ];
    }

    private function previewId(Planet $origin, int $mission, array $state): string
    {
        // Jeton d'aperçu distinct du jeton final, qui scelle, lui, la composition choisie.
        return implode(':', ['pirate-pilot-preview-v1', $origin->owner->player->id, $origin->owner->id, $origin->id, $mission,
            $state['attempt'], $state['options']->fingerprint()]);
    }
}
