<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Security;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Freno a la fuerza bruta sobre el login: tras demasiados intentos fallidos se bloquea el acceso un rato,
 * aunque después se escriba la contraseña correcta.
 *
 * Dos contadores: uno por correo + IP (5 fallos) y otro por IP (30 fallos, para quien prueba muchos correos).
 * Al llegar al tope se bloquea durante 15 minutos. Los contadores viven en una caché propia (var/throttle) que
 * no se borra al vaciar var/cache en una actualización.
 */
final class LoginAttemptLimiter
{
    public const MAX_PER_ACCOUNT = 5;
    public const MAX_PER_IP = 30;
    public const WINDOW_SECONDS = 900;

    private CacheItemPoolInterface $cache;
    /** @var callable(): int */
    private $clock;

    public function __construct(CacheItemPoolInterface $cache, ?callable $clock = null)
    {
        $this->cache = $cache;
        $this->clock = $clock ?? static function (): int {
            return time();
        };
    }

    /** @return array{0: string, 1: string} [correo normalizado, IP] */
    public static function identify(Request $request): array
    {
        $body = json_decode($request->getContent(), true);
        $email = is_array($body) && isset($body['email']) && is_string($body['email']) ? $body['email'] : '';

        return [mb_strtolower(trim($email)), $request->getClientIp() ?? '0.0.0.0'];
    }

    /** Segundos que faltan para poder volver a intentarlo (0 si no está bloqueado). */
    public function retryAfter(string $email, string $ip): int
    {
        $now = $this->now();
        $until = max(
            $this->lockedUntil($this->accountKey($email, $ip), $now),
            $this->lockedUntil($this->ipKey($ip), $now)
        );

        return max(0, $until - $now);
    }

    public function isBlocked(string $email, string $ip): bool
    {
        return $this->retryAfter($email, $ip) > 0;
    }

    public function recordFailure(string $email, string $ip): void
    {
        $this->hit($this->accountKey($email, $ip), self::MAX_PER_ACCOUNT);
        $this->hit($this->ipKey($ip), self::MAX_PER_IP);
    }

    /** Un acceso correcto limpia el contador de esa cuenta (el de la IP se conserva). */
    public function reset(string $email, string $ip): void
    {
        $this->cache->deleteItem($this->accountKey($email, $ip));
    }

    private function hit(string $key, int $max): void
    {
        $now = $this->now();
        $item = $this->cache->getItem($key);
        $data = $this->read($item->isHit() ? $item->get() : null, $now);
        ++$data['count'];

        if ($data['count'] >= $max) {
            $data['lockedUntil'] = $now + self::WINDOW_SECONDS;
        }

        $item->set($data);
        $item->expiresAfter(self::WINDOW_SECONDS);
        $this->cache->save($item);
    }

    private function lockedUntil(string $key, int $now): int
    {
        $item = $this->cache->getItem($key);

        return $this->read($item->isHit() ? $item->get() : null, $now)['lockedUntil'];
    }

    /**
     * @param mixed $stored
     *
     * @return array{count: int, lockedUntil: int}
     */
    private function read($stored, int $now): array
    {
        if (!is_array($stored) || !isset($stored['count'], $stored['lockedUntil'])) {
            return ['count' => 0, 'lockedUntil' => 0];
        }

        // Cuando termina el bloqueo, el contador empieza de cero.
        if ($stored['lockedUntil'] > 0 && $stored['lockedUntil'] <= $now) {
            return ['count' => 0, 'lockedUntil' => 0];
        }

        return ['count' => (int) $stored['count'], 'lockedUntil' => (int) $stored['lockedUntil']];
    }

    private function accountKey(string $email, string $ip): string
    {
        return 'account.' . hash('sha256', mb_strtolower(trim($email)) . '|' . $ip);
    }

    private function ipKey(string $ip): string
    {
        return 'ip.' . hash('sha256', $ip);
    }

    private function now(): int
    {
        return (int) ($this->clock)();
    }
}
