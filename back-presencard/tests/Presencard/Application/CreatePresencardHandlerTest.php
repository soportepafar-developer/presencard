<?php

declare(strict_types=1);

namespace App\Tests\Presencard\Application;

use App\Presencard\Application\Create\CreatePresencardCommand;
use App\Presencard\Application\Create\CreatePresencardHandler;
use App\Presencard\Domain\Exception\InvalidPresencard;
use App\Tests\Presencard\Support\InMemoryPresencardRepository;
use App\Tests\Presencard\Support\PresencardMother;
use App\Tests\Presencard\Support\SequenceHashGenerator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CreatePresencardHandlerTest extends TestCase
{
    private InMemoryPresencardRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryPresencardRepository();
    }

    public function testCreatesAndPersistsPresencardWithGeneratedHash(): void
    {
        $handler = new CreatePresencardHandler($this->repository, new SequenceHashGenerator('Zz9Yy8Xx7Ww6'));

        $view = $handler->handle(new CreatePresencardCommand(7, 
            'Jaime Padrón',
            'CEO',
            'Pafar Ingeniería',
            'jaileop@yahoo.com',
            '+58 412-3258691',
            '584123258691',
            '@pafar_ve'
        ));

        $this->assertSame(1, $view->id);
        $this->assertSame(7, $view->ownerId);
        $this->assertSame('Pafar Ingeniería', $view->company);
        $this->assertTrue($this->repository->byId(1)->isOwnedBy(7));
        $this->assertSame('Zz9Yy8Xx7Ww6', $view->hashId);
        $this->assertSame('pafar_ve', $view->instagram);
        $this->assertSame('https://enlacesmultiples.pafar.com.ve/universodesoluciones/', $view->universoSolucionesUrl);
        $this->assertSame(1, $this->repository->count(null));
    }

    public function testCreatesACardWithOnlyTheName(): void
    {
        $handler = new CreatePresencardHandler($this->repository, new SequenceHashGenerator('Zz9Yy8Xx7Ww6'));

        $view = $handler->handle(new CreatePresencardCommand(7, 'Persona Sin Datos', null, null, null, null));

        $this->assertSame('Persona Sin Datos', $view->fullName);
        $this->assertNull($view->jobTitle);
        $this->assertNull($view->company);
        $this->assertNull($view->email);
        $this->assertNull($view->phone);
        $this->assertSame('https://enlacesmultiples.pafar.com.ve/universodesoluciones/', $view->universoSolucionesUrl);
        $this->assertSame(1, $this->repository->count(null));
    }

    public function testStillRejectsAMalformedEmailWhenOneIsProvided(): void
    {
        $handler = new CreatePresencardHandler($this->repository, new SequenceHashGenerator('Zz9Yy8Xx7Ww6'));

        $this->expectException(InvalidPresencard::class);

        $handler->handle(new CreatePresencardCommand(7, 'Ana', null, null, 'no-es-correo', null));
    }

    public function testRetriesWhenGeneratedHashAlreadyExists(): void
    {
        $this->repository->save(PresencardMother::create('aaaaaaaaaaaa'));
        $generator = new SequenceHashGenerator('aaaaaaaaaaaa', 'aaaaaaaaaaaa', 'bbbbbbbbbbbb');
        $handler = new CreatePresencardHandler($this->repository, $generator);

        $view = $handler->handle(new CreatePresencardCommand(7, 'Ana', 'CTO', null, 'ana@pafar.com.ve', '0412-1112233'));

        $this->assertSame('bbbbbbbbbbbb', $view->hashId);
        $this->assertSame(3, $generator->calls());
    }

    public function testFailsWhenNoUniqueHashCanBeGenerated(): void
    {
        $this->repository->save(PresencardMother::create('aaaaaaaaaaaa'));
        $handler = new CreatePresencardHandler($this->repository, new SequenceHashGenerator('aaaaaaaaaaaa'));

        $this->expectException(RuntimeException::class);

        $handler->handle(new CreatePresencardCommand(7, 'Ana', 'CTO', null, 'ana@pafar.com.ve', '0412-1112233'));
    }

    public function testDoesNotPersistWhenDataIsInvalid(): void
    {
        $handler = new CreatePresencardHandler($this->repository, new SequenceHashGenerator());

        try {
            $handler->handle(new CreatePresencardCommand(7, '', 'CTO', null, 'ana@pafar.com.ve', '0412-1112233'));
            $this->fail('Se esperaba InvalidPresencard');
        } catch (InvalidPresencard $e) {
            $this->assertSame(0, $this->repository->count(null));
        }
    }
}
