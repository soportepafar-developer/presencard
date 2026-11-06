<?php

declare(strict_types=1);

namespace App\User\Application;

use App\User\Domain\Exception\OperationNotAllowed;
use App\User\Domain\User;
use App\User\Domain\UserRepository;

/** Evita que el sistema se quede sin nadie que pueda administrarlo. */
final class LastAdminGuard
{
    private UserRepository $users;

    public function __construct(UserRepository $users)
    {
        $this->users = $users;
    }

    /** Llamar antes de quitar el rol de administrador, desactivar o eliminar a $target. */
    public function assertNotTheLastAdmin(User $target): void
    {
        if ($target->isAdmin() && $target->isActive() && $this->users->countActiveAdmins() <= 1) {
            throw OperationNotAllowed::lastActiveAdmin();
        }
    }
}
