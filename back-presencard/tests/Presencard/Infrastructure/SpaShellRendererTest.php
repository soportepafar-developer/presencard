<?php

declare(strict_types=1);

namespace App\Tests\Presencard\Infrastructure;

use App\Presencard\Infrastructure\Http\Seo\SpaShellRenderer;
use PHPUnit\Framework\TestCase;

final class SpaShellRendererTest extends TestCase
{
    private const SHELL = <<<HTML
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Presencard</title>
  <base href="/app/">
</head>
<body>
  <app-root></app-root>
  <script src="main.js"></script>
</body>
</html>
HTML;

    private string $shellPath;

    protected function setUp(): void
    {
        $this->shellPath = tempnam(sys_get_temp_dir(), 'shell');
        file_put_contents($this->shellPath, self::SHELL);
    }

    protected function tearDown(): void
    {
        @unlink($this->shellPath);
    }

    public function testInjectsOpenGraphTagsAndReplacesTitle(): void
    {
        $html = (new SpaShellRenderer($this->shellPath))->render([
            'title' => 'Ana Pérez · CTO',
            'ogTitle' => 'Ana Pérez',
            'description' => 'CTO',
            'url' => 'https://tarjetas.test/c/aaaaaaaaaaaa',
            'type' => 'profile',
        ]);

        $this->assertSame(1, substr_count($html, '<title>'));
        $this->assertStringContainsString('<title>Ana Pérez · CTO</title>', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Ana Pérez">', $html);
        $this->assertStringContainsString('<meta property="og:description" content="CTO">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="profile">', $html);
        $this->assertStringContainsString('<meta property="og:url" content="https://tarjetas.test/c/aaaaaaaaaaaa">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://tarjetas.test/c/aaaaaaaaaaaa">', $html);
        $this->assertLessThan(strpos($html, '</head>'), strpos($html, 'og:title'));
        $this->assertStringContainsString('<app-root></app-root>', $html, 'conserva el shell de Angular');
    }

    public function testEscapesHtmlInMetaTags(): void
    {
        $html = (new SpaShellRenderer($this->shellPath))->render([
            'title' => 'x',
            'ogTitle' => 'Jaime "JP" <script>alert(1)</script> & Co',
            'description' => "O'Brien",
        ]);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString(
            'content="Jaime &quot;JP&quot; &lt;script&gt;alert(1)&lt;/script&gt; &amp; Co"',
            $html
        );
        $this->assertStringContainsString('content="O&#039;Brien"', $html);
    }

    public function testEmbedsStateWithoutAllowingScriptBreakout(): void
    {
        $html = (new SpaShellRenderer($this->shellPath))->render(
            ['title' => 'x'],
            ['card' => ['fullName' => '</script><script>alert(1)</script>', 'jobTitle' => 'Año & ñandú']]
        );

        $this->assertSame(1, preg_match('#<script type="application/json" id="presencard-state">(.*?)</script>#s', $html, $match));
        $this->assertStringNotContainsString('<', $match[1]);

        $decoded = json_decode($match[1], true);
        $this->assertSame('</script><script>alert(1)</script>', $decoded['card']['fullName']);
        $this->assertSame('Año & ñandú', $decoded['card']['jobTitle']);
        $this->assertLessThan(strpos($html, '</body>'), strpos($html, 'presencard-state'));
    }

    public function testOmitsStateScriptWhenThereIsNoState(): void
    {
        $html = (new SpaShellRenderer($this->shellPath))->render(['title' => 'x', 'robots' => 'noindex']);

        $this->assertStringNotContainsString('presencard-state', $html);
        $this->assertStringContainsString('<meta name="robots" content="noindex">', $html);
    }

    public function testFallsBackToMinimalHtmlWhenAngularBuildIsMissing(): void
    {
        $html = (new SpaShellRenderer('/ruta/que/no/existe/index.html'))->render([
            'title' => 'Ana <b>Pérez</b>',
            'description' => 'CTO',
        ]);

        $this->assertStringContainsString('<title>Ana &lt;b&gt;Pérez&lt;/b&gt;</title>', $html);
        $this->assertStringContainsString('<noscript><h1>Ana &lt;b&gt;Pérez&lt;/b&gt;</h1><p>CTO</p></noscript>', $html);
    }

    public function testAdjustsBaseHrefAndPublishesTheAppRootWhenInASubfolder(): void
    {
        $html = (new SpaShellRenderer($this->shellPath))->render(['title' => 'x'], null, '/presencard');

        $this->assertStringContainsString('<base href="/presencard/app/">', $html);
        $this->assertStringNotContainsString('<base href="/app/">', $html);
        $this->assertStringContainsString('<meta name="presencard-root" content="/presencard">', $html);
        $this->assertSame(1, substr_count($html, '<base '));
    }

    public function testKeepsRootInstallationsWorking(): void
    {
        $html = (new SpaShellRenderer($this->shellPath))->render(['title' => 'x']);

        $this->assertStringContainsString('<base href="/app/">', $html);
        $this->assertStringContainsString('<meta name="presencard-root" content="">', $html);
    }

    public function testNormalizesTrailingSlashAndEscapesTheBasePath(): void
    {
        $html = (new SpaShellRenderer($this->shellPath))->render(['title' => 'x'], null, '/presencard/');
        $this->assertStringContainsString('<base href="/presencard/app/">', $html);

        $hostile = (new SpaShellRenderer($this->shellPath))->render(['title' => 'x'], null, '/a"><script>alert(1)</script>');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $hostile);
    }
}
