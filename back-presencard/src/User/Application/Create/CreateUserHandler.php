<?php

declare(strict_types=1);

namespace App\User\Application\Create;

use App\User\Application\EmailAvailability;
use App\User\Application\Port\PasswordHasher;
use App\User\Application\UserView;
use App\User\Domain\PasswordPolicy;
use App\User\Domain\User;
use App\User\Domain\UserRepository;
use DateTimeImmutable;

final class CreateUserHandler
{
    private UserRepository $users;
    private PasswordHasher $hasher;
    private EmailAvailability $emails;

    public function __construct(UserRepository $users, PasswordHasher $hasher, EmailAvailability $emails)
    {
        $this->users = $users;
        $this->hasher = $hasher;
        $this->emails = $emails;
    }

    public function handle(CreateUserCommand $command): UserView
    {
        PasswordPolicy::assertValid($command->password);
        $this->emails->assertAvailable($command->email);

        $user = User::register(
            $command->email,
            $command->fullName,
            $this->hasher->hash($command->password),
            $command->role,
            new DateTimeImmutable()
        );

        $this->users->save($user);

        return UserView::fromUser($user);
    }
}
