<?php

declare(strict_types=1);

namespace App\Tests\Presencard\Application;

use App\Presencard\Application\Actor;
use App\Presencard\Application\Query\GetPresencardHandler;
use App\Presencard\Application\Query\GetPublicPresencardHandler;
use App\Presencard\Application\Query\ListPresencardsHandler;
use App\Presencard\Domain\Exception\PresencardNotFound;
use App\Tests\Presencard\Support\FakeOwnerDirectory;
use App\Tests\Presencard\Support\InMemoryPresencardRepository;
use App\Tests\Presencard\Support\PresencardMother;
use PHPUnit\Framework\TestCase;

final class QueryHandlersTest extends TestCase
{
    private InMemoryPresencardRepository $repository;
    private FakeOwnerDirectory $owners;
    private Actor $admin;

    protected function setUp(): void
    {
        $this->repository = new InMemoryPresencardRepository();
        // Dueños: Ana y María son del usuario 1; Luis es del usuario 2.
        $this->repository->save(PresencardMother::create('aaaaaaaaaaaa', 'Ana Pérez', 'CTO', 'ana@pafar.com.ve', 1));
        $this->repository->save(PresencardMother::create('bbbbbbbbbbbb', 'Luis Gómez', 'Gerente', 'luis@pafar.com.ve', 2));
        $this->repository->save(PresencardMother::create('cccccccccccc', 'María Díaz', 'CTO', 'maria@jpl.com', 1));
        $this->owners = new FakeOwnerDirectory([1 => 'Usuario Uno', 2 => 'Usuario Dos']);
        $this->admin = Actor::administrator(99);
    }

    private function list(): ListPresencardsHandler
    {
        return new ListPresencardsHandler($this->repository, $this->owners);
    }

    public function testAdministratorListsEveryCardNewestFirstWithOwnerNames(): void
    {
        $page = $this->list()->handle($this->admin, null, 1, 2);

        $this->assertSame(['María Díaz', 'Luis Gómez'], array_column($page->toArray()['items'], 'fullName'));
        $this->assertSame(['Usuario Uno', 'Usuario Dos'], array_column($page->toArray()['items'], 'ownerName'));
        $this->assertSame(3, $page->total);
        $this->assertSame(2, $page->totalPages());
    }

    public function testRegularUserOnlySeesTheirOwnCards(): void
    {
        $page = $this->list()->handle(Actor::regularUser(2), null);

        $this->assertSame(1, $page->total);
        $this->assertSame('Luis Gómez', $page->items[0]->fullName);
    }

    public function testRegularUserWithoutCardsGetsAnEmptyPage(): void
    {
        $page = $this->list()->handle(Actor::regularUser(3), null);

        $this->assertSame(0, $page->total);
        $this->assertSame([], $page->items);
    }

    public function testSearchOnlyLooksInsideWhatTheActorCanSee(): void
    {
        $page = $this->list()->handle(Actor::regularUser(1), 'cto');

        $this->assertSame(2, $page->total);

        $this->assertSame(0, $this->list()->handle(Actor::regularUser(2), 'cto')->total);
    }

    public function testListSecondPage(): void
    {
        $page = $this->list()->handle($this->admin, null, 2, 2);

        $this->assertCount(1, $page->items);
        $this->assertSame('Ana Pérez', $page->items[0]->fullName);
    }

    public function testListFiltersBySearchTermIgnoringCaseAndSpaces(): void
    {
        $this->assertSame(2, $this->list()->handle($this->admin, '  CTO ', 1, 10)->total);
    }

    public function testListNormalizesInvalidPagination(): void
    {
        $page = $this->list()->handle($this->admin, '', 0, 1000);

        $this->assertSame(1, $page->page);
        $this->assertSame(ListPresencardsHandler::MAX_PER_PAGE, $page->perPage);
        $this->assertSame(3, $page->total);
    }

    public function testListOfEmptyResultStillHasOnePage(): void
    {
        $page = $this->list()->handle($this->admin, 'inexistente');

        $this->assertSame(0, $page->total);
        $this->assertSame(1, $page->totalPages());
    }

    public function testOwnerNameIsNullWhenTheOwnerNoLongerExists(): void
    {
        $page = (new ListPresencardsHandler($this->repository, new FakeOwnerDirectory([])))->handle($this->admin, null);

        $this->assertNull($page->items[0]->ownerName);
    }

    public function testGetByIdForAdministratorAndOwner(): void
    {
        $handler = new GetPresencardHandler($this->repository, $this->owners);

        $this->assertSame('Luis Gómez', $handler->handle($this->admin, 2)->fullName);
        $this->assertSame('Usuario Dos', $handler->handle(Actor::regularUser(2), 2)->ownerName);
    }

    public function testGetByIdFailsForSomeoneElsesCardAndForMissingOnes(): void
    {
        $handler = new GetPresencardHandler($this->repository, $this->owners);

        foreach ([[Actor::regularUser(1), 2], [$this->admin, 99]] as [$actor, $id]) {
            try {
                $handler->handle($actor, $id);
                $this->fail('Se esperaba PresencardNotFound');
            } catch (PresencardNotFound $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testCardsWithoutOptionalDataAreListedSearchedAndPublished(): void
    {
        $bare = \App\Presencard\Domain\Presencard::create(
            \App\Presencard\Domain\HashId::fromString('dddddddddddd'),
            1,
            'Sin Datos Aún',
            null,
            null,
            null,
            null,
            null,
            null,
            new \DateTimeImmutable()
        );
        $this->repository->save($bare);

        $found = $this->list()->handle($this->admin, 'sin datos');
        $this->assertSame(1, $found->total);
        $this->assertNull($found->items[0]->jobTitle);

        $this->assertSame(4, $this->list()->handle($this->admin, null)->total, 'las cards con datos nulos no rompen el listado');
        $this->assertNull((new GetPublicPresencardHandler($this->repository))->handle('dddddddddddd')->email);
    }

    public function testGetPublicByHashReturnsViewWithoutNeedingAnActor(): void
    {
        $view = (new GetPublicPresencardHandler($this->repository))->handle('bbbbbbbbbbbb');

        $this->assertSame('Luis Gómez', $view->fullName);
    }

    /**
     * @dataProvider unknownOrMalformedHashes
     */
    public function testGetPublicTreatsUnknownOrMalformedHashAsNotFound(string $hash): void
    {
        $this->expectException(PresencardNotFound::class);

        (new GetPublicPresencardHandler($this->repository))->handle($hash);
    }

    public function unknownOrMalformedHashes(): array
    {
        return [
            'no existe' => ['zzzzzzzzzzzz'],
            'formato inválido' => ['../../etc/passwd'],
            'vacío' => [''],
        ];
    }
}
