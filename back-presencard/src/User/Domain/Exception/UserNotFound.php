<?php

declare(strict_types=1);

namespace App\User\Domain\Exception;

use DomainException;

final class UserNotFound extends DomainException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('No existe el usuario con id %d.', $id));
    }
}
