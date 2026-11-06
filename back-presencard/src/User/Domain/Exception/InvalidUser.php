<?php

declare(strict_types=1);

namespace App\User\Domain\Exception;

use DomainException;

/** Datos de usuario inválidos (nombre, correo, rol, contraseña, correo repetido...). */
final class InvalidUser extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public static function emailAlreadyInUse(string $email): self
    {
        return new self(sprintf('El correo %s ya está registrado.', $email));
    }

    public static function incorrectCurrentPassword(): self
    {
        return new self('La contraseña actual no es correcta.');
    }
}
