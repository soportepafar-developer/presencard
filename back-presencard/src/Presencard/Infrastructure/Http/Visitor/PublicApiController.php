<?php

declare(strict_types=1);

namespace App\Presencard\Infrastructure\Http\Visitor;

use App\Presencard\Application\Query\GetPublicPresencardHandler;
use App\Presencard\Infrastructure\Http\PublicCardPresenter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

final class PublicApiController
{
    private GetPublicPresencardHandler $handler;
    private PublicCardPresenter $presenter;

    public function __construct(GetPublicPresencardHandler $handler, PublicCardPresenter $presenter)
    {
        $this->handler = $handler;
        $this->presenter = $presenter;
    }

    /**
     * @Route("/api/public/cards/{hashId}", name="public_card_api", methods={"GET"}, requirements={"hashId"="[^/]+"})
     */
    public function __invoke(string $hashId): JsonResponse
    {
        $response = new JsonResponse($this->presenter->present($this->handler->handle($hashId)));
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
