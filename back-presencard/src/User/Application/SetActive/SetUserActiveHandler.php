<?php

declare(strict_types=1);

namespace App\User\Application\SetActive;

use App\User\Application\LastAdminGuard;
use App\User\Application\UserView;
use App\User\Domain\Exception\OperationNotAllowed;
use App\User\Domain\Exception\UserNotFound;
use App\User\Domain\UserRepository;
use DateTimeImmutable;

final class SetUserActiveHandler
{
    private UserRepository $users;
    private LastAdminGuard $lastAdmin;

    public function __construct(UserRepository $users, LastAdminGuard $lastAdmin)
    {
        $this->users = $users;
        $this->lastAdmin = $lastAdmin;
    }

    public function handle(int $actorId, int $id, bool $active): UserView
    {
        $user = $this->users->byId($id);

        if ($user === null) {
            throw UserNotFound::withId($id);
        }

        $now = new DateTimeImmutable();

        if ($active) {
            $user->activate($now);
        } else {
            if ($actorId === $id) {
                throw OperationNotAllowed::ownAccount('desactivar');
            }

            $this->lastAdmin->assertNotTheLastAdmin($user);
            $user->deactivate($now);
        }

        $this->users->save($user);

        return UserView::fromUser($user);
    }
}
