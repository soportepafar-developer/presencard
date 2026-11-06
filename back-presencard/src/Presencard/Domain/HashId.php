<?php

declare(strict_types=1);

namespace App\Presencard\Domain;

use App\Presencard\Domain\Exception\InvalidPresencard;

/**
 * Identificador público y no secuencial de una tarjeta (va en la URL).
 */
final class HashId
{
    public const LENGTH = 12;

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        if (!preg_match('/^[0-9A-Za-z]{' . self::LENGTH . '}$/', $value)) {
            throw InvalidPresencard::because(sprintf(
                'El hash debe ser alfanumérico de %d caracteres.',
                self::LENGTH
            ));
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
