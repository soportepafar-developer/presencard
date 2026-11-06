<?php

declare(strict_types=1);

namespace App\Tests\User\Application;

use App\Tests\User\Support\FakePasswordHasher;
use App\Tests\User\Support\InMemoryUserRepository;
use App\Tests\User\Support\SpyCardOwnershipTransfer;
use App\Tests\User\Support\UserMother;
use App\User\Application\Create\CreateUserCommand;
use App\User\Application\Create\CreateUserHandler;
use App\User\Application\Delete\DeleteUserHandler;
use App\User\Application\EmailAvailability;
use App\User\Application\LastAdminGuard;
use App\User\Application\Profile\ChangePasswordHandler;
use App\User\Application\Profile\UpdateProfileHandler;
use App\User\Application\Query\GetUserHandler;
use App\User\Application\Query\ListUsersHandler;
use App\User\Application\ResetPassword\ResetUserPasswordHandler;
use App\User\Application\SetActive\SetUserActiveHandler;
use App\User\Application\Update\UpdateUserCommand;
use App\User\Application\Update\UpdateUserHandler;
use App\User\Domain\Exception\InvalidUser;
use App\User\Domain\Exception\OperationNotAllowed;
use App\User\Domain\Exception\UserNotFound;
use App\User\Domain\UserRole;
use PHPUnit\Framework\TestCase;

final class UserHandlersTest extends TestCase
{
    private const ADMIN_ID = 1;
    private const ANA_ID = 2;
    private const LUIS_ID = 3;

    private InMemoryUserRepository $users;
    private FakePasswordHasher $hasher;
    private SpyCardOwnershipTransfer $transfers;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->hasher = new FakePasswordHasher();
        $this->transfers = new SpyCardOwnershipTransfer();

