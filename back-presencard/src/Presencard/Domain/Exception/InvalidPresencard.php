<?php

declare(strict_types=1);

namespace App\Presencard\Domain\Exception;

use DomainException;

final class InvalidPresencard extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
