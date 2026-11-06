<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Card;

use App\Presencard\Domain\PresencardRepository;
use App\User\Application\Port\CardOwnershipTransfer;

/** Adaptador: el contexto de usuarios pide al de tarjetas que cambie de responsable. */
final class PresencardOwnershipTransfer implements CardOwnershipTransfer
{
    private PresencardRepository $cards;

    public function __construct(PresencardRepository $cards)
    {
        $this->cards = $cards;
    }

    public function transfer(int $fromUserId, int $toUserId): void
    {
        $this->cards->reassignOwner($fromUserId, $toUserId);
    }
}