        $this->users->save(UserMother::admin('admin@pafar.net', 'Admin'));
        $this->users->save(UserMother::regular('ana@pafar.net', 'Ana Pérez'));
        $this->users->save(UserMother::regular('luis@pafar.net', 'Luis Gómez'));
    }

    private function create(): CreateUserHandler
    {
        return new CreateUserHandler($this->users, $this->hasher, new EmailAvailability($this->users));
    }

    private function update(): UpdateUserHandler
    {
        return new UpdateUserHandler($this->users, new EmailAvailability($this->users), new LastAdminGuard($this->users));
    }

    private function setActive(): SetUserActiveHandler
    {
        return new SetUserActiveHandler($this->users, new LastAdminGuard($this->users));
    }

    private function delete(): DeleteUserHandler
    {
        return new DeleteUserHandler($this->users, new LastAdminGuard($this->users), $this->transfers);
    }

    // --- Alta ---------------------------------------------------------------------------------

    public function testCreateUserHashesPasswordAndNeverExposesIt(): void
    {
        $view = $this->create()->handle(new CreateUserCommand('nuevo@pafar.net', 'Nuevo Usuario', 'clave-temporal-1', UserRole::USER));

        $this->assertSame(4, $view->id);
        $this->assertSame(UserRole::USER, $view->role);
        $this->assertTrue($view->active);
        $this->assertSame('hashed:clave-temporal-1', $this->users->byId(4)->passwordHash());
        $this->assertArrayNotHasKey('passwordHash', $view->toArray());
        $this->assertArrayNotHasKey('password', $view->toArray());
    }

    public function testCreateUserRejectsDuplicatedEmailIgnoringCase(): void
    {
        $this->expectException(InvalidUser::class);
        $this->expectExceptionMessageMatches('/ya está registrado/');

        $this->create()->handle(new CreateUserCommand(' ANA@pafar.net ', 'Otra Ana', 'clave-temporal-1', UserRole::USER));
    }

    public function testCreateUserRejectsWeakPasswordAndInvalidRoleWithoutSaving(): void
    {
        foreach ([['clave', UserRole::USER], ['clave-temporal-1', 'ROLE_ROOT']] as [$password, $role]) {
            try {
                $this->create()->handle(new CreateUserCommand('nuevo@pafar.net', 'Nuevo', $password, $role));
                $this->fail('Se esperaba InvalidUser');
            } catch (InvalidUser $e) {
                $this->assertNull($this->users->byEmail('nuevo@pafar.net'));
            }
        }
    }

    // --- Edición --------------------------------------------------------------------------------

    public function testUpdateUserChangesNameEmailAndRole(): void
    {
        $view = $this->update()->handle(new UpdateUserCommand(self::ADMIN_ID, self::ANA_ID, 'Ana M. Pérez', 'ana.m@pafar.net', UserRole::ADMIN));

        $this->assertSame('Ana M. Pérez', $view->fullName);
        $this->assertSame('ana.m@pafar.net', $view->email);
        $this->assertSame(UserRole::ADMIN, $view->role);
    }

    public function testUpdateUserCanKeepItsOwnEmail(): void
    {
        $view = $this->update()->handle(new UpdateUserCommand(self::ADMIN_ID, self::ANA_ID, 'Ana', 'ana@pafar.net', UserRole::USER));

        $this->assertSame('ana@pafar.net', $view->email);
    }

    public function testUpdateUserRejectsAnEmailUsedByAnotherUser(): void
    {
        $this->expectException(InvalidUser::class);

        $this->update()->handle(new UpdateUserCommand(self::ADMIN_ID, self::ANA_ID, 'Ana', 'luis@pafar.net', UserRole::USER));
    }

    public function testAdministratorCannotChangeTheirOwnRole(): void
    {
        $this->expectException(OperationNotAllowed::class);
        $this->expectExceptionMessageMatches('/propia cuenta/');

        $this->update()->handle(new UpdateUserCommand(self::ADMIN_ID, self::ADMIN_ID, 'Admin', 'admin@pafar.net', UserRole::USER));
    }

    public function testTheLastActiveAdministratorCannotBeDemoted(): void
    {
        // Quien actúa es otra cuenta (id 50) para saltar la regla de "propia cuenta".
        $this->expectException(OperationNotAllowed::class);
        $this->expectExceptionMessageMatches('/al menos un administrador/');

        $this->update()->handle(new UpdateUserCommand(50, self::ADMIN_ID, 'Admin', 'admin@pafar.net', UserRole::USER));
    }

    public function testAnAdministratorCanBeDemotedWhenAnotherActiveOneExists(): void
    {
        $this->users->save(UserMother::admin('otro@pafar.net', 'Otro Admin'));

        $view = $this->update()->handle(new UpdateUserCommand(4, self::ADMIN_ID, 'Admin', 'admin@pafar.net', UserRole::USER));

        $this->assertSame(UserRole::USER, $view->role);
    }

    public function testUpdateUserFailsWhenMissing(): void
    {
        $this->expectException(UserNotFound::class);

        $this->update()->handle(new UpdateUserCommand(self::ADMIN_ID, 99, 'X', 'x@pafar.net', UserRole::USER));
    }

    // --- Restablecer contraseña ---------------------------------------------------------------------

    public function testAdminResetsAnotherUsersPassword(): void
    {
        (new ResetUserPasswordHandler($this->users, $this->hasher))->handle(self::ANA_ID, 'otra-clave-123');

        $this->assertSame('hashed:otra-clave-123', $this->users->byId(self::ANA_ID)->passwordHash());
    }

    public function testResetPasswordRejectsWeakPasswordAndMissingUser(): void
    {
        $handler = new ResetUserPasswordHandler($this->users, $this->hasher);

        try {
            $handler->handle(self::ANA_ID, 'corta');
            $this->fail('Se esperaba InvalidUser');
        } catch (InvalidUser $e) {
            $this->assertSame('hashed:secreto123', $this->users->byId(self::ANA_ID)->passwordHash());
        }

        $this->expectException(UserNotFound::class);
        $handler->handle(99, 'otra-clave-123');
    }

    // --- Activar / desactivar ----------------------------------------------------------------------

    public function testDeactivateAndReactivateAnotherUser(): void
    {
        $off = $this->setActive()->handle(self::ADMIN_ID, self::ANA_ID, false);
        $this->assertFalse($off->active);
        $this->assertFalse($this->users->byId(self::ANA_ID)->isActive());

        $on = $this->setActive()->handle(self::ADMIN_ID, self::ANA_ID, true);
        $this->assertTrue($on->active);
    }

    public function testCannotDeactivateYourOwnAccount(): void
    {
        $this->expectException(OperationNotAllowed::class);

        $this->setActive()->handle(self::ADMIN_ID, self::ADMIN_ID, false);
    }

    public function testCannotDeactivateTheLastActiveAdministrator(): void
    {
        $this->expectException(OperationNotAllowed::class);

        $this->setActive()->handle(50, self::ADMIN_ID, false);
    }

    public function testSetActiveFailsWhenMissing(): void
    {
        $this->expectException(UserNotFound::class);

        $this->setActive()->handle(self::ADMIN_ID, 99, false);
    }

    // --- Eliminar ---------------------------------------------------------------------------------

    public function testDeleteTransfersTheCardsToTheActingAdministratorThenRemovesTheUser(): void
    {
        $this->delete()->handle(self::ADMIN_ID, self::ANA_ID);

        $this->assertSame([[self::ANA_ID, self::ADMIN_ID]], $this->transfers->transfers);
        $this->assertNull($this->users->byId(self::ANA_ID));
    }

    public function testCannotDeleteYourOwnAccount(): void
    {
        try {
            $this->delete()->handle(self::ADMIN_ID, self::ADMIN_ID);
            $this->fail('Se esperaba OperationNotAllowed');
        } catch (OperationNotAllowed $e) {
            $this->assertNotNull($this->users->byId(self::ADMIN_ID));
            $this->assertSame([], $this->transfers->transfers, 'no se tocan las tarjetas si se rechaza el borrado');
        }
    }

    public function testCannotDeleteTheLastActiveAdministrator(): void
    {
        try {
            $this->delete()->handle(50, self::ADMIN_ID);
            $this->fail('Se esperaba OperationNotAllowed');
        } catch (OperationNotAllowed $e) {
            $this->assertNotNull($this->users->byId(self::ADMIN_ID));
            $this->assertSame([], $this->transfers->transfers);
        }
    }

    public function testDeleteFailsWhenMissing(): void
    {
        $this->expectException(UserNotFound::class);

        $this->delete()->handle(self::ADMIN_ID, 99);
    }

    // --- Consultas --------------------------------------------------------------------------------

    public function testListUsersOrderedByNameWithPagination(): void
    {
        $page = (new ListUsersHandler($this->users))->handle(null, 1, 2);

        $this->assertSame(['Admin', 'Ana Pérez'], array_column($page->toArray()['items'], 'fullName'));
        $this->assertSame(3, $page->total);
        $this->assertSame(2, $page->totalPages());
    }

    public function testListUsersSearchesByNameOrEmailAndNormalizesPagination(): void
    {
        $handler = new ListUsersHandler($this->users);

        $this->assertSame(1, $handler->handle('  LUIS ')->total);
        $this->assertSame(1, $handler->handle('ana@')->total);
        $this->assertSame(ListUsersHandler::MAX_PER_PAGE, $handler->handle('', 0, 999)->perPage);
        $this->assertSame(1, $handler->handle('nadie')->totalPages());
    }

    public function testGetUser(): void
    {
        $this->assertSame('Luis Gómez', (new GetUserHandler($this->users))->handle(self::LUIS_ID)->fullName);

        $this->expectException(UserNotFound::class);
        (new GetUserHandler($this->users))->handle(99);
    }

    // --- Perfil (el propio usuario) ----------------------------------------------------------------------

    public function testUserUpdatesTheirOwnNameAndEmail(): void
    {
        $view = (new UpdateProfileHandler($this->users, new EmailAvailability($this->users)))
            ->handle(self::ANA_ID, 'Ana Pérez de Mora', 'ana.mora@pafar.net');

        $this->assertSame('Ana Pérez de Mora', $view->fullName);
        $this->assertSame('ana.mora@pafar.net', $view->email);
        $this->assertSame(UserRole::USER, $view->role, 'el perfil nunca cambia el rol');
    }

    public function testProfileRejectsEmailOfAnotherUserButAllowsOwnEmail(): void
    {
        $handler = new UpdateProfileHandler($this->users, new EmailAvailability($this->users));

        $this->assertSame('ana@pafar.net', $handler->handle(self::ANA_ID, 'Ana', 'ana@pafar.net')->email);

        $this->expectException(InvalidUser::class);
        $handler->handle(self::ANA_ID, 'Ana', 'luis@pafar.net');
    }

    public function testChangePasswordRequiresTheCurrentOne(): void
    {
        $handler = new ChangePasswordHandler($this->users, $this->hasher);

        $handler->handle(self::ANA_ID, 'secreto123', 'nueva-clave-456');

        $this->assertSame('hashed:nueva-clave-456', $this->users->byId(self::ANA_ID)->passwordHash());
    }

    /**
     * @dataProvider rejectedPasswordChanges
     */
    public function testChangePasswordRejections(string $current, string $new, string $messagePart): void
    {
        $handler = new ChangePasswordHandler($this->users, $this->hasher);

        try {
            $handler->handle(self::ANA_ID, $current, $new);
            $this->fail('Se esperaba InvalidUser');
        } catch (InvalidUser $e) {
            $this->assertStringContainsString($messagePart, $e->getMessage());
            $this->assertSame('hashed:secreto123', $this->users->byId(self::ANA_ID)->passwordHash());
        }
    }

    public function rejectedPasswordChanges(): array
    {
        return [
            'actual incorrecta' => ['equivocada1', 'nueva-clave-456', 'contraseña actual no es correcta'],
            'nueva débil' => ['secreto123', 'corta', 'entre 8 y 72'],
            'nueva igual a la actual' => ['secreto123', 'secreto123', 'distinta de la actual'],
        ];
    }

    public function testChangePasswordFailsWhenUserIsMissing(): void
    {
        $this->expectException(UserNotFound::class);

        (new ChangePasswordHandler($this->users, $this->hasher))->handle(99, 'a', 'bbbbbbbbb');
    }
}
