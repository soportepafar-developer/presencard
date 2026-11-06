<?php

declare(strict_types=1);

namespace App\Presencard\Infrastructure\Http;

final class PublicUrlBuilder
{
    private string $publicUrl;

    public function __construct(string $publicUrl)
    {
        $this->publicUrl = rtrim($publicUrl, '/');
    }

    public function forHashId(string $hashId): string
    {
        return sprintf('%s/c/%s', $this->publicUrl, $hashId);
    }

    /** URL absoluta de un recurso de la app (p. ej. "/app/og-image.png"). */
    public function forPath(string $path): string
    {
        return $this->publicUrl . '/' . ltrim($path, '/');
    }
}
