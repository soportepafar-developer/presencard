<?php

declare(strict_types=1);

namespace App\User\Application\Profile;

use App\User\Application\EmailAvailability;
use App\User\Application\UserView;
use App\User\Domain\Exception\UserNotFound;
use App\User\Domain\UserRepository;
use DateTimeImmutable;

/** Cada usuario edita su propio nombre y correo (el rol y el estado solo los cambia un administrador). */
final class UpdateProfileHandler
{
    private UserRepository $users;
    private EmailAvailability $emails;

    public function __construct(UserRepository $users, EmailAvailability $emails)
    {
        $this->users = $users;
        $this->emails = $emails;
    }

    public function handle(int $userId, string $fullName, string $email): UserView
    {
        $user = $this->users->byId($userId);

        if ($user === null) {
            throw UserNotFound::withId($userId);
        }

        $this->emails->assertAvailable($email, $userId);
        $user->updateProfile($fullName, $email, new DateTimeImmutable());
        $this->users->save($user);

        return UserView::fromUser($user);
    }
}
