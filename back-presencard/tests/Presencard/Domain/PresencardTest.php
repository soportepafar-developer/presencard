<?php

declare(strict_types=1);

namespace App\Tests\Presencard\Domain;

use App\Presencard\Domain\Exception\InvalidPresencard;
use App\Presencard\Domain\HashId;
use App\Presencard\Domain\Presencard;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PresencardTest extends TestCase
{
    public function testCreateNormalizesAndExposesData(): void
    {
        $now = new DateTimeImmutable('2026-10-21 10:00:00');

        $card = Presencard::create(
            HashId::fromString('aaaaaaaaaaaa'),
            7,
            '  Jaime Padrón  ',
            ' CEO ',
            '  Consultores JPL ',
            ' jaileop@yahoo.com ',
            '+58 412-3258691',
            '+58 (412) 325-8691',
            '@pafar_ve',
            $now
        );

        $this->assertNull($card->id());
        $this->assertSame(7, $card->ownerId());
        $this->assertTrue($card->isOwnedBy(7));
        $this->assertFalse($card->isOwnedBy(8));
        $this->assertSame('aaaaaaaaaaaa', $card->hashId()->value());
        $this->assertSame('Jaime Padrón', $card->fullName());
        $this->assertSame('CEO', $card->jobTitle());
        $this->assertSame('Consultores JPL', $card->company());
        $this->assertSame('jaileop@yahoo.com', $card->email());
        $this->assertSame('+58 412-3258691', $card->phone());
        $this->assertSame('584123258691', $card->whatsapp());
        $this->assertSame('pafar_ve', $card->instagram());
        $this->assertSame($now, $card->createdAt());
        $this->assertSame($now, $card->updatedAt());
    }

    public function testAlwaysExposesPermanentUniversoSolucionesLink(): void
    {
        $card = $this->validCard();

        $this->assertSame(
            'https://enlacesmultiples.pafar.com.ve/universodesoluciones/',
            $card->universoSolucionesUrl()
        );
    }

    public function testWhatsappAndInstagramAreOptional(): void
    {
        $card = Presencard::create(
            HashId::fromString('aaaaaaaaaaaa'),
            7,
            'Ana',
            'CTO',
            null,
            'ana@pafar.com.ve',
            '0412-1112233',
            '  ',
            null,
            new DateTimeImmutable()
        );

        $this->assertNull($card->whatsapp());
        $this->assertNull($card->instagram());
    }

    public function testOnlyTheNameIsRequired(): void
    {
        $card = Presencard::create(
            HashId::fromString('aaaaaaaaaaaa'),
            7,
            'Ana Pérez',
            null,
            null,
            null,
            null,
            null,
            null,
            new DateTimeImmutable()
        );

        $this->assertSame('Ana Pérez', $card->fullName());
        $this->assertNull($card->jobTitle());
        $this->assertNull($card->company());
        $this->assertNull($card->email());
        $this->assertNull($card->phone());
    }

    public function testBlankOptionalFieldsAreStoredAsNull(): void
    {
        $card = $this->validCard(['jobTitle' => '   ', 'company' => ' ', 'email' => '', 'phone' => "  \t "]);

        $this->assertNull($card->jobTitle());
        $this->assertNull($card->company());
        $this->assertNull($card->email());
        $this->assertNull($card->phone());
    }

    public function testOptionalFieldsAreTrimmedAndStillValidatedWhenProvided(): void
    {
        $card = $this->validCard(['jobTitle' => '  Gerente ', 'email' => ' ana@pafar.net ', 'phone' => ' 0412-1 ']);

        $this->assertSame('Gerente', $card->jobTitle());
        $this->assertSame('ana@pafar.net', $card->email());
        $this->assertSame('0412-1', $card->phone());
    }

    private function cardWith(?string $telegram, ?string $primary = null, ?string $accent = null): Presencard
    {
        return Presencard::create(
            HashId::fromString('aaaaaaaaaaaa'),
            7,
            'Ana Pérez',
            null,
            null,
            null,
            null,
            null,
            null,
            new DateTimeImmutable(),
            $telegram,
            $primary,
            $accent
        );
    }

    public function testTelegramAcceptsHandleOrLinkAndStoresOnlyTheHandle(): void
    {
        $this->assertSame('pafar_ve', $this->cardWith('@pafar_ve')->telegram());
        $this->assertSame('pafar_ve', $this->cardWith('pafar_ve')->telegram());
        $this->assertSame('pafar_ve', $this->cardWith('https://t.me/pafar_ve')->telegram());
        $this->assertSame('pafar_ve', $this->cardWith('telegram.me/pafar_ve')->telegram());
        $this->assertNull($this->cardWith('  ')->telegram());
    }

    /** @dataProvider invalidTelegramProvider */
    public function testTelegramRejectsInvalidHandles(string $handle): void
    {
        $this->expectException(InvalidPresencard::class);

        $this->cardWith($handle);
    }

    /** @return array<string, array{string}> */
    public function invalidTelegramProvider(): array
    {
        return ['muy corto' => ['abc'], 'caracteres no válidos' => ['mi.usuario'], 'muy largo' => [str_repeat('a', 33)]];
    }

    public function testColorsAreNormalizedToUppercaseAndAreOptional(): void
    {
        $card = $this->cardWith(null, '#3240a5', ' #008d36 ');

        $this->assertSame('#3240A5', $card->colorPrimary());
        $this->assertSame('#008D36', $card->colorAccent());

        $default = $this->cardWith(null, '', null);
        $this->assertNull($default->colorPrimary());
        $this->assertNull($default->colorAccent());
    }

    /** @dataProvider invalidColorProvider */
    public function testColorsRejectInvalidValues(string $color): void
    {
        $this->expectException(InvalidPresencard::class);

        $this->cardWith(null, $color);
    }

    /** @return array<string, array{string}> */
    public function invalidColorProvider(): array
    {
        return ['sin almohadilla' => ['3240A5'], 'abreviado' => ['#fff'], 'nombre' => ['red'], 'inyección' => ['#3240A5;x{}']];
    }

    public function testUpdateChangesTelegramAndColorsAndCanClearThem(): void
    {
        $card = $this->cardWith('pafar_ve', '#112233', '#445566');

        $card->update('Ana Pérez', null, null, null, null, null, null, new DateTimeImmutable());

        $this->assertNull($card->telegram());
        $this->assertNull($card->colorPrimary());
        $this->assertNull($card->colorAccent());
    }

    public function testUpdateCanClearOptionalFields(): void
    {
        $card = $this->validCard();

        $card->update('Jaime Padrón', null, null, null, null, null, null, new DateTimeImmutable());

        $this->assertNull($card->jobTitle());
        $this->assertNull($card->email());
        $this->assertNull($card->phone());
        $this->assertSame('Jaime Padrón', $card->fullName());
    }

    public function testInstagramAcceptsProfileUrl(): void
    {
        $card = $this->validCard(['instagram' => 'https://www.instagram.com/pafar.ve/?hl=es']);

        $this->assertSame('pafar.ve', $card->instagram());
    }

    /**
     * @dataProvider invalidInputs
     */
    public function testRejectsInvalidData(array $override, string $messagePart): void
    {
        $this->expectException(InvalidPresencard::class);
        $this->expectExceptionMessageMatches('/' . preg_quote($messagePart, '/') . '/');

        $this->validCard($override);
    }

    public function invalidInputs(): array
    {
        return [
            'nombre vacío' => [['fullName' => '   '], 'nombre completo'],
            'nombre muy largo' => [['fullName' => str_repeat('a', 151)], 'nombre completo'],
            'cargo muy largo' => [['jobTitle' => str_repeat('a', 151)], 'cargo'],
            'empresa muy larga' => [['company' => str_repeat('a', 151)], 'empresa'],
            'correo inválido' => [['email' => 'no-es-correo'], 'correo'],
            'correo muy largo' => [['email' => str_repeat('a', 175) . '@b.co'], 'correo'],
            'teléfono muy largo' => [['phone' => str_repeat('1', 31)], 'teléfono'],
            'whatsapp corto' => [['whatsapp' => '123'], 'WhatsApp'],
            'whatsapp largo' => [['whatsapp' => '1234567890123456'], 'WhatsApp'],
            'instagram inválido' => [['instagram' => 'no valido!'], 'Instagram'],
        ];
    }

    public function testUpdateChangesContentButKeepsHashIdAndCreationDate(): void
    {
        $card = $this->validCard();
        $createdAt = $card->createdAt();
        $later = new DateTimeImmutable('2026-11-01 08:00:00');

        $card->update('Nuevo Nombre', 'Gerente', 'Otra Empresa', 'nuevo@pafar.com.ve', '0414-0000000', null, null, $later);

        $this->assertSame('Nuevo Nombre', $card->fullName());
        $this->assertSame('Gerente', $card->jobTitle());
        $this->assertSame('Otra Empresa', $card->company());
        $this->assertNull($card->whatsapp());
        $this->assertSame('aaaaaaaaaaaa', $card->hashId()->value());
        $this->assertSame($createdAt, $card->createdAt());
        $this->assertSame($later, $card->updatedAt());
    }

    public function testUpdateRejectsInvalidDataWithoutTouchingTimestamp(): void
    {
        $card = $this->validCard();
        $updatedAt = $card->updatedAt();

        try {
            $card->update('', 'Gerente', null, 'x@y.com', '1', null, null, new DateTimeImmutable('2030-01-01'));
            $this->fail('Se esperaba InvalidPresencard');
        } catch (InvalidPresencard $e) {
            $this->assertSame($updatedAt, $card->updatedAt());
        }
    }

    private function validCard(array $override = []): Presencard
    {
        $data = array_merge([
            'fullName' => 'Jaime Padrón',
            'jobTitle' => 'CEO',
            'company' => 'Consultores JPL',
            'email' => 'jaileop@yahoo.com',
            'phone' => '+58 412-3258691',
            'whatsapp' => '584123258691',
            'instagram' => 'pafar_ve',
        ], $override);

        return Presencard::create(
            HashId::fromString('aaaaaaaaaaaa'),
            7,
            $data['fullName'],
            $data['jobTitle'],
            $data['company'],
            $data['email'],
            $data['phone'],
            $data['whatsapp'],
            $data['instagram'],
            new DateTimeImmutable('2026-10-21 10:00:00')
        );
    }
}
