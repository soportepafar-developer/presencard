<?php

declare(strict_types=1);

namespace App\Presencard\Infrastructure\Http\Visitor;

use App\Presencard\Application\Query\GetPublicPresencardHandler;
use App\Presencard\Domain\Exception\PresencardNotFound;
use App\Presencard\Infrastructure\Http\PublicCardPresenter;
use App\Presencard\Infrastructure\Http\PublicUrlBuilder;
use App\Presencard\Infrastructure\Http\Seo\SpaShellRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Página pública de la tarjeta (/c/{hashId}). Symfony responde con el shell de Angular
 * y las meta tags Open Graph ya resueltas con el nombre y cargo de la persona.
 */
final class CardPageController
{
    private const CACHE_SECONDS = 60;
    private const OG_IMAGE_WIDTH = 1200;
    private const OG_IMAGE_HEIGHT = 630;

    private GetPublicPresencardHandler $handler;
    private PublicCardPresenter $presenter;
    private SpaShellRenderer $shell;
    private PublicUrlBuilder $urls;
    /** Ruta de la imagen de la vista previa, relativa a la URL pública (vacía = sin imagen). */
    private string $ogImagePath;

    public function __construct(
        GetPublicPresencardHandler $handler,
        PublicCardPresenter $presenter,
        SpaShellRenderer $shell,
        PublicUrlBuilder $urls,
        string $ogImagePath
    ) {
        $this->handler = $handler;
        $this->presenter = $presenter;
        $this->shell = $shell;
        $this->urls = $urls;
        $this->ogImagePath = $ogImagePath;
    }

    /**
     * @Route("/c/{hashId}", name="public_card_page", methods={"GET"}, requirements={"hashId"="[^/]+"})
     */
    public function __invoke(string $hashId, Request $request): Response
    {
        try {
            $card = $this->presenter->present($this->handler->handle($hashId));
        } catch (PresencardNotFound $e) {
            $response = new Response(
                $this->shell->render(['title' => 'Tarjeta no encontrada · Presencard', 'robots' => 'noindex'], ['notFound' => true], $request->getBasePath()),
                Response::HTTP_NOT_FOUND
            );
            $response->headers->set('Cache-Control', 'no-store');

            return $response;
        }

        // Cargo y empresa son opcionales: la vista previa lleva solo lo que exista.
        $details = array_values(array_filter([$card['jobTitle'], $card['company']]));
        $meta = [
            'title' => implode(' · ', array_merge([$card['fullName']], $details)),
            'ogTitle' => $card['fullName'],
            'url' => $card['publicUrl'],
            'type' => 'profile',
            // Los datos de contacto de una persona no deben aparecer en buscadores: solo se llega por enlace o QR.
            'robots' => 'noindex, nofollow',
        ];

        if ($this->ogImagePath !== '') {
            $meta['image'] = $this->urls->forPath($this->ogImagePath);
            $meta['imageWidth'] = self::OG_IMAGE_WIDTH;
            $meta['imageHeight'] = self::OG_IMAGE_HEIGHT;
        }

        if ($details !== []) {
            $meta['description'] = implode(' · ', $details);
        }

        $response = new Response($this->shell->render($meta, ['card' => $card], $request->getBasePath()));

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->setPublic();
        $response->setMaxAge(self::CACHE_SECONDS);

        return $response;
    }
}
