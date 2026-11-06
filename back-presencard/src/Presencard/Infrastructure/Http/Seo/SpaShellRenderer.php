<?php

declare(strict_types=1);

namespace App\Presencard\Infrastructure\Http\Seo;

/**
 * Entrega el index.html compilado de Angular con las meta tags (Open Graph) y el estado
 * inicial inyectados en el servidor. Los rastreadores de WhatsApp/redes no ejecutan JS,
 * por eso las tags deben venir ya en el HTML.
 */
final class SpaShellRenderer
{
    private string $indexHtmlPath;

    public function __construct(string $indexHtmlPath)
    {
        $this->indexHtmlPath = $indexHtmlPath;
    }

    /**
     * @param array{title: string, ogTitle?: string, description?: string, url?: string, type?: string, robots?: string, image?: string, imageWidth?: int, imageHeight?: int} $meta
     * @param array<string, mixed>|null $state Datos que Angular lee sin hacer otra petición.
     * @param string $basePath Prefijo de la app en el dominio ('' en la raíz, '/presencard' en una subcarpeta).
     */
    public function render(array $meta, ?array $state = null, string $basePath = ''): string
    {
        $basePath = rtrim($basePath, '/');
        $html = $this->loadShell($meta);
        $html = (string) preg_replace('#<title>.*?</title>#is', '', $html, 1);
        // El build se compila una sola vez con <base href="/app/">: aquí se ajusta al prefijo real de esta instalación.
        $html = (string) preg_replace('#<base\s+href="[^"]*"\s*/?>#i', '<base href="' . self::escape($basePath) . '/app/">', $html, 1);
        $html = $this->insertBefore($html, '</head>', $this->metaTags($meta, $basePath));

        if ($state !== null) {
            $html = $this->insertBefore($html, '</body>', $this->stateScript($state));
        }

        return $html;
    }

    /** @param array<string, string> $meta */
    private function metaTags(array $meta, string $basePath): string
    {
        $title = $meta['title'];
        $tags = ['<title>' . self::escape($title) . '</title>'];
        // Angular lee este prefijo para construir la URL de la API y las rutas del router.
        $tags[] = '<meta name="presencard-root" content="' . self::escape($basePath) . '">';

        if (isset($meta['robots'])) {
            $tags[] = $this->nameTag('robots', $meta['robots']);
        }

        $tags[] = $this->propertyTag('og:site_name', 'Presencard');
        $tags[] = $this->propertyTag('og:locale', 'es_VE');
        $tags[] = $this->propertyTag('og:type', $meta['type'] ?? 'website');
        $tags[] = $this->propertyTag('og:title', $meta['ogTitle'] ?? $title);
        $tags[] = $this->nameTag('twitter:card', isset($meta['image']) ? 'summary_large_image' : 'summary');

        if (isset($meta['image'])) {
            $tags[] = $this->propertyTag('og:image', $meta['image']);

            foreach (['imageWidth' => 'og:image:width', 'imageHeight' => 'og:image:height'] as $key => $property) {
                if (isset($meta[$key])) {
                    $tags[] = $this->propertyTag($property, (string) $meta[$key]);
                }
            }
        }

        if (isset($meta['description'])) {
            $tags[] = $this->nameTag('description', $meta['description']);
            $tags[] = $this->propertyTag('og:description', $meta['description']);
        }

        if (isset($meta['url'])) {
            $tags[] = $this->propertyTag('og:url', $meta['url']);
            $tags[] = '<link rel="canonical" href="' . self::escape($meta['url']) . '">';
        }

        return "    " . implode("\n    ", $tags) . "\n";
    }

    private function nameTag(string $name, string $content): string
    {
        return sprintf('<meta name="%s" content="%s">', $name, self::escape($content));
    }

    private function propertyTag(string $property, string $content): string
    {
        return sprintf('<meta property="%s" content="%s">', $property, self::escape($content));
    }

    /** @param array<string, mixed> $state */
    private function stateScript(array $state): string
    {
        // JSON_HEX_* neutraliza "</script>" y similares dentro del JSON embebido.
        $json = json_encode(
            $state,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return '<script type="application/json" id="presencard-state">' . $json . "</script>\n";
    }

    /** @param array<string, string> $meta */
    private function loadShell(array $meta): string
    {
        if (is_file($this->indexHtmlPath)) {
            return (string) file_get_contents($this->indexHtmlPath);
        }

        // Sin build de Angular (p. ej. en desarrollo): HTML mínimo, con contenido para quien no ejecuta JS.
        $heading = self::escape($meta['title']);
        $description = isset($meta['description']) ? '<p>' . self::escape($meta['description']) . '</p>' : '';

        return <<<HTML
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <app-root></app-root>
    <noscript><h1>{$heading}</h1>{$description}</noscript>
</body>
</html>
HTML;
    }

    private function insertBefore(string $html, string $marker, string $content): string
    {
        $position = strripos($html, $marker);

        if ($position === false) {
            return $html . $content;
        }

        return substr($html, 0, $position) . $content . substr($html, $position);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
