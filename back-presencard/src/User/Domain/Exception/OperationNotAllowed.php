<?php

declare(strict_types=1);

namespace App\User\Domain\Exception;

use DomainException;

/** La operación es válida en sí misma pero rompe una regla de negocio (p. ej. quedarse sin administradores). */
final class OperationNotAllowed extends DomainException
{
    public static function lastActiveAdmin(): self
    {
        return new self('Debe existir al menos un administrador activo.');
    }

    public static function ownAccount(string $action): self
    {
        return new self(sprintf('No puedes %s tu propia cuenta desde la gestión de usuarios.', $action));
    }
}
