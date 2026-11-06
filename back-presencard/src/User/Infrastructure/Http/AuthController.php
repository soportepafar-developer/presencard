<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Http;

use App\User\Application\Query\GetUserHandler;
use App\User\Infrastructure\Security\SecurityUser;
use LogicException;
use App\User\Infrastructure\Security\LoginAttemptLimiter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

final class AuthController
{
    private Security $security;
    private GetUserHandler $getUser;
    private LoginAttemptLimiter $limiter;

    public function __construct(Security $security, GetUserHandler $getUser, LoginAttemptLimiter $limiter)
    {
        $this->security = $security;
        $this->getUser = $getUser;
        $this->limiter = $limiter;
    }

    /**
     * El firewall (json_login) valida {"email","password"} antes de llegar aquí: si las
     * credenciales son malas responde 401 solo; si son buenas, esta acción responde el perfil.
     *
     * @Route("/api/login", name="api_login", methods={"POST"})
     */
    public function login(Request $request): JsonResponse
    {
        // Solo se llega aquí con credenciales correctas: se limpia el contador de fallos de esta cuenta.
        [$email, $ip] = LoginAttemptLimiter::identify($request);
        $this->limiter->reset($email, $ip);

        /** @var SecurityUser $user */
        $user = $this->security->getUser();

        return new JsonResponse($this->getUser->handle($user->id())->toArray());
    }

    /**
     * @Route("/api/logout", name="api_logout", methods={"POST"})
     */
    public function logout(): void
    {
        throw new LogicException('Interceptado por el firewall (logout).');
    }
}
