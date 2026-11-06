<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Security;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

final class JsonLoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    private LoggerInterface $logger;
    private LoginAttemptLimiter $limiter;

    public function __construct(LoggerInterface $logger, LoginAttemptLimiter $limiter)
    {
        $this->logger = $logger;
        $this->limiter = $limiter;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        // Cuenta desactivada (la contraseña sí era correcta): se explica el motivo.
        if ($exception instanceof AccountStatusException) {
            return new JsonResponse(['message' => $exception->getMessageKey()], Response::HTTP_FORBIDDEN);
        }

        // Un fallo de infraestructura (p. ej. no hay conexión con la base de datos) no es una contraseña incorrecta:
        // se avisa como error del servidor y se deja registrado para poder diagnosticarlo.
        if ($exception instanceof AuthenticationServiceException) {
            $this->logger->error('No se pudo verificar el acceso: ' . ($exception->getPrevious() ?? $exception)->getMessage());

            return new JsonResponse(
                ['message' => 'No se pudo verificar tu acceso en este momento. Inténtalo de nuevo en unos minutos.'],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        // Solo las credenciales incorrectas cuentan para el bloqueo (no las caídas de la base de datos).
        [$email, $ip] = LoginAttemptLimiter::identify($request);
        $this->limiter->recordFailure($email, $ip);

        return new JsonResponse(['message' => 'Correo o contraseña incorrectos.'], Response::HTTP_UNAUTHORIZED);
    }
}
