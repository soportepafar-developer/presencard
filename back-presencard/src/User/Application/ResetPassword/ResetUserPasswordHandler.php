<?php

declare(strict_types=1);

namespace App\User\Application\ResetPassword;

use App\User\Application\Port\PasswordHasher;
use App\User\Domain\Exception\UserNotFound;
use App\User\Domain\PasswordPolicy;
use App\User\Domain\UserRepository;
use DateTimeImmutable;

/** Un administrador asigna una contraseña nueva a otro usuario (no requiere la anterior). */
final class ResetUserPasswordHandler
{
    private UserRepository $users;
    private PasswordHasher $hasher;

    public function __construct(UserRepository $users, PasswordHasher $hasher)
    {
        $this->users = $users;
        $this->hasher = $hasher;
    }

    public function handle(int $id, string $newPassword): void
    {
        $user = $this->users->byId($id);

        if ($user === null) {
            throw UserNotFound::withId($id);
        }

        PasswordPolicy::assertValid($newPassword);
        $user->changePasswordHash($this->hasher->hash($newPassword), new DateTimeImmutable());
        $this->users->save($user);
    }
}
