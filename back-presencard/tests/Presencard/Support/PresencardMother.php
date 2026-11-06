<?php

declare(strict_types=1);

namespace App\Tests\Presencard\Support;

use App\Presencard\Domain\HashId;
use App\Presencard\Domain\Presencard;
use DateTimeImmutable;

final class PresencardMother
{
    public static function create(
        string $hashId = 'aaaaaaaaaaaa',
        string $fullName = 'Jaime Leonardo Padrón Faria',
        string $jobTitle = 'Socio/a, Fundador o CEO',
        string $email = 'jaileop@yahoo.com',
        int $ownerId = 1
    ): Presencard {
        return Presencard::create(
            HashId::fromString($hashId),
            $ownerId,
            $fullName,
            $jobTitle,
            'Consultores JPL',
            $email,
            '+58 412-3258691',
            '584123258691',
            'pafar_ve',
            new DateTimeImmutable('2026-10-21 10:00:00')
        );
    }
}
