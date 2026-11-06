<?php

declare(strict_types=1);

namespace App\User\Application\Port;

/**
 * Puerto de salida hacia el contexto de tarjetas: al eliminar a un usuario,
 * sus tarjetas pasan a otro usuario en lugar de borrarse.
 */
interface CardOwnershipTransfer
{
    public function transfer(int $fromUserId, int $toUserId): void;
}
