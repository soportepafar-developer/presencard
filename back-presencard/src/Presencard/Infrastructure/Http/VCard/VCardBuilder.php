<?php

declare(strict_types=1);

namespace App\Presencard\Infrastructure\Http\VCard;

/**
 * Construye el contacto .vcf (vCard 3.0, RFC 2426) de una tarjeta pública para guardarlo en la agenda del móvil.
 * Los campos vacíos se omiten; los textos se escapan y las líneas largas se pliegan a 75 octetos sin partir
 * caracteres multibyte.
 */
final class VCardBuilder
{
    private const MAX_LINE_OCTETS = 75;
    private const EOL = "\r\n";

    private const ACCENTS = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c',
    ];

    /**
     * @param array<string, mixed> $card Datos públicos de la tarjeta (ver PublicCardPresenter)
     */
    public function build(array $card): string
    {
        $fullName = $this->text($card, 'fullName');
        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            // vCard 3.0 exige N: sin separar apellidos con fiabilidad, el nombre completo va en "nombre".
            'N:;' . $this->escape($fullName) . ';;;',
            'FN:' . $this->escape($fullName),
        ];

        $this->addText($lines, 'ORG', $this->text($card, 'company'));
        $this->addText($lines, 'TITLE', $this->text($card, 'jobTitle'));

        $phone = $this->phoneNumber($this->text($card, 'phone'));
        $whatsapp = $this->phoneNumber('+' . $this->text($card, 'whatsapp'));

        if ($phone !== '') {
            $lines[] = 'TEL;TYPE=CELL:' . $phone;
        }

        // El WhatsApp solo se añade si es otro número distinto del teléfono.
        if ($whatsapp !== '' && ltrim($whatsapp, '+') !== ltrim($phone, '+')) {
            $lines[] = 'TEL;TYPE=CELL:' . $whatsapp;
        }

        $email = $this->text($card, 'email');

        if ($email !== '') {
            $lines[] = 'EMAIL;TYPE=INTERNET:' . $this->escape($email);
        }

        $url = $this->text($card, 'publicUrl');

        if ($url !== '') {
            $lines[] = 'URL:' . $this->singleLine($url);
        }

        $instagram = $this->text($card, 'instagram');

        if ($instagram !== '') {
            $lines[] = 'X-SOCIALPROFILE;TYPE=instagram:https://instagram.com/' . rawurlencode($instagram);
        }

        $telegram = $this->text($card, 'telegram');

        if ($telegram !== '') {
            $lines[] = 'X-SOCIALPROFILE;TYPE=telegram:https://t.me/' . rawurlencode($telegram);
        }

        $lines[] = 'END:VCARD';

        return implode(self::EOL, array_map([$this, 'fold'], $lines)) . self::EOL;
    }

    /** "María Fernanda Rojas" -> "maria-fernanda-rojas.vcf" (nombre de archivo ASCII seguro). */
    public function filename(string $fullName, string $fallback): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtr(mb_strtolower($fullName), self::ACCENTS));
        $slug = trim((string) $slug, '-');

        return ($slug !== '' ? $slug : $fallback) . '.vcf';
    }

    /** @param string[] $lines */
    private function addText(array &$lines, string $property, string $value): void
    {
        if ($value !== '') {
            $lines[] = $property . ':' . $this->escape($value);
        }
    }

    /** @param array<string, mixed> $card */
    private function text(array $card, string $key): string
    {
        $value = $card[$key] ?? null;

        return is_string($value) ? trim($value) : '';
    }

    /** Escapa \ , ; y saltos de línea, como exige el tipo "text" de vCard. */
    private function escape(string $value): string
    {
        return str_replace(['\\', "\r\n", "\n", "\r", ',', ';'], ['\\\\', '\\n', '\\n', '\\n', '\\,', '\;'], $value);
    }

    private function singleLine(string $value): string
    {
        return str_replace(["\r", "\n"], '', $value);
    }

    /** Deja solo dígitos y un "+" inicial: "+58 412-3258691" -> "+584123258691". */
    private function phoneNumber(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);

        if ($digits === '' || $digits === null) {
            return '';
        }

        return (strpos(trim($raw), '+') === 0 ? '+' : '') . $digits;
    }

    /** Plegado de líneas: máximo 75 octetos por línea física; las continuaciones empiezan con un espacio. */
    private function fold(string $line): string
    {
        if (strlen($line) <= self::MAX_LINE_OCTETS) {
            return $line;
        }

        $folded = '';
        $current = '';

        foreach (preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            if (strlen($current) + strlen($character) > self::MAX_LINE_OCTETS) {
                $folded .= $current . self::EOL;
                $current = ' ';
            }

            $current .= $character;
        }

        return $folded . $current;
    }
}
