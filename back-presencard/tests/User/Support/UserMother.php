<?php

declare(strict_types=1);

namespace App\Tests\User\Support;

use App\User\Domain\User;
use App\User\Domain\UserRole;
use DateTimeImmutable;

final class UserMother
{
    public static function admin(string $email = 'admin@pafar.net', string $name = 'Admin'): User
    {
        return self::create($email, $name, UserRole::ADMIN);
    }

    public static function regular(string $email = 'ana@pafar.net', string $name = 'Ana Pérez'): User
    {
        return self::create($email, $name, UserRole::USER);
    }

    public static function create(string $email, string $name, string $role, string $password = 'secreto123'): User
    {
        return User::register($email, $name, 'hashed:' . $password, $role, new DateTimeImmutable('2026-10-21 10:00:00'));
    }
}
