<?php

declare(strict_types=1);

namespace App\User\Application;

use App\User\Domain\Exception\InvalidUser;
use App\User\Domain\UserRepository;

/** Regla compartida por altas, edición y perfil: un correo pertenece a un solo usuario. */
final class EmailAvailability
{
    private UserRepository $users;

    public function __construct(UserRepository $users)
    {
        $this->users = $users;
    }

    public function assertAvailable(string $email, ?int $exceptUserId = null): void
    {
        $owner = $this->users->byEmail(trim($email));

        if ($owner !== null && $owner->id() !== $exceptUserId) {
            throw InvalidUser::emailAlreadyInUse(trim($email));
        }
    }
}
