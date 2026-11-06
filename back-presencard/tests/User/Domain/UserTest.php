<?php

declare(strict_types=1);

namespace App\Tests\User\Domain;

use App\Tests\User\Support\UserMother;
use App\User\Domain\Exception\InvalidUser;
use App\User\Domain\PasswordPolicy;
use App\User\Domain\User;
use App\User\Domain\UserRole;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testRegisterNormalizesAndStartsActive(): void
    {
        $now = new DateTimeImmutable('2026-10-21 10:00:00');

        $user = User::register('  Ana@Pafar.net ', '  Ana Pérez ', 'hash', UserRole::USER, $now);

        $this->assertNull($user->id());
        $this->assertSame('Ana@Pafar.net', $user->email());
        $this->assertSame('Ana Pérez', $user->fullName());
        $this->assertSame('hash', $user->passwordHash());
        $this->assertSame(UserRole::USER, $user->role());
        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->isActive());
        $this->assertSame($now, $user->createdAt());
    }

    /**
     * @dataProvider invalidInputs
     */
    public function testRegisterRejectsInvalidData(string $email, string $name, string $hash, string $role, string $messagePart): void
    {
        $this->expectException(InvalidUser::class);
        $this->expectExceptionMessageMatches('/' . preg_quote($messagePart, '/') . '/');

        User::register($email, $name, $hash, $role, new DateTimeImmutable());
    }

    public function invalidInputs(): array
    {
        return [
            'nombre vacío' => ['a@b.co', '  ', 'h', UserRole::USER, 'nombre'],
            'nombre largo' => ['a@b.co', str_repeat('a', 151), 'h', UserRole::USER, 'nombre'],
            'correo inválido' => ['no-es-correo', 'Ana', 'h', UserRole::USER, 'correo'],
            'correo vacío' => ['', 'Ana', 'h', UserRole::USER, 'correo'],
            'rol inválido' => ['a@b.co', 'Ana', 'h', 'ROLE_ROOT', 'rol'],
            'hash vacío' => ['a@b.co', 'Ana', '', UserRole::USER, 'hash'],
        ];
    }

    public function testUpdateProfileChangesNameAndEmailAndTimestamp(): void
    {
        $user = UserMother::regular();
        $later = new DateTimeImmutable('2026-11-01 08:00:00');

        $user->updateProfile('Ana María Pérez', 'ana.maria@pafar.net', $later);

        $this->assertSame('Ana María Pérez', $user->fullName());
        $this->assertSame('ana.maria@pafar.net', $user->email());
        $this->assertSame($later, $user->updatedAt());
    }

    public function testInvalidProfileUpdateDoesNotChangeAnything(): void
    {
        $user = UserMother::regular();

        try {
            $user->updateProfile('Nuevo', 'no-valido', new DateTimeImmutable());
            $this->fail('Se esperaba InvalidUser');
        } catch (InvalidUser $e) {
            $this->assertSame('Ana Pérez', $user->fullName());
            $this->assertSame('ana@pafar.net', $user->email());
        }
    }

    public function testRoleAndActivationAndPasswordChanges(): void
    {
        $user = UserMother::regular();
        $now = new DateTimeImmutable('2026-11-01 08:00:00');

        $user->changeRole(UserRole::ADMIN, $now);
        $this->assertTrue($user->isAdmin());

        $user->deactivate($now);
        $this->assertFalse($user->isActive());

        $user->activate($now);
        $this->assertTrue($user->isActive());

        $user->changePasswordHash('nuevo-hash', $now);
        $this->assertSame('nuevo-hash', $user->passwordHash());
    }

    public function testChangeRoleRejectsUnknownRole(): void
    {
        $this->expectException(InvalidUser::class);

        UserMother::regular()->changeRole('ROLE_ROOT', new DateTimeImmutable());
    }

    /**
     * @dataProvider passwords
     */
    public function testPasswordPolicy(string $password, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(InvalidUser::class);
        }

        PasswordPolicy::assertValid($password);
        $this->addToAssertionCount(1);
    }

    public function passwords(): array
    {
        return [
            'mínimo exacto' => ['12345678', true],
            'con símbolos y tildes' => ['Contraseña-Segura 2026', true],
            'máximo 72 bytes' => [str_repeat('a', 72), true],
            'corta' => ['1234567', false],
            'vacía' => ['', false],
            'más de 72 bytes' => [str_repeat('a', 73), false],
        ];
    }
}
