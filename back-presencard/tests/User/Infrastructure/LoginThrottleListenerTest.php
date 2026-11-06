<?php

declare(strict_types=1);

namespace App\Tests\User\Infrastructure;

use App\User\Infrastructure\Security\LoginAttemptLimiter;
use App\User\Infrastructure\Security\LoginThrottleListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class LoginThrottleListenerTest extends TestCase
{
    private LoginAttemptLimiter $limiter;
    private LoginThrottleListener $listener;

    protected function setUp(): void
    {
        $this->limiter = new LoginAttemptLimiter(new ArrayAdapter());
        $this->listener = new LoginThrottleListener($this->limiter);
    }

    private function request(string $path = '/api/login', string $method = 'POST', string $email = 'ana@pafar.net'): Request
    {
        return Request::create($path, $method, [], [], [], ['REMOTE_ADDR' => '203.0.113.9', 'CONTENT_TYPE' => 'application/json'], (string) json_encode(['email' => $email, 'password' => 'x']));
    }

    private function dispatch(Request $request, int $type = HttpKernelInterface::MASTER_REQUEST): RequestEvent
    {
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, $type);
        $this->listener->onKernelRequest($event);

        return $event;
    }

    private function exhaust(): void
    {
        for ($i = 0; $i < LoginAttemptLimiter::MAX_PER_ACCOUNT; ++$i) {
            $this->limiter->recordFailure('ana@pafar.net', '203.0.113.9');
        }
    }

    public function testRunsBeforeTheSecurityFirewall(): void
    {
        $this->assertGreaterThan(8, LoginThrottleListener::getSubscribedEvents()[KernelEvents::REQUEST][1]);
    }

    public function testLetsNormalLoginAttemptsThrough(): void
    {
        $this->assertFalse($this->dispatch($this->request())->hasResponse());
    }

    public function testRespondsTooManyRequestsWithRetryAfterWhenTheLimitIsReached(): void
    {
        $this->exhaust();

        $response = $this->dispatch($this->request())->getResponse();

        $this->assertNotNull($response);
        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame((string) LoginAttemptLimiter::WINDOW_SECONDS, $response->headers->get('Retry-After'));
        $this->assertSame('Demasiados intentos fallidos. Inténtalo de nuevo en 15 minuto(s).', json_decode($response->getContent(), true)['message']);
    }

    public function testDoesNotBlockOtherAccountsOrOtherEndpoints(): void
    {
        $this->exhaust();

        $this->assertFalse($this->dispatch($this->request('/api/login', 'POST', 'luis@pafar.net'))->hasResponse());
        $this->assertFalse($this->dispatch($this->request('/api/me', 'GET'))->hasResponse());
        $this->assertFalse($this->dispatch($this->request('/api/cards', 'POST'))->hasResponse());
        $this->assertFalse($this->dispatch($this->request('/api/login', 'GET'))->hasResponse(), 'solo el POST de login');
    }

    public function testIgnoresSubRequests(): void
    {
        $this->exhaust();

        $this->assertFalse($this->dispatch($this->request(), HttpKernelInterface::SUB_REQUEST)->hasResponse());
    }
}
