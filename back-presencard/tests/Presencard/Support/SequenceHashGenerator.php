<?php

declare(strict_types=1);

namespace App\Tests\Presencard\Support;

use App\Presencard\Application\Port\HashGenerator;
use App\Presencard\Domain\HashId;

/**
 * Devuelve los hashes indicados en orden; el último se repite si se agotan.
 */
final class SequenceHashGenerator implements HashGenerator
{
    /** @var string[] */
    private array $hashes;
    private int $calls = 0;

    public function __construct(string ...$hashes)
    {
        $this->hashes = $hashes ?: ['aaaaaaaaaaaa'];
    }

    public function generate(): HashId
    {
        $index = min($this->calls, count($this->hashes) - 1);
        ++$this->calls;

        return HashId::fromString($this->hashes[$index]);
    }

    public function calls(): int
    {
        return $this->calls;
    }
}
