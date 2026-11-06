<?php

declare(strict_types=1);

namespace App\Tests\User\Infrastructure;

use App\Tests\User\Support\UserMother;
use App\User\Domain\UserRole;
use App\User\Infrastructure\Security\ActiveUserChecker;
use App\User\Infrastructure\Security\JsonLoginFailureHandler;
use App\User\Infrastructure\Security\LoginAttemptLimiter;
use App\User\Infrastructure\Security\SecurityUser;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

final class SecurityUserTest extends TestCase
{
    private function user(string $role = UserRole::USER, bool $active = true, string $hash = 'h', string $email = 'a@b.co'): SecurityUser
    {
        return new SecurityUser(5, $email, $hash, $role, $active);
    }

    public function testAdministratorInheritsUserRole(): void
    {
        $this->assertSame([UserRole::ADMIN, UserRole::USER], $this->user(UserRole::ADMIN)->getRoles());
        $this->assertSame([UserRole::USER], $this->user(UserRole::USER)->getRoles());
        $this->assertTrue($this->user(UserRole::ADMIN)->isAdmin());
    }

    public function testBuildsFromDomainUser(): void
    {
        $domain = UserMother::admin('admin@pafar.net');
        $reflection = new \ReflectionProperty($domain, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($domain, 9);

        $user = SecurityUser::fromUser($domain);

        $this->assertSame(9, $user->id());
        $this->assertSame('admin@pafar.net', $user->getUsername());
        $this->assertSame('hashed:secreto123', $user->getPassword());
    }

    public function testSessionSurvivesAnEmailChange(): void
    {
        $this->assertTrue($this->user()->isEqualTo($this->user(UserRole::USER, true, 'h', 'otro@correo.co')));
    }

    /**
     * @dataProvider changesThatMustEndTheSession
     */
    public function testSessionEndsWhenPasswordRoleOrStatusChange(SecurityUser $changed): void
    {
        $this->assertFalse($this->user()->isEqualTo($changed));
    }

    public function changesThatMustEndTheSession(): array
    {
        return [
            'nueva contraseña' => [new SecurityUser(5, 'a@b.co', 'otro-hash', UserRole::USER, true)],
            'cambio de rol' => [new SecurityUser(5, 'a@b.co', 'h', UserRole::ADMIN, true)],
            'cuenta desactivada' => [new SecurityUser(5, 'a@b.co', 'h', UserRole::USER, false)],
            'otro usuario' => [new SecurityUser(6, 'a@b.co', 'h', UserRole::USER, true)],
        ];
    }

    public function testInactiveAccountsAreRejectedAfterTheirPasswordIsChecked(): void
    {
        $checker = new ActiveUserChecker();

        $checker->checkPreAuth($this->user(UserRole::USER, false)); // no revela nada antes de validar la clave
        $checker->checkPostAuth($this->user());
        $this->addToAssertionCount(2);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $checker->checkPostAuth($this->user(UserRole::USER, false));
    }

    public function testLoginFailureResponses(): void
    {
        $handler = new JsonLoginFailureHandler(new NullLogger(), new LoginAttemptLimiter(new ArrayAdapter()));

        $bad = $handler->onAuthenticationFailure(new Request(), new BadCredentialsException());
        $this->assertSame(401, $bad->getStatusCode());
        $this->assertSame(['message' => 'Correo o contraseña incorrectos.'], json_decode($bad->getContent(), true));

        $disabled = $handler->onAuthenticationFailure(new Request(), new CustomUserMessageAccountStatusException('Tu cuenta está desactivada.'));
        $this->assertSame(403, $disabled->getStatusCode());
        $this->assertSame(['message' => 'Tu cuenta está desactivada.'], json_decode($disabled->getContent(), true));
    }

    public function testADatabaseOutageIsNotReportedAsWrongCredentials(): void
    {
        $logger = new class() extends NullLogger {
            /** @var string[] */
            public array $errors = [];

            public function error($message, array $context = []): void
            {
                $this->errors[] = (string) $message;
            }
        };

        $response = (new JsonLoginFailureHandler($logger, new LoginAttemptLimiter(new ArrayAdapter())))->onAuthenticationFailure(
            new Request(),
            new AuthenticationServiceException('Fallo', 0, new \RuntimeException('SQLSTATE[HY000] [2002] Connection refused'))
        );

        $this->assertSame(503, $response->getStatusCode());
        $this->assertStringContainsString('Inténtalo de nuevo', json_decode($response->getContent(), true)['message']);
        $this->assertStringNotContainsString('contraseña', json_decode($response->getContent(), true)['message']);
        $this->assertCount(1, $logger->errors);
        $this->assertStringContainsString('Connection refused', $logger->errors[0], 'la causa real queda en el log');
    }

    /** @return array{0: JsonLoginFailureHandler, 1: LoginAttemptLimiter} */
    private function handlerWithLimiter(): array
    {
        $limiter = new LoginAttemptLimiter(new ArrayAdapter());

        return [new JsonLoginFailureHandler(new NullLogger(), $limiter), $limiter];
    }

    private function loginRequest(string $email): Request
    {
        return Request::create('/api/login', 'POST', [], [], [], ['REMOTE_ADDR' => '10.0.0.7', 'CONTENT_TYPE' => 'application/json'], (string) json_encode(['email' => $email, 'password' => 'x']));
    }

    public function testWrongCredentialsCountTowardsTheLoginLimit(): void
    {
        [$handler, $limiter] = $this->handlerWithLimiter();

        for ($i = 0; $i < LoginAttemptLimiter::MAX_PER_ACCOUNT; ++$i) {
            $handler->onAuthenticationFailure($this->loginRequest('Ana@Pafar.net'), new BadCredentialsException());
        }

        $this->assertTrue($limiter->isBlocked('ana@pafar.net', '10.0.0.7'));
    }

    public function testInfrastructureErrorsAndDisabledAccountsDoNotCountTowardsTheLimit(): void
    {
        [$handler, $limiter] = $this->handlerWithLimiter();

        for ($i = 0; $i < LoginAttemptLimiter::MAX_PER_ACCOUNT + 2; ++$i) {
            $handler->onAuthenticationFailure($this->loginRequest('ana@pafar.net'), new AuthenticationServiceException('BD caída'));
            $handler->onAuthenticationFailure($this->loginRequest('ana@pafar.net'), new CustomUserMessageAccountStatusException('Desactivada'));
        }

        $this->assertFalse($limiter->isBlocked('ana@pafar.net', '10.0.0.7'));
    }
}
