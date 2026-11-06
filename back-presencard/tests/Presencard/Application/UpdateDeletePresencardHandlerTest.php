<?php

declare(strict_types=1);

namespace App\Tests\Presencard\Application;

use App\Presencard\Application\Actor;
use App\Presencard\Application\Delete\DeletePresencardHandler;
use App\Presencard\Application\Update\UpdatePresencardCommand;
use App\Presencard\Application\Update\UpdatePresencardHandler;
use App\Presencard\Domain\Exception\InvalidPresencard;
use App\Presencard\Domain\Exception\PresencardNotFound;
use App\Tests\Presencard\Support\InMemoryPresencardRepository;
use App\Tests\Presencard\Support\PresencardMother;
use PHPUnit\Framework\TestCase;

final class UpdateDeletePresencardHandlerTest extends TestCase
{
    private const OWNER = 1;
    private const OTHER_USER = 2;
    private const ADMIN = 99;

    private InMemoryPresencardRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryPresencardRepository();
        $this->repository->save(PresencardMother::create('aaaaaaaaaaaa', 'Jaime Padrón', 'CEO', 'jaileop@yahoo.com', self::OWNER));
    }

    public function testOwnerUpdatesDataAndKeepsHashIdAndOwner(): void
    {
        $handler = new UpdatePresencardHandler($this->repository);

        $view = $handler->handle($this->update(Actor::regularUser(self::OWNER), 1, 'Otro Nombre'));

        $this->assertSame('Otro Nombre', $view->fullName);
        $this->assertSame('Nueva Empresa', $view->company);
        $this->assertSame('aaaaaaaaaaaa', $view->hashId);
        $this->assertSame(self::OWNER, $view->ownerId);
        $this->assertNull($view->whatsapp);
        $this->assertSame('Otro Nombre', $this->repository->byId(1)->fullName());
    }

    public function testAdministratorCanUpdateAnyCardWithoutTakingOwnership(): void
    {
        $view = (new UpdatePresencardHandler($this->repository))->handle($this->update(Actor::administrator(self::ADMIN), 1, 'Editada'));

        $this->assertSame('Editada', $view->fullName);
        $this->assertSame(self::OWNER, $view->ownerId);
    }

    public function testRegularUserCannotUpdateSomeoneElsesCardAndItLooksNonexistent(): void
    {
        $this->expectException(PresencardNotFound::class);

        (new UpdatePresencardHandler($this->repository))->handle($this->update(Actor::regularUser(self::OTHER_USER), 1, 'Hack'));
    }

    public function testRejectedUpdateLeavesTheCardUntouched(): void
    {
        try {
            (new UpdatePresencardHandler($this->repository))->handle($this->update(Actor::regularUser(self::OTHER_USER), 1, 'Hack'));
        } catch (PresencardNotFound $e) {
            $this->assertSame('Jaime Padrón', $this->repository->byId(1)->fullName());
        }
    }

    public function testUpdateFailsWhenPresencardDoesNotExist(): void
    {
        $this->expectException(PresencardNotFound::class);

        (new UpdatePresencardHandler($this->repository))->handle($this->update(Actor::administrator(self::ADMIN), 99, 'X'));
    }

    public function testUpdateRejectsInvalidData(): void
    {
        $this->expectException(InvalidPresencard::class);

        (new UpdatePresencardHandler($this->repository))->handle($this->update(Actor::administrator(self::ADMIN), 1, ''));
    }

    public function testOwnerDeletesTheirCard(): void
    {
        (new DeletePresencardHandler($this->repository))->handle(Actor::regularUser(self::OWNER), 1);

        $this->assertNull($this->repository->byId(1));
    }

    public function testAdministratorDeletesAnyCard(): void
    {
        (new DeletePresencardHandler($this->repository))->handle(Actor::administrator(self::ADMIN), 1);

        $this->assertNull($this->repository->byId(1));
    }

    public function testRegularUserCannotDeleteSomeoneElsesCard(): void
    {
        try {
            (new DeletePresencardHandler($this->repository))->handle(Actor::regularUser(self::OTHER_USER), 1);
            $this->fail('Se esperaba PresencardNotFound');
        } catch (PresencardNotFound $e) {
            $this->assertNotNull($this->repository->byId(1), 'la tarjeta ajena sigue existiendo');
        }
    }

    public function testDeleteFailsWhenPresencardDoesNotExist(): void
    {
        $this->expectException(PresencardNotFound::class);

        (new DeletePresencardHandler($this->repository))->handle(Actor::administrator(self::ADMIN), 99);
    }

    private function update(Actor $actor, int $id, string $fullName): UpdatePresencardCommand
    {
        return new UpdatePresencardCommand($actor, $id, $fullName, 'Gerente', 'Nueva Empresa', 'otro@pafar.com.ve', '0414-0000000');
    }
}
