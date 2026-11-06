<?php

declare(strict_types=1);

namespace App\Tests\Presencard\Infrastructure;

use App\Presencard\Domain\HashId;
use App\Presencard\Infrastructure\Hash\RandomHashGenerator;
use PHPUnit\Framework\TestCase;

final class RandomHashGeneratorTest extends TestCase
{
    public function testGeneratesValidAlphanumericHashes(): void
    {
        $generator = new RandomHashGenerator();

        for ($i = 0; $i < 50; ++$i) {
            $this->assertMatchesRegularExpression('/^[0-9A-Za-z]{12}$/', $generator->generate()->value());
        }
    }

    public function testGeneratesDifferentValues(): void
    {
        $generator = new RandomHashGenerator();
        $values = [];

        for ($i = 0; $i < 200; ++$i) {
            $values[$generator->generate()->value()] = true;
        }

        $this->assertCount(200, $values);
        $this->assertSame(HashId::LENGTH, strlen(array_key_first($values)));
    }
}
