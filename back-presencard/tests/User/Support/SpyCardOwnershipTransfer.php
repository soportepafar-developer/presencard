<?php

declare(strict_types=1);

namespace App\Tests\User\Support;

use App\User\Application\Port\CardOwnershipTransfer;

final class SpyCardOwnershipTransfer implements CardOwnershipTransfer
{
    /** @var array<int, array{int, int}> */
    public array $transfers = [];

    public function transfer(int $fromUserId, int $toUserId): void
    {
        $this->transfers[] = [$fromUserId, $toUserId];
    }
}
