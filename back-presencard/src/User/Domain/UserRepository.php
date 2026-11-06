<?php

declare(strict_types=1);

namespace App\User\Domain;

interface UserRepository
{
    public function save(User $user): void;

    public function remove(User $user): void;

    public function byId(int $id): ?User;

    public function byEmail(string $email): ?User;

    /**
     * @param int[] $ids
     *
     * @return User[]
     */
    public function byIds(array $ids): array;

    /**
     * Ordenados por nombre. $search filtra por nombre o correo.
     *
     * @return User[]
     */
    public function search(?string $search, int $page, int $perPage): array;

    public function count(?string $search): int;

    public function countActiveAdmins(): int;
}
