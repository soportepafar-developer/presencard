<?php

declare(strict_types=1);

namespace App\User\Application\Query;

use App\User\Application\UserView;
use App\User\Domain\Exception\UserNotFound;
use App\User\Domain\UserRepository;

final class GetUserHandler
{
    private UserRepository $users;

    public function __construct(UserRepository $users)
    {
        $this->users = $users;
    }

    public function handle(int $id): UserView
    {
        $user = $this->users->byId($id);

        if ($user === null) {
            throw UserNotFound::withId($id);
        }

        return UserView::fromUser($user);
    }
}
