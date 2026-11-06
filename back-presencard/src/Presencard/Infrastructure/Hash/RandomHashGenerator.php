<?php

declare(strict_types=1);

namespace App\Presencard\Infrastructure\Hash;

use App\Presencard\Application\Port\HashGenerator;
use App\Presencard\Domain\HashId;

final class RandomHashGenerator implements HashGenerator
{
    private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public function generate(): HashId
    {
        $maxIndex = strlen(self::ALPHABET) - 1;
        $value = '';

        for ($i = 0; $i < HashId::LENGTH; ++$i) {
            $value .= self::ALPHABET[random_int(0, $maxIndex)];
        }

        return HashId::fromString($value);
    }
}
