<?php

declare(strict_types=1);

namespace App\Tests\Presencard\Infrastructure;

use App\Presencard\Application\Query\GetPublicPresencardHandler;
use App\Presencard\Domain\HashId;
use App\Presencard\Domain\Presencard;
use App\Presencard\Infrastructure\Http\PublicCardPresenter;
use App\Presencard\Infrastructure\Http\PublicUrlBuilder;
use App\Presencard\Infrastructure\Http\Seo\SpaShellRenderer;
use App\Presencard\Infrastructure\Http\Visitor\CardPageController;
use App\Tests\Presencard\Support\InMemoryPresencardRepository;
use App\Tests\Presencard\Support\PresencardMother;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class CardPageControllerTest extends TestCase
{
    private InMemoryPresencardRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryPresencardRepository();
        $this->repository->save(PresencardMother::create('aB3dE5gH7jK9', 'María Rojas', 'Gerente', 'maria@pafar.net'));
        $this->repository->save(Presencard::create(HashId::fromString('bbbbbbbbbbbb'), 1, 'Solo Nombre', null, null, null, null, null, null, new DateTimeImmutable()));
    }

    private function controller(string $ogImagePath = ''): CardPageController
    {
        $urls = new PublicUrlBuilder('https://pafar.net/presencard');

        // Sin build de Angular el renderer usa su HTML de respaldo: suficiente para comprobar las meta tags.
        return new CardPageController(
            new GetPublicPresencardHandler($this->repository),
            new PublicCardPresenter($urls),
            new SpaShellRenderer('/ruta/que/no/existe/index.html'),
            $urls,
            $ogImagePath
        );
    }

    private function get(string $hash, string $ogImagePath = '', ?Request $request = null): \Symfony\Component\HttpFoundation\Response
    {
        return ($this->controller($ogImagePath))($hash, $request ?? Request::create('/c/' . $hash));
    }

    public function testCardPagesAreNotIndexedByGoogle(): void
    {
        $response = $this->get('aB3dE5gH7jK9');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', (string) $response->getContent());
    }

    public function testLinkPreviewTagsCarryTheNameAndTheRole(): void
    {
        $html = (string) $this->get('aB3dE5gH7jK9')->getContent();

        $this->assertStringContainsString('<meta property="og:title" content="María Rojas">', $html);
        $this->assertStringContainsString('<meta property="og:description" content="Gerente · Consultores JPL">', $html);
        $this->assertStringContainsString('<meta property="og:url" content="https://pafar.net/presencard/c/aB3dE5gH7jK9">', $html);
    }

    public function testWithoutAnImageConfiguredThereIsNoOgImageAndTheCardIsSmall(): void
    {
        $html = (string) $this->get('aB3dE5gH7jK9')->getContent();

        $this->assertStringNotContainsString('og:image', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary">', $html);
    }

    public function testWithAnImageConfiguredTheAbsoluteUrlAndSizeArePublished(): void
    {
        $html = (string) $this->get('aB3dE5gH7jK9', '/app/og-image.png')->getContent();

        $this->assertStringContainsString('<meta property="og:image" content="https://pafar.net/presencard/app/og-image.png">', $html);
        $this->assertStringContainsString('<meta property="og:image:width" content="1200">', $html);
        $this->assertStringContainsString('<meta property="og:image:height" content="630">', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
    }

    public function testACardWithOnlyANameHasNoDescription(): void
    {
        $html = (string) $this->get('bbbbbbbbbbbb')->getContent();

        $this->assertStringContainsString('<meta property="og:title" content="Solo Nombre">', $html);
        $this->assertStringNotContainsString('og:description', $html);
    }

    public function testUnknownCardsAreNotFoundNotIndexedAndNotCached(): void
    {
        $response = $this->get('zzzzzzzzzzzz');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('<meta name="robots" content="noindex">', (string) $response->getContent());
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function testPublishesTheSubfolderPrefixForAngular(): void
    {
        $request = Request::create('/presencard/c/aB3dE5gH7jK9', 'GET', [], [], [], [
            'SCRIPT_NAME' => '/presencard/index.php',
            'SCRIPT_FILENAME' => '/home/u/public_html/presencard/index.php',
            'PHP_SELF' => '/presencard/index.php',
        ]);

        $html = (string) $this->get('aB3dE5gH7jK9', '', $request)->getContent();

        $this->assertStringContainsString('<meta name="presencard-root" content="/presencard">', $html);
    }
}
