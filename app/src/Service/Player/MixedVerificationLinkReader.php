<?php

namespace App\Service\Player;

use App\Entity\{EmailVerificationToken, Player, PlayerVerificationGeneration};
use App\Manager\GameManager;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\{EntityManagerInterface, Query};
use Psr\Clock\ClockInterface;

/** Lecteur interne non raccordé. Aucune clé de dérivation en lecture. */
final class MixedVerificationLinkReader
{
    public function __construct(
        private EntityManagerInterface $em,
        private GameManager $manager,
        private VersionedVerificationLink $links,
        private ClockInterface $clock,
    ) {
    }

    public function confirm(#[\SensitiveParameter] string $link): bool
    {
        // Conserver l'alphabet et les longueurs acceptés par le lecteur hérité ;
        // le composant versionné vérifie ensuite son format plus strict.
        if (!preg_match('/\A[A-Za-z0-9_-]{40,50}\z/', $link)) {
            return false;
        }

        $db = $this->em->getConnection();
        if ($db->isTransactionActive()) {
            throw new \LogicException('La confirmation mixte exige sa propre transaction.');
        }

        // L'orientation n'autorise rien et n'ouvre pas de snapshot dans la
        // transaction de décision. Ne jamais chercher en base avec le lien brut.
        $hash = hash('sha256', $link);
        $owners = $db->fetchFirstColumn(
            'SELECT player_id FROM player_email_verification_token WHERE token_hash = ?
             UNION ALL SELECT player_id FROM player_verification_generation WHERE fingerprint = ?',
            [$hash, $hash],
        );
        if (count($owners) !== 1) {
            return false;
        }
        $playerId = (int) $owners[0];

        return $this->manager->transaction(function () use ($playerId, $hash, $link): bool {
            // Ordre commun aux émetteurs et au magasin : compte, puis enfants.
            // Une instance déjà chargée ne doit pas décider avec son ancien état.
            $player = $this->em->createQuery('SELECT p FROM App\Entity\Player p WHERE p.id = :id')
                ->setParameter('id', $playerId)->setLockMode(LockMode::PESSIMISTIC_WRITE)
                ->setHint(Query::HINT_REFRESH, true)->getOneOrNullResult();
            if (!$player instanceof Player || $player->emailVerifiedAt !== null) {
                return false;
            }

            $legacy = $this->em->createQuery('SELECT t FROM App\Entity\EmailVerificationToken t WHERE t.player = :player AND t.tokenHash = :hash')
                ->setParameter('player', $player)->setParameter('hash', $hash)
                ->setLockMode(LockMode::PESSIMISTIC_WRITE)->setHint(Query::HINT_REFRESH, true)->getOneOrNullResult();
            $generation = $this->em->createQuery('SELECT g FROM App\Entity\PlayerVerificationGeneration g WHERE g.player = :player')
                ->setParameter('player', $player)->setLockMode(LockMode::PESSIMISTIC_WRITE)
                ->setHint(Query::HINT_REFRESH, true)->getOneOrNullResult();

            $now = $this->clock->now();
            if ($legacy instanceof EmailVerificationToken) {
                if ($legacy->expiresAt <= $now
                    || ($generation instanceof PlayerVerificationGeneration && hash_equals($generation->fingerprint, $hash))) {
                    return false;
                }
                // Comme dans le lecteur ancien, seul le lien consommé est retiré.
                $this->manager->remove($legacy);
                if ($generation instanceof PlayerVerificationGeneration && $generation->state === 'active') {
                    $generation->state = 'revoked';
                }
            } else {
                // La voie historique ne filtre pas la suspension ; ne pas
                // lui imposer la règle spécifique à la génération versionnée.
                if ($player->lockedAt !== null || !$generation instanceof PlayerVerificationGeneration || $generation->state !== 'active'
                    || !VerificationRecipientBinding::matches($generation->recipientFingerprint, $player->email, $generation->fingerprint)) {
                    return false;
                }
                $current = new VerificationLinkGeneration(
                    $playerId, $generation->version, $generation->algorithm, $generation->keyId,
                    $generation->nonce, $generation->expiresAt, $generation->fingerprint,
                );
                if (!$this->links->matches($current, $link)) {
                    return false;
                }
                $generation->state = 'consumed';
            }

            $player->emailVerifiedAt = $now;
            return true;
        });
    }
}
