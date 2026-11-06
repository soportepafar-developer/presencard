<?php

declare(strict_types=1);

namespace App\Tests\User\Support;

use App\User\Application\Port\PasswordHasher;

/** Hash reversible y predecible: suficiente para probar la lógica sin bcrypt. */
final class FakePasswordHasher implements PasswordHasher
{
    public function hash(string $plainPassword): string
    {
        return 'hashed:' . $plainPassword;
    }

    public function verify(string $passwordHash, string $plainPassword): bool
    {
        return $passwordHash === $this->hash($plainPassword);
    }
}
