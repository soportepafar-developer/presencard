<?php

declare(strict_types=1);

namespace App\Presencard\Application\Port;

/**
 * Puerto de salida hacia el contexto de usuarios: nombres de los responsables de las tarjetas.
 */
interface OwnerDirectory
{
    /**
     * @param int[] $ownerIds
     *
     * @return array<int, string> nombre por id de usuario (los inexistentes se omiten)
     */
    public function namesByIds(array $ownerIds): array;
}
