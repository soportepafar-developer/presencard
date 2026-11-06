<?php

declare(strict_types=1);

namespace App\Presencard\Infrastructure\Http;

use App\Presencard\Infrastructure\Http\Seo\SpaShellRenderer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Rutas de la SPA que no necesitan meta tags propias (login y panel):
 * se entrega el index.html de Angular y su router resuelve la pantalla.
 */
final class SpaController
{
    private SpaShellRenderer $shell;

    public function __construct(SpaShellRenderer $shell)
    {
        $this->shell = $shell;
    }

    /**
     * @Route("/", name="home", methods={"GET"})
     */
    public function home(Request $request): RedirectResponse
    {
        // getBasePath() es '' en la raíz y '/presencard' cuando la app vive en una subcarpeta.
        return new RedirectResponse($request->getBasePath() . '/panel');
    }

    /**
     * @Route("/login", name="spa_login", methods={"GET"})
     * @Route("/panel", name="spa_panel", methods={"GET"})
     * @Route("/panel/{path}", name="spa_panel_path", methods={"GET"}, requirements={"path"=".+"})
     */
    public function __invoke(Request $request): Response
    {
        $response = new Response($this->shell->render(['title' => 'Presencard', 'robots' => 'noindex'], null, $request->getBasePath()));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
