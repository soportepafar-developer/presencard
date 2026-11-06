<?php

declare(strict_types=1);

namespace App\User\Application\Query;

use App\User\Application\UserPage;
use App\User\Application\UserView;
use App\User\Domain\User;
use App\User\Domain\UserRepository;

final class ListUsersHandler
{
    public const DEFAULT_PER_PAGE = 10;
    public const MAX_PER_PAGE = 50;

    private UserRepository $users;

    public function __construct(UserRepository $users)
    {
        $this->users = $users;
    }

    public function handle(?string $search, int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): UserPage
    {
        $search = $search !== null ? trim($search) : null;
        $search = $search === '' ? null : $search;
        $page = max(1, $page);
        $perPage = min(self::MAX_PER_PAGE, max(1, $perPage));

        $items = array_map(static function (User $user): UserView {
            return UserView::fromUser($user);
        }, $this->users->search($search, $page, $perPage));

        return new UserPage($items, $this->users->count($search), $page, $perPage);
    }
}
