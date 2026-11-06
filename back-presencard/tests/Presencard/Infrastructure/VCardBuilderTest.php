<?php

declare(strict_types=1);

namespace App\Tests\Presencard\Infrastructure;

use App\Presencard\Infrastructure\Http\VCard\VCardBuilder;
use PHPUnit\Framework\TestCase;

final class VCardBuilderTest extends TestCase
{
    private VCardBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new VCardBuilder();
    }

    /** @return array<string, mixed> */
    private function card(array $override = []): array
    {
        return array_merge([
            'hashId' => 'aB3dE5gH7jK9',
            'fullName' => 'Jaime Leonardo Padrón Faria',
            'jobTitle' => 'Socio/a, Fundador o CEO',
            'company' => 'Consultores JPL / Pafar Ingeniería / Somos 3D3',
            'email' => 'jaileop@yahoo.com',
            'phone' => '584123258691',
            'whatsapp' => null,
            'instagram' => null,
            'telegram' => null,
            'publicUrl' => 'https://pafar.net/presencard/c/aB3dE5gH7jK9',
        ], $override);
    }

    /** @return string[] */
    private function lines(string $vcard): array
    {
        return explode("\r\n", rtrim($vcard, "\r\n"));
    }

    public function testBuildsTheContactFromTheUsersExample(): void
    {
        $expected = implode("\r\n", [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'N:;Jaime Leonardo Padrón Faria;;;',
            'FN:Jaime Leonardo Padrón Faria',
            'ORG:Consultores JPL / Pafar Ingeniería / Somos 3D3',
            'TITLE:Socio/a\, Fundador o CEO',
            'TEL;TYPE=CELL:584123258691',
            'EMAIL;TYPE=INTERNET:jaileop@yahoo.com',
            'URL:https://pafar.net/presencard/c/aB3dE5gH7jK9',
            'END:VCARD',
        ]) . "\r\n";

        $this->assertSame($expected, $this->builder->build($this->card()));
    }

    public function testUsesOnlyCrlfLineEndingsAndEndsWithOne(): void
    {
        $vcard = $this->builder->build($this->card());

        $this->assertSame(0, preg_match('/(?<!\r)\n/', $vcard), 'no debe haber saltos de línea sueltos');
        $this->assertSame("\r\n", substr($vcard, -2));
    }

    public function testOmitsEverythingThatIsMissing(): void
    {
        $vcard = $this->builder->build($this->card([
            'jobTitle' => null, 'company' => ' ', 'email' => '', 'phone' => null, 'publicUrl' => null,
        ]));

        $this->assertSame(['BEGIN:VCARD', 'VERSION:3.0', 'N:;Jaime Leonardo Padrón Faria;;;', 'FN:Jaime Leonardo Padrón Faria', 'END:VCARD'], $this->lines($vcard));
    }

    public function testNormalizesThePhoneKeepingTheLeadingPlus(): void
    {
        $this->assertContains('TEL;TYPE=CELL:+584123258691', $this->lines($this->builder->build($this->card(['phone' => '+58 412-325.8691']))));
        $this->assertContains('TEL;TYPE=CELL:04123258691', $this->lines($this->builder->build($this->card(['phone' => '(0412) 325 8691']))));
    }

    public function testIgnoresAPhoneWithoutDigits(): void
    {
        $this->assertNotContains('TEL;TYPE=CELL:', $this->lines($this->builder->build($this->card(['phone' => 'sin teléfono']))));
        $this->assertSame(0, count(preg_grep('/^TEL/', $this->lines($this->builder->build($this->card(['phone' => '---']))))));
    }

    public function testAddsTheWhatsappOnlyWhenItIsADifferentNumber(): void
    {
        $same = $this->lines($this->builder->build($this->card(['phone' => '+58 412-3258691', 'whatsapp' => '584123258691'])));
        $this->assertCount(1, preg_grep('/^TEL/', $same));

        $different = $this->lines($this->builder->build($this->card(['phone' => '0212-5551234', 'whatsapp' => '584123258691'])));
        $this->assertSame(['TEL;TYPE=CELL:02125551234', 'TEL;TYPE=CELL:+584123258691'], array_values(preg_grep('/^TEL/', $different)));

        $onlyWhatsapp = $this->lines($this->builder->build($this->card(['phone' => null, 'whatsapp' => '584123258691'])));
        $this->assertSame(['TEL;TYPE=CELL:+584123258691'], array_values(preg_grep('/^TEL/', $onlyWhatsapp)));
    }

    public function testAddsInstagramAsASocialProfile(): void
    {
        $lines = $this->lines($this->builder->build($this->card(['instagram' => 'pafar_ve'])));

        $this->assertContains('X-SOCIALPROFILE;TYPE=instagram:https://instagram.com/pafar_ve', $lines);
    }

    public function testAddsTelegramAsASocialProfile(): void
    {
        $lines = $this->lines($this->builder->build($this->card(['telegram' => 'pafar_ve'])));

        $this->assertContains('X-SOCIALPROFILE;TYPE=telegram:https://t.me/pafar_ve', $lines);
    }

    public function testEscapesSpecialCharactersAndNewlines(): void
    {
        $vcard = $this->builder->build($this->card([
            'fullName' => 'Ana; "Pérez", \\ González',
            'company' => "Empresa A, S.A.\nPiso 2",
            'jobTitle' => 'Dir.; Comercial, Norte',
        ]));
        $lines = $this->lines($vcard);

        $this->assertContains('FN:Ana\; "Pérez"\, \\\\ González', $lines);
        $this->assertContains('ORG:Empresa A\, S.A.\nPiso 2', $lines);
        $this->assertContains('TITLE:Dir.\; Comercial\, Norte', $lines);
        $this->assertSame(1, count(preg_grep('/^ORG/', $lines)), 'el salto de línea del texto no debe partir la propiedad');
    }

    public function testFoldsLongLinesAtSeventyFiveOctetsWithoutBreakingMultibyteCharacters(): void
    {
        $company = str_repeat('Ingeniería Ñandú Corporación Éxito ', 8);
        $vcard = $this->builder->build($this->card(['company' => trim($company)]));

        foreach ($this->lines($vcard) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line), $line);
            $this->assertTrue(mb_check_encoding($line, 'UTF-8'), 'carácter partido: ' . bin2hex($line));
        }

        // Al "desplegar" (quitar CRLF + espacio) se recupera el texto íntegro.
        $unfolded = str_replace("\r\n ", '', $vcard);
        $this->assertStringContainsString('ORG:' . trim($company), $unfolded);
        $this->assertStringContainsString("\r\n ", $vcard, 'debió plegarse al menos una línea');
    }

    public function testDoesNotFoldShortLines(): void
    {
        $this->assertStringNotContainsString("\r\n ", $this->builder->build($this->card()));
    }

    public function testBuildsFilenameFromTheNameWithoutAccentsOrSymbols(): void
    {
        $this->assertSame('jaime-leonardo-padron-faria.vcf', $this->builder->filename('Jaime Leonardo Padrón Faria', 'hash'));
        $this->assertSame('nandu-ano-ana-pena.vcf', $this->builder->filename('  Ñandú, Año / Ana Peña ', 'hash'));
        $this->assertSame('maria-script.vcf', $this->builder->filename('MARÍA "<script>"', 'hash'), 'los símbolos nunca llegan al nombre de archivo');
    }

    public function testFallsBackToTheHashWhenTheNameHasNothingUsable(): void
    {
        $this->assertSame('aB3dE5gH7jK9.vcf', $this->builder->filename('***', 'aB3dE5gH7jK9'));
    }
}
