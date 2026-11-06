<?php

declare(strict_types=1);

namespace App\User\Application\Port;

/**
 * Puerto de salida: el algoritmo concreto (bcrypt, argon...) lo decide la infraestructura.
 */
interface PasswordHasher
{
    public function hash(string $plainPassword): string;

    public function verify(string $passwordHash, string $plainPassword): bool;
}
