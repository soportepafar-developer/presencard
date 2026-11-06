<?php

declare(strict_types=1);

namespace App\Presencard\Infrastructure\Persistence\Doctrine;

use App\Presencard\Domain\HashId;
use App\Presencard\Domain\Presencard;
use App\Presencard\Domain\PresencardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final class DoctrinePresencardRepository implements PresencardRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function save(Presencard $presencard): void
    {
        $this->entityManager->persist($presencard);
        $this->entityManager->flush();
    }

    public function remove(Presencard $presencard): void
    {
        $this->entityManager->remove($presencard);
        $this->entityManager->flush();
    }

    public function byId(int $id): ?Presencard
    {
        return $this->entityManager->find(Presencard::class, $id);
    }

    public function byHashId(HashId $hashId): ?Presencard
    {
        return $this->entityManager->getRepository(Presencard::class)->findOneBy(['hashId' => $hashId->value()]);
    }

    public function hashIdExists(HashId $hashId): bool
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(Presencard::class, 'p')
            ->where('p.hashId = :hashId')
            ->setParameter('hashId', $hashId->value())
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function search(?string $search, int $page, int $perPage, ?int $ownerId = null): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Presencard::class, 'p')
            ->orderBy('p.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $this->applyFilters($queryBuilder, $search, $ownerId);

        return $queryBuilder->getQuery()->getResult();
    }

    public function count(?string $search, ?int $ownerId = null): int
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(Presencard::class, 'p');

        $this->applyFilters($queryBuilder, $search, $ownerId);

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    public function reassignOwner(int $fromOwnerId, int $toOwnerId): void
    {
        $this->entityManager->createQueryBuilder()
            ->update(Presencard::class, 'p')
            ->set('p.ownerId', ':to')
            ->where('p.ownerId = :from')
            ->setParameter('to', $toOwnerId)
            ->setParameter('from', $fromOwnerId)
            ->getQuery()
            ->execute();
    }

    private function applyFilters(QueryBuilder $queryBuilder, ?string $search, ?int $ownerId): void
    {
        if ($ownerId !== null) {
            $queryBuilder->andWhere('p.ownerId = :ownerId')->setParameter('ownerId', $ownerId);
        }

        if ($search !== null) {
            $queryBuilder
                ->andWhere('p.fullName LIKE :search OR p.jobTitle LIKE :search OR p.company LIKE :search OR p.email LIKE :search')
                ->setParameter('search', '%' . addcslashes($search, '%_\\') . '%');
        }
    }
}
