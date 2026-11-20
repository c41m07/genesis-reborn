<?php

namespace App\Service\Player;

use App\Entity\Player;
use Doctrine\DBAL\Connection;

/** Mutation de l'adresse existante sous le verrou métier du compte. */
final class PlayerEmailAddressChange
{
    public function __construct(private Connection $db)
    {
    }

    public function changeLocked(#[\SensitiveParameter] Player $player, #[\SensitiveParameter] string $address): void
    {
        if (!$this->db->isTransactionActive() || $player->id === null) {
            throw new \LogicException('Le changement d’adresse exige un compte et sa transaction.');
        }
        if ($player->email === $address) {
            return;
        }

        // Les deux appelants ont déjà le verrou compte et l'ont rafraîchi.
        // Le lecteur et la remise prennent ce même verrou avant la génération.
        // Sans table (ancien schéma), il n’existe aucun lien versionné à révoquer.
        if ($this->db->createSchemaManager()->tablesExist(['player_verification_generation'])) {
            $this->db->update(
                'player_verification_generation',
                ['state' => 'revoked'],
                ['player_id' => $player->id, 'state' => 'active'],
            );
        }
        $player->email = $address; // Commit ou rollback commun avec la révocation.
    }
}
