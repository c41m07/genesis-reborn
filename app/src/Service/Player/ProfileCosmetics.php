<?php

declare(strict_types=1);

namespace App\Service\Player;

use App\Entity\{Player, ProfileCosmetic};
use App\Manager\GameManager;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProfileCosmetics
{
    public const DEFINITIONS = [
        'guardian_of_stars' => ['slot' => 'title', 'label' => 'Gardien des étoiles'],
        'polar_night' => ['slot' => 'banner', 'label' => 'Nuit polaire'],
    ];

    public function __construct(private GameManager $manager, private EntityManagerInterface $em) {}

    /** @param list<string> $codes Le compte est déjà verrouillé par le droit Avent. */
    public function grantLocked(Player $player, array $codes, \DateTimeImmutable $at): void
    {
        foreach (array_unique($codes) as $code) {
            if (!isset(self::DEFINITIONS[$code])) {
                throw new \DomainException('Cosmétique inconnu.');
            }
            // Même verrou compte que la sélection et l’effacement : une autre
            // édition ne doit pas renouveler le droit ni modifier l’équipement.
            $this->em->getConnection()->executeStatement(
                'INSERT INTO profile_cosmetic (player_id, code, awarded_at, equipped) VALUES (?, ?, ?, 0)
                 ON DUPLICATE KEY UPDATE code = VALUES(code)',
                [$player->id, $code, $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')],
            );
        }
    }

    /** @return array<string, array{slot:string,label:string,equipped:bool}> */
    public function owned(Player $player): array
    {
        $cosmetics = [];
        foreach ($this->em->getRepository(ProfileCosmetic::class)->findBy(['player' => $player], ['id' => 'ASC']) as $row) {
            if (isset(self::DEFINITIONS[$row->code])) {
                $cosmetics[$row->code] = self::DEFINITIONS[$row->code] + ['equipped' => $row->equipped];
            }
        }

        return $cosmetics;
    }

    /** @param list<string> $codes */
    public function equip(Player $player, array $codes): void
    {
        $this->manager->transaction(function () use ($player, $codes): void {
            $this->manager->lockCurrent($player);
            if ($player->lockedAt !== null) {
                throw new \DomainException('Ce compte ne peut pas être personnalisé.');
            }
            $owned = $this->owned($player);
            $slots = [];
            foreach ($codes as $code) {
                if (!is_string($code) || !isset($owned[$code]) || isset($slots[$owned[$code]['slot']])) {
                    throw new \DomainException('Cet élément n’est pas disponible pour votre profil.');
                }
                $slots[$owned[$code]['slot']] = $code;
            }
            foreach ($this->em->getRepository(ProfileCosmetic::class)->findBy(['player' => $player]) as $row) {
                if (isset($owned[$row->code])) {
                    $row->equipped = in_array($row->code, $slots, true);
                }
            }
        });
    }
}
