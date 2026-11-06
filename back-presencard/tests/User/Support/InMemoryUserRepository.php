<?php

declare(strict_types=1);

namespace App\Tests\User\Support;

use App\User\Domain\User;
use App\User\Domain\UserRepository;
use App\User\Domain\UserRole;
use ReflectionProperty;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<int, User> */
    private array $items = [];
    private int $lastId = 0;

    public function save(User $user): void
    {
        if ($user->id() === null) {
            $property = new ReflectionProperty(User::class, 'id');
            $property->setAccessible(true);
            $property->setValue($user, ++$this->lastId);
        }

        $this->items[$user->id()] = $user;
    }

    public function remove(User $user): void
    {
        unset($this->items[$user->id()]);
    }

    public function byId(int $id): ?User
    {
        return $this->items[$id] ?? null;
    }

    public function byEmail(string $email): ?User
    {
        foreach ($this->items as $item) {
            // Igual que la collation utf8mb4_unicode_ci de MySQL: sin distinguir mayúsculas.
            if (mb_strtolower($item->email()) === mb_strtolower(trim($email))) {
                return $item;
            }
        }

        return null;
    }

    public function byIds(array $ids): array
    {
        return array_values(array_filter($this->items, static function (User $user) use ($ids): bool {
            return in_array($user->id(), $ids, true);
        }));
    }

    public function search(?string $search, int $page, int $perPage): array
    {
        $matches = $this->filter($search);
        usort($matches, static function (User $a, User $b): int {
            return strcmp($a->fullName(), $b->fullName()) ?: $a->id() <=> $b->id();
        });

        return array_slice($matches, ($page - 1) * $perPage, $perPage);
    }

    public function count(?string $search): int
    {
        return count($this->filter($search));
    }

    public function countActiveAdmins(): int
    {
        return count(array_filter($this->items, static function (User $user): bool {
            return $user->role() === UserRole::ADMIN && $user->isActive();
        }));
    }

    /** @return User[] */
    private function filter(?string $search): array
    {
        return array_values(array_filter($this->items, static function (User $user) use ($search): bool {
            return $search === null
                || strpos(mb_strtolower($user->fullName() . ' ' . $user->email()), mb_strtolower($search)) !== false;
        }));
    }
}
