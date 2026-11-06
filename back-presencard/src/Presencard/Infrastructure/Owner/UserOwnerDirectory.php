<?php

declare(strict_types=1);

namespace App\Presencard\Infrastructure\Owner;

use App\Presencard\Application\Port\OwnerDirectory;
use App\User\Domain\User;
use App\User\Domain\UserRepository;

/** Adaptador: el contexto de tarjetas consulta nombres al contexto de usuarios. */
final class UserOwnerDirectory implements OwnerDirectory
{
    private UserRepository $users;

    public function __construct(UserRepository $users)
    {
        $this->users = $users;
    }

    public function namesByIds(array $ownerIds): array
    {
        if ($ownerIds === []) {
            return [];
        }

        $names = [];

        /** @var User $user */
        foreach ($this->users->byIds($ownerIds) as $user) {
            $names[(int) $user->id()] = $user->fullName();
        }

        return $names;
    }
}
