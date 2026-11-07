<?php
namespace App\Manager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;
final class GameManager
{
    public function __construct(private EntityManagerInterface $em) {}
    public function transaction(callable $operation): mixed
    {
        return $this->em->wrapInTransaction(fn() => $operation());
    }
    public function lock(object $entity): void { $this->em->lock($entity, LockMode::PESSIMISTIC_WRITE); $this->em->refresh($entity); }
    public function save(object $entity): void { $this->em->persist($entity); }
    public function remove(object $entity): void { $this->em->remove($entity); }
    public function flush(): void { $this->em->flush(); }
    public function clear(): void { $this->em->clear(); }
}
