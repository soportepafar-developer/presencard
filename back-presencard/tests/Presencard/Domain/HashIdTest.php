<?php

declare(strict_types=1);

namespace App\Tests\Presencard\Domain;

use App\Presencard\Domain\Exception\InvalidPresencard;
use App\Presencard\Domain\HashId;
use PHPUnit\Framework\TestCase;

final class HashIdTest extends TestCase
{
    public function testAcceptsTwelveAlphanumericCharacters(): void
    {
        $hashId = HashId::fromString('aB3dE5gH7jK9');

        $this->assertSame('aB3dE5gH7jK9', $hashId->value());
        $this->assertSame('aB3dE5gH7jK9', (string) $hashId);
    }

    /**
     * @dataProvider invalidHashes
     */
    public function testRejectsInvalidValues(string $value): void
    {
        $this->expectException(InvalidPresencard::class);

        HashId::fromString($value);
    }

    public function invalidHashes(): array
    {
        return [
            'vacío' => [''],
            'corto' => ['abc123'],
            'largo' => ['aaaaaaaaaaaaa'],
            'símbolos' => ['aaaaaa-aaaaa'],
            'espacios' => ['aaaaaa aaaaa'],
            'tilde' => ['aaaaaaaaaaaá'],
        ];
    }

    public function testEqualsComparesByValue(): void
    {
        $this->assertTrue(HashId::fromString('aaaaaaaaaaaa')->equals(HashId::fromString('aaaaaaaaaaaa')));
        $this->assertFalse(HashId::fromString('aaaaaaaaaaaa')->equals(HashId::fromString('bbbbbbbbbbbb')));
    }
}
