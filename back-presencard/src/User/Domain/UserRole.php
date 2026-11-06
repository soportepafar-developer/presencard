<?php

declare(strict_types=1);

namespace App\User\Domain;

use App\User\Domain\Exception\InvalidUser;

final class UserRole
{
    public const ADMIN = 'ROLE_ADMIN';
    public const USER = 'ROLE_USER';

    private function __construct()
    {
    }

    /** @return string[] */
    public static function all(): array
    {
        return [self::ADMIN, self::USER];
    }

    public static function assertValid(string $role): void
    {
        if (!in_array($role, self::all(), true)) {
            throw InvalidUser::because('El rol debe ser administrador o usuario.');
        }
    }
}
