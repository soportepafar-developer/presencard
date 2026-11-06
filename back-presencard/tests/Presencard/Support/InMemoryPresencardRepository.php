<?php

declare(strict_types=1);

namespace App\Tests\Presencard\Support;

use App\Presencard\Domain\HashId;
use App\Presencard\Domain\Presencard;
use App\Presencard\Domain\PresencardRepository;
use ReflectionProperty;

final class InMemoryPresencardRepository implements PresencardRepository
{
    /** @var array<int, Presencard> */
    private array $items = [];
    private int $lastId = 0;

    public function save(Presencard $presencard): void
    {
        if ($presencard->id() === null) {
            $property = new ReflectionProperty(Presencard::class, 'id');
            $property->setAccessible(true);
            $property->setValue($presencard, ++$this->lastId);
        }

        $this->items[$presencard->id()] = $presencard;
    }

    public function remove(Presencard $presencard): void
    {
        unset($this->items[$presencard->id()]);
    }

    public function byId(int $id): ?Presencard
    {
        return $this->items[$id] ?? null;
    }

    public function byHashId(HashId $hashId): ?Presencard
    {
        foreach ($this->items as $item) {
            if ($item->hashId()->equals($hashId)) {
                return $item;
            }
        }

        return null;
    }

    public function hashIdExists(HashId $hashId): bool
    {
        return $this->byHashId($hashId) !== null;
    }

    public function search(?string $search, int $page, int $perPage, ?int $ownerId = null): array
    {
        $matches = $this->filter($search, $ownerId);
        usort($matches, static function (Presencard $a, Presencard $b): int {
            return $b->id() <=> $a->id();
        });

        return array_slice($matches, ($page - 1) * $perPage, $perPage);
    }

    public function count(?string $search, ?int $ownerId = null): int
    {
        return count($this->filter($search, $ownerId));
    }

    public function reassignOwner(int $fromOwnerId, int $toOwnerId): void
    {
        $property = new ReflectionProperty(Presencard::class, 'ownerId');
        $property->setAccessible(true);

        foreach ($this->items as $item) {
            if ($item->isOwnedBy($fromOwnerId)) {
                $property->setValue($item, $toOwnerId);
            }
        }
    }

    /** @return Presencard[] */
    private function filter(?string $search, ?int $ownerId): array
    {
        return array_values(array_filter($this->items, static function (Presencard $item) use ($search, $ownerId): bool {
            if ($ownerId !== null && !$item->isOwnedBy($ownerId)) {
                return false;
            }

            if ($search === null) {
                return true;
            }

            $haystack = mb_strtolower($item->fullName() . ' ' . $item->jobTitle() . ' ' . $item->company() . ' ' . $item->email());

            return strpos($haystack, mb_strtolower($search)) !== false;
        }));
    }
}
