<?php

declare(strict_types=1);

namespace App\Tests\Presencard\Infrastructure;

use App\Presencard\Application\Query\GetPublicPresencardHandler;
use App\Presencard\Domain\Exception\PresencardNotFound;
use App\Presencard\Infrastructure\Http\PublicCardPresenter;
use App\Presencard\Infrastructure\Http\PublicUrlBuilder;
use App\Presencard\Infrastructure\Http\VCard\VCardBuilder;
use App\Presencard\Infrastructure\Http\Visitor\VCardController;
use App\Tests\Presencard\Support\InMemoryPresencardRepository;
use App\Tests\Presencard\Support\PresencardMother;
use PHPUnit\Framework\TestCase;

final class VCardControllerTest extends TestCase
{
    private VCardController $controller;

    protected function setUp(): void
    {
        $repository = new InMemoryPresencardRepository();
        $repository->save(PresencardMother::create('aB3dE5gH7jK9', 'María Fernanda Rojas', 'Gerente, Proyectos', 'maria@pafar.net'));

        $this->controller = new VCardController(
            new GetPublicPresencardHandler($repository),
            new PublicCardPresenter(new PublicUrlBuilder('https://pafar.net/presencard/')),
            new VCardBuilder()
        );
    }

    public function testRespondsWithAVCardAttachment(): void
    {
        $response = ($this->controller)('aB3dE5gH7jK9');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/vcard; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename=maria-fernanda-rojas.vcf', $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        $this->assertStringStartsWith("BEGIN:VCARD\r\nVERSION:3.0\r\n", (string) $response->getContent());
    }

    public function testIncludesTheCardDataAndItsPublicUrl(): void
    {
        $content = (string) ($this->controller)('aB3dE5gH7jK9')->getContent();

        $this->assertStringContainsString("FN:María Fernanda Rojas\r\n", $content);
        $this->assertStringContainsString("TITLE:Gerente\\, Proyectos\r\n", $content);
        $this->assertStringContainsString("EMAIL;TYPE=INTERNET:maria@pafar.net\r\n", $content);
        $this->assertStringContainsString("URL:https://pafar.net/presencard/c/aB3dE5gH7jK9\r\n", $content);
    }

    public function testIsCacheableBrieflyBecauseItIsPublic(): void
    {
        $response = ($this->controller)('aB3dE5gH7jK9');

        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
        $this->assertSame(60, $response->getMaxAge());
    }

    public function testUnknownOrMalformedHashIsNotFound(): void
    {
        foreach (['zzzzzzzzzzzz', '../etc/passwd'] as $hash) {
            try {
                ($this->controller)($hash);
                $this->fail('Se esperaba PresencardNotFound');
            } catch (PresencardNotFound $e) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
