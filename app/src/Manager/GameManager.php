<?php

namespace App\Manager;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;

/** Point de passage commun aux services de jeu pour les transactions et les écritures Doctrine. */
final class GameManager
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function transaction(callable $operation): mixed
    {
        return $this->em->wrapInTransaction(fn() => $operation());
    }

    public function lock(object $entity): void
    {
        $this->em->lock($entity, LockMode::PESSIMISTIC_WRITE);
        // L’attente du verrou a pu rendre l’entité périmée : repartir de l’état réellement en base.
        $this->em->refresh($entity);
    }

    public function save(object $entity): void
    {
        $this->em->persist($entity);
    }

    public function remove(object $entity): void
    {
        $this->em->remove($entity);
    }

    public function flush(): void
    {
        $this->em->flush();
    }

    public function clear(): void
    {
        $this->em->clear();
    }
}
