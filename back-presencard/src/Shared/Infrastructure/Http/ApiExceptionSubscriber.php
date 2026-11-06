<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Presencard\Domain\Exception\InvalidPresencard;
use App\Presencard\Domain\Exception\PresencardNotFound;
use App\User\Domain\Exception\InvalidUser;
use App\User\Domain\Exception\OperationNotAllowed;
use App\User\Domain\Exception\UserNotFound;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/** Traduce excepciones de los dominios (tarjetas y usuarios) y HTTP a respuestas JSON para las rutas /api. */
final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onException'];
    }

    public function onException(ExceptionEvent $event): void
    {
        if (strpos($event->getRequest()->getPathInfo(), '/api/') !== 0) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof InvalidPresencard || $exception instanceof InvalidUser) {
            $event->setResponse(new JsonResponse(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY));
        } elseif ($exception instanceof PresencardNotFound || $exception instanceof UserNotFound) {
            $event->setResponse(new JsonResponse(['message' => $exception->getMessage()], Response::HTTP_NOT_FOUND));
        } elseif ($exception instanceof OperationNotAllowed) {
            $event->setResponse(new JsonResponse(['message' => $exception->getMessage()], Response::HTTP_CONFLICT));
        } elseif ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            $message = $exception->getMessage() !== '' ? $exception->getMessage() : (Response::$statusTexts[$status] ?? 'Error');

            $event->setResponse(new JsonResponse(['message' => $message], $status, $exception->getHeaders()));
        }
    }
}
