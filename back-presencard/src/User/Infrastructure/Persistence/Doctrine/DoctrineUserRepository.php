<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Persistence\Doctrine;

use App\User\Domain\User;
use App\User\Domain\UserRepository;
use App\User\Domain\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final class DoctrineUserRepository implements UserRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function remove(User $user): void
    {
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }

    public function byId(int $id): ?User
    {
        return $this->entityManager->find(User::class, $id);
    }

    public function byEmail(string $email): ?User
    {
        return $this->entityManager->getRepository(User::class)->findOneBy(['email' => trim($email)]);
    }

    public function byIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', array_values($ids))
            ->getQuery()
            ->getResult();
    }

    public function search(?string $search, int $page, int $perPage): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->orderBy('u.fullName', 'ASC')
            ->addOrderBy('u.id', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $this->applySearch($queryBuilder, $search);

        return $queryBuilder->getQuery()->getResult();
    }

    public function count(?string $search): int
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u');

        $this->applySearch($queryBuilder, $search);

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    public function countActiveAdmins(): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->where('u.role = :role')
            ->andWhere('u.active = :active')
            ->setParameter('role', UserRole::ADMIN)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function applySearch(QueryBuilder $queryBuilder, ?string $search): void
    {
        if ($search === null) {
            return;
        }

        $queryBuilder
            ->andWhere('u.fullName LIKE :search OR u.email LIKE :search')
            ->setParameter('search', '%' . addcslashes($search, '%_\\') . '%');
    }
}
