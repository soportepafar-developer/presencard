<?php

declare(strict_types=1);

namespace App\Presencard\Domain\Exception;

use DomainException;

final class PresencardNotFound extends DomainException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('No existe la tarjeta con id %d.', $id));
    }

    public static function withHashId(string $hashId): self
    {
        return new self(sprintf('No existe la tarjeta con hash "%s".', $hashId));
    }
}
