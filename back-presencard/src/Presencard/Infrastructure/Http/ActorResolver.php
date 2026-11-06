<?php

declare(strict_types=1);

namespace App\Presencard\Infrastructure\Http;

use App\Presencard\Application\Actor;
use App\User\Infrastructure\Security\SecurityUser;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Security;

/** Traduce el usuario autenticado en el Actor que entienden los casos de uso de tarjetas. */
final class ActorResolver
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function current(): Actor
    {
        $user = $this->security->getUser();

        if (!$user instanceof SecurityUser) {
            throw new AccessDeniedHttpException('Debes iniciar sesión.');
        }

        return $user->isAdmin() ? Actor::administrator($user->id()) : Actor::regularUser($user->id());
    }
}
