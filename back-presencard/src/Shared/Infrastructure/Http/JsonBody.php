<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

/**
 * Lectura estricta del cuerpo JSON. Exigir application/json evita que un formulario
 * de otro sitio (text/plain) pueda disparar acciones con la sesión del admin.
 */
final class JsonBody
{
    /** @return array<string, mixed> */
    public static function decode(Request $request): array
    {
        if (stripos((string) $request->headers->get('Content-Type'), 'application/json') !== 0) {
            throw new UnsupportedMediaTypeHttpException('El cuerpo debe enviarse como application/json.');
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            throw new BadRequestHttpException('El cuerpo de la petición no es un JSON válido.');
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    public static function string(array $data, string $key): string
    {
        return self::nullableString($data, $key) ?? '';
    }

    /** @param array<string, mixed> $data */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (is_string($value)) {
            return $value;
        }

        return is_int($value) ? (string) $value : null;
    }

    /** @param array<string, mixed> $data */
    public static function bool(array $data, string $key): ?bool
    {
        $value = $data[$key] ?? null;

        return is_bool($value) ? $value : null;
    }
}
