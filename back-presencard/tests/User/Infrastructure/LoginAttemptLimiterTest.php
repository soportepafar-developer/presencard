<?php

declare(strict_types=1);

namespace App\Tests\User\Infrastructure;

use App\User\Infrastructure\Security\LoginAttemptLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;

final class LoginAttemptLimiterTest extends TestCase
{
    private const IP = '203.0.113.9';

    private int $now = 1_000_000;
    private LoginAttemptLimiter $limiter;

    protected function setUp(): void
    {
        $this->limiter = new LoginAttemptLimiter(new ArrayAdapter(), function (): int {
            return $this->now;
        });
    }

    private function failLogin(string $email = 'ana@pafar.net', string $ip = self::IP, int $times = 1): void
    {
        for ($i = 0; $i < $times; ++$i) {
            $this->limiter->recordFailure($email, $ip);
        }
    }

    public function testStartsOpen(): void
    {
        $this->assertFalse($this->limiter->isBlocked('ana@pafar.net', self::IP));
        $this->assertSame(0, $this->limiter->retryAfter('ana@pafar.net', self::IP));
    }

    public function testBlocksAfterFiveFailedAttemptsForTheSameAccountAndIp(): void
    {
        $this->failLogin('ana@pafar.net', self::IP, 4);
        $this->assertFalse($this->limiter->isBlocked('ana@pafar.net', self::IP), 'con 4 fallos aún puede intentar');

        $this->failLogin();
        $this->assertTrue($this->limiter->isBlocked('ana@pafar.net', self::IP));
        $this->assertSame(LoginAttemptLimiter::WINDOW_SECONDS, $this->limiter->retryAfter('ana@pafar.net', self::IP));
    }

    public function testTheLockLastsFifteenMinutesAndThenTheCounterStartsOver(): void
    {
        $this->failLogin('ana@pafar.net', self::IP, 5);

        $this->now += 899;
        $this->assertTrue($this->limiter->isBlocked('ana@pafar.net', self::IP));
        $this->assertSame(1, $this->limiter->retryAfter('ana@pafar.net', self::IP));

        $this->now += 1;
        $this->assertFalse($this->limiter->isBlocked('ana@pafar.net', self::IP));

        // Tras el desbloqueo hacen falta otros 5 fallos, no 1.
        $this->failLogin('ana@pafar.net', self::IP, 4);
        $this->assertFalse($this->limiter->isBlocked('ana@pafar.net', self::IP));
    }

    public function testOtherAccountsAndOtherIpsAreNotAffected(): void
    {
        $this->failLogin('ana@pafar.net', self::IP, 5);

        $this->assertFalse($this->limiter->isBlocked('luis@pafar.net', self::IP), 'otro correo desde la misma IP');
        $this->assertFalse($this->limiter->isBlocked('ana@pafar.net', '198.51.100.4'), 'el mismo correo desde otra IP');
    }

    public function testEmailsAreComparedIgnoringCaseAndSpaces(): void
    {
        $this->failLogin('  ANA@Pafar.net ', self::IP, 5);

        $this->assertTrue($this->limiter->isBlocked('ana@pafar.net', self::IP));
    }

    public function testASuccessfulLoginResetsTheAccountCounter(): void
    {
        $this->failLogin('ana@pafar.net', self::IP, 4);
        $this->limiter->reset('ana@pafar.net', self::IP);
        $this->failLogin('ana@pafar.net', self::IP, 4);

        $this->assertFalse($this->limiter->isBlocked('ana@pafar.net', self::IP));
    }

    public function testAnIpTryingManyDifferentEmailsGetsBlockedAtThirtyFailures(): void
    {
        for ($i = 0; $i < LoginAttemptLimiter::MAX_PER_IP; ++$i) {
            $this->assertFalse($this->limiter->isBlocked('probando' . $i . '@x.com', self::IP), "intento $i");
            $this->failLogin('probando' . $i . '@x.com');
        }

        $this->assertTrue($this->limiter->isBlocked('uno-nuevo@x.com', self::IP), 'la IP completa queda bloqueada');
        $this->assertFalse($this->limiter->isBlocked('uno-nuevo@x.com', '198.51.100.4'));
    }

    public function testRemainingTimeShrinksAsTimePasses(): void
    {
        $this->failLogin('ana@pafar.net', self::IP, 5);
        $this->now += 300;

        $this->assertSame(600, $this->limiter->retryAfter('ana@pafar.net', self::IP));
    }

    public function testIdentifiesTheAttemptFromTheJsonBodyAndTheClientIp(): void
    {
        $request = Request::create('/api/login', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.9', 'CONTENT_TYPE' => 'application/json'], '{"email":"  Ana@Pafar.net ","password":"x"}');

        $this->assertSame(['ana@pafar.net', '203.0.113.9'], LoginAttemptLimiter::identify($request));
    }

    public function testIdentifyToleratesMalformedBodies(): void
    {
        foreach (['', '{no es json', '[]', '{"email":123}', '"texto"'] as $body) {
            $request = Request::create('/api/login', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.9'], $body);

            $this->assertSame(['', '203.0.113.9'], LoginAttemptLimiter::identify($request), $body);
        }
    }
}
