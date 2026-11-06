<?php

declare(strict_types=1);

namespace App\Tests\Presencard\Infrastructure;

use App\Presencard\Application\Query\GetPublicPresencardHandler;
use App\Presencard\Infrastructure\Http\PublicCardPresenter;
use App\Presencard\Infrastructure\Http\PublicUrlBuilder;
use App\Presencard\Infrastructure\Http\Visitor\PublicApiController;
use App\Tests\Presencard\Support\InMemoryPresencardRepository;
use App\Tests\Presencard\Support\PresencardMother;
use PHPUnit\Framework\TestCase;

final class PublicApiAndUrlTest extends TestCase
{
    public function testThePublicApiIsNotIndexedAndNeverExposesInternalIds(): void
    {
        $repository = new InMemoryPresencardRepository();
        $repository->save(PresencardMother::create('aB3dE5gH7jK9', 'María Rojas', 'Gerente', 'maria@pafar.net'));
        $controller = new PublicApiController(
            new GetPublicPresencardHandler($repository),
            new PublicCardPresenter(new PublicUrlBuilder('https://pafar.net/presencard'))
        );

        $response = $controller('aB3dE5gH7jK9');
        $data = json_decode((string) $response->getContent(), true);

        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        $this->assertSame('María Rojas', $data['fullName']);
        $this->assertArrayNotHasKey('id', $data);
        $this->assertArrayNotHasKey('ownerId', $data);
    }

    public function testBuildsAbsoluteUrlsForCardsAndResourcesWithOrWithoutSlashes(): void
    {
        $urls = new PublicUrlBuilder('https://pafar.net/presencard/');

        $this->assertSame('https://pafar.net/presencard/c/aB3dE5gH7jK9', $urls->forHashId('aB3dE5gH7jK9'));
        $this->assertSame('https://pafar.net/presencard/app/og-image.png', $urls->forPath('/app/og-image.png'));
        $this->assertSame('https://pafar.net/presencard/app/og-image.png', $urls->forPath('app/og-image.png'));
    }
}
