<?php

declare(strict_types=1);

namespace App\User\Application\Update;

use App\User\Application\EmailAvailability;
use App\User\Application\LastAdminGuard;
use App\User\Application\UserView;
use App\User\Domain\Exception\OperationNotAllowed;
use App\User\Domain\Exception\UserNotFound;
use App\User\Domain\UserRepository;
use DateTimeImmutable;

final class UpdateUserHandler
{
    private UserRepository $users;
    private EmailAvailability $emails;
    private LastAdminGuard $lastAdmin;

    public function __construct(UserRepository $users, EmailAvailability $emails, LastAdminGuard $lastAdmin)
    {
        $this->users = $users;
        $this->emails = $emails;
        $this->lastAdmin = $lastAdmin;
    }

    public function handle(UpdateUserCommand $command): UserView
    {
        $user = $this->users->byId($command->id);

        if ($user === null) {
            throw UserNotFound::withId($command->id);
        }

        $now = new DateTimeImmutable();
        $this->emails->assertAvailable($command->email, $command->id);

        if ($command->role !== $user->role()) {
            if ($command->actorId === $command->id) {
                throw OperationNotAllowed::ownAccount('cambiar el rol de');
            }

            $this->lastAdmin->assertNotTheLastAdmin($user);
            $user->changeRole($command->role, $now);
        }

        $user->updateProfile($command->fullName, $command->email, $now);
        $this->users->save($user);

        return UserView::fromUser($user);
    }
}
