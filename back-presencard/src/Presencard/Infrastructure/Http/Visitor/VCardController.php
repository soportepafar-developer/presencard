<?php

declare(strict_types=1);

namespace App\Presencard\Infrastructure\Http\Visitor;

use App\Presencard\Application\Query\GetPublicPresencardHandler;
use App\Presencard\Infrastructure\Http\PublicCardPresenter;
use App\Presencard\Infrastructure\Http\VCard\VCardBuilder;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/** Descarga del contacto (.vcf) de una tarjeta pública. */
final class VCardController
{
    private const CACHE_SECONDS = 60;

    private GetPublicPresencardHandler $handler;
    private PublicCardPresenter $presenter;
    private VCardBuilder $builder;

    public function __construct(GetPublicPresencardHandler $handler, PublicCardPresenter $presenter, VCardBuilder $builder)
    {
        $this->handler = $handler;
        $this->presenter = $presenter;
        $this->builder = $builder;
    }

    /**
     * Bajo /api para que también funcione con el proxy de "ng serve". Un hash inexistente responde 404 (JSON).
     *
     * @Route("/api/public/cards/{hashId}/vcard.vcf", name="public_card_vcard", methods={"GET"}, requirements={"hashId"="[^/]+"})
     */
    public function __invoke(string $hashId): Response
    {
        $card = $this->presenter->present($this->handler->handle($hashId));

        $response = new Response($this->builder->build($card));
        $response->headers->set('Content-Type', 'text/vcard; charset=utf-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $this->builder->filename($card['fullName'], $card['hashId'])
        ));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->setPublic();
        $response->setMaxAge(self::CACHE_SECONDS);

        return $response;
    }
}
