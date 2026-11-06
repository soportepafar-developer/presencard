<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Corta con 429 los intentos de login de quien ya agotó sus intentos, antes de que el firewall consulte la base de datos.
 */
final class LoginThrottleListener implements EventSubscriberInterface
{
    private const LOGIN_PATH = '/api/login';

    private LoginAttemptLimiter $limiter;

    public function __construct(LoginAttemptLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    public static function getSubscribedEvents(): array
    {
        // Prioridad mayor que la del firewall de seguridad (8).
        return [KernelEvents::REQUEST => ['onKernelRequest', 20]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMasterRequest() || $request->getMethod() !== 'POST' || $request->getPathInfo() !== self::LOGIN_PATH) {
            return;
        }

        [$email, $ip] = LoginAttemptLimiter::identify($request);
        $seconds = $this->limiter->retryAfter($email, $ip);

        if ($seconds <= 0) {
            return;
        }

        $event->setResponse(new JsonResponse(
            ['message' => sprintf('Demasiados intentos fallidos. Inténtalo de nuevo en %d minuto(s).', (int) ceil($seconds / 60))],
            Response::HTTP_TOO_MANY_REQUESTS,
            ['Retry-After' => (string) $seconds]
        ));
    }
}
