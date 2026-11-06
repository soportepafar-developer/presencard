<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Security;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Bloquea el acceso de cuentas desactivadas. Se valida después de comprobar la contraseña
 * para no revelar a un desconocido qué correos existen ni cuáles están desactivados.
 */
final class ActiveUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if ($user instanceof SecurityUser && !$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('Tu cuenta está desactivada. Contacta al administrador.');
        }
    }
}
