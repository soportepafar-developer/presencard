<?php

declare(strict_types=1);

namespace App\Presencard\Domain;

/**
 * Puerto de salida: el dominio define qué necesita de la persistencia.
 */
interface PresencardRepository
{
    public function save(Presencard $presencard): void;

    public function remove(Presencard $presencard): void;

    public function byId(int $id): ?Presencard;

    public function byHashId(HashId $hashId): ?Presencard;

    public function hashIdExists(HashId $hashId): bool;

    /**
     * Más recientes primero. $search filtra por nombre, cargo, empresa o correo;
     * $ownerId (si se indica) limita a las tarjetas de ese usuario.
     *
     * @return Presencard[]
     */
    public function search(?string $search, int $page, int $perPage, ?int $ownerId = null): array;

    public function count(?string $search, ?int $ownerId = null): int;

    /** Pasa todas las tarjetas de un usuario a otro (p. ej. al eliminar al primero). */
    public function reassignOwner(int $fromOwnerId, int $toOwnerId): void;
}
