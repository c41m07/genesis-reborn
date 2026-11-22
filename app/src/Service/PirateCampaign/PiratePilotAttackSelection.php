<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** Choix exacts résolus en mémoire depuis les ordinaux autorisés, jamais des slugs clients. */
#[Exclude]
final readonly class PiratePilotAttackSelection
{
    private function __construct(
        private PiratePilotCommandOptions $options,
        public array $composition,
    ) {
    }

    public static function fromSubmission(?array $submitted, PiratePilotCommandOptions $options): ?self
    {
        if ($options->action !== PiratePilotCommandAction::Attack) {
            throw new \InvalidArgumentException('Des options d’escorte autorisées sont requises.');
        }
        $input = PiratePilotCommandInput::parse($submitted, $options);
        if ($input === null) {
            return null;
        }

        $composition = [];
        foreach ($input->escortQuantities as $ordinal => $quantity) {
            [$slug, $tier] = explode(':', $options->escortKey($ordinal), 2);
            $composition[$slug][$tier] = $quantity;
        }
        ksort($composition, SORT_STRING);
        foreach ($composition as &$tiers) {
            ksort($tiers, SORT_STRING);
        }
        unset($tiers);

        return new self($options, $composition);
    }

    /**
     * Le rapport, l'état de tentative, la cible et les profils moteur sont scellés par le futur
     * service dans $worldProof, relus sous verrou avant le devis et jamais pris dans le formulaire.
     * @param array{seconds:int, hydrogen:float, orbit:float, hold:float, planet:float} $nativeQuote
     */
    public function confirmation(
        int $account,
        int $membership,
        int $origin,
        int $missionNumber,
        int $expectedAttempt,
        string $worldProof,
        array $nativeQuote,
    ): PiratePilotCommandIntent {
        $quote = [];
        if (preg_match('/^[a-f0-9]{64}$/D', $worldProof) !== 1 || count($nativeQuote) !== 5
            || !is_int($nativeQuote['seconds'] ?? null) || $nativeQuote['seconds'] < 1) {
            throw new \InvalidArgumentException('Preuve ou durée du devis interne invalide.');
        }
        $quote['seconds'] = $nativeQuote['seconds'];
        foreach (['hydrogen', 'orbit', 'hold', 'planet'] as $key) {
            $value = $nativeQuote[$key] ?? null;
            if ((!is_int($value) && !is_float($value)) || !is_finite($value) || $value < 0.0) {
                throw new \InvalidArgumentException('Montants natifs du devis incomplets.');
            }
            $quote[$key] = (float) $value;
        }
        if ($quote['orbit'] > $quote['hydrogen'] || $quote['hold'] > $quote['hydrogen']
            || $quote['planet'] !== $quote['hydrogen'] - $quote['hold']) {
            throw new \InvalidArgumentException('La réserve de l’attaque et ses sources ne concordent pas.');
        }
        // Deux compositions de même prix doivent produire deux identifiants CSRF différents.
        // Aucun instant de page n’entre dans le digest, uniquement des faits stables du devis.
        $version = hash('sha256', json_encode([
            'version' => 1, 'composition' => $this->composition, 'quote' => $quote, 'world' => $worldProof,
        ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

        return new PiratePilotCommandIntent(
            $account, $membership, $origin, $missionNumber, $expectedAttempt, $version, $this->options,
        );
    }
}
