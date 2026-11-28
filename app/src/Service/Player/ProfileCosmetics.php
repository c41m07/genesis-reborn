<?php

declare(strict_types=1);

namespace App\Service\Player;

use App\Entity\{Player, PlayerUniverse, ProfileCosmetic, Universe};
use App\Manager\GameManager;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProfileCosmetics
{
    public const PIRATE_HUNTER_TITLE = 'amateur_pirate_hunter';

    public const DEFINITIONS = [
        self::PIRATE_HUNTER_TITLE => ['slot' => 'title', 'label' => 'Chasseur de Pirate amateur'],
        'guardian_of_stars' => ['slot' => 'title', 'label' => 'Gardien des étoiles'],
        'polar_night' => ['slot' => 'banner', 'label' => 'Nuit polaire'],
    ];

    public function __construct(private GameManager $manager, private EntityManagerInterface $em) {}

    /** @param list<string> $codes Le compte est déjà verrouillé par l’appelant. */
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

    /** @return array<string,string> Uniquement ce que le commandant a choisi d’équiper. */
    public function display(Player $player): array
    {
        $result = [];
        foreach ($this->owned($player) as $cosmetic) {
            if ($cosmetic['equipped']) {
                $result[$cosmetic['slot']] = $cosmetic['label'];
            }
        }

        return $result;
    }

    /**
     * @param list<int> $membershipIds
     * @return array<int,string> Titres publics des empires visibles sur la page.
     */
    public function equippedTitles(Universe $universe, array $membershipIds): array
    {
        if ($membershipIds === []) {
            return [];
        }

        $codes = array_keys(array_filter(self::DEFINITIONS, static fn(array $item): bool => $item['slot'] === 'title'));
        $rows = $this->em->createQueryBuilder()
            ->select('m.id AS membershipId', 'c.code')
            ->from(PlayerUniverse::class, 'm')
            ->innerJoin(ProfileCosmetic::class, 'c', 'WITH', 'c.player = m.player')
            ->where('m.universe = :universe AND m.forgottenAt IS NULL AND m.id IN (:ids)')
            ->andWhere('c.equipped = true AND c.code IN (:codes)')
            ->setParameter('universe', $universe)
            ->setParameter('ids', $membershipIds)
            ->setParameter('codes', $codes)
            ->orderBy('c.id', 'ASC')
            ->getQuery()->getArrayResult();

        $titles = [];
        foreach ($rows as $row) {
            $titles[$row['membershipId']] = self::DEFINITIONS[$row['code']]['label'];
        }

        return $titles;
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
