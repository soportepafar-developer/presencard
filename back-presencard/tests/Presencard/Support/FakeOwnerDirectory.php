<?php

declare(strict_types=1);

namespace App\Tests\Presencard\Support;

use App\Presencard\Application\Port\OwnerDirectory;

final class FakeOwnerDirectory implements OwnerDirectory
{
    /** @var array<int, string> */
    private array $names;

    /** @param array<int, string> $names */
    public function __construct(array $names = [])
    {
        $this->names = $names;
    }

    public function namesByIds(array $ownerIds): array
    {
        return array_intersect_key($this->names, array_flip($ownerIds));
    }
}
