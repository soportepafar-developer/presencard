<?php

declare(strict_types=1);

namespace App\User\Application\Profile;

use App\User\Application\Port\PasswordHasher;
use App\User\Domain\Exception\InvalidUser;
use App\User\Domain\Exception\UserNotFound;
use App\User\Domain\PasswordPolicy;
use App\User\Domain\UserRepository;
use DateTimeImmutable;

/** El propio usuario cambia su contraseña: debe demostrar que conoce la actual. */
final class ChangePasswordHandler
{
    private UserRepository $users;
    private PasswordHasher $hasher;

    public function __construct(UserRepository $users, PasswordHasher $hasher)
    {
        $this->users = $users;
        $this->hasher = $hasher;
    }

    public function handle(int $userId, string $currentPassword, string $newPassword): void
    {
        $user = $this->users->byId($userId);

        if ($user === null) {
            throw UserNotFound::withId($userId);
        }

        if (!$this->hasher->verify($user->passwordHash(), $currentPassword)) {
            throw InvalidUser::incorrectCurrentPassword();
        }

        PasswordPolicy::assertValid($newPassword);

        if ($newPassword === $currentPassword) {
            throw InvalidUser::because('La nueva contraseña debe ser distinta de la actual.');
        }

        $user->changePasswordHash($this->hasher->hash($newPassword), new DateTimeImmutable());
        $this->users->save($user);
    }
}
