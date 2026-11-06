<?php

declare(strict_types=1);

namespace App\User\Domain;

use App\User\Domain\Exception\InvalidUser;

final class PasswordPolicy
{
    public const MIN_LENGTH = 8;
    /** bcrypt ignora lo que exceda 72 bytes: se rechaza en vez de truncar en silencio. */
    public const MAX_BYTES = 72;

    private function __construct()
    {
    }

    public static function assertValid(string $plainPassword): void
    {
        if (mb_strlen($plainPassword) < self::MIN_LENGTH || strlen($plainPassword) > self::MAX_BYTES) {
            throw InvalidUser::because(sprintf(
                'La contraseña debe tener entre %d y %d caracteres.',
                self::MIN_LENGTH,
                self::MAX_BYTES
            ));
        }
    }
}
