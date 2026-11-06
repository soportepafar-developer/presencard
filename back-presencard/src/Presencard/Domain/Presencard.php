<?php

declare(strict_types=1);

namespace App\Presencard\Domain;

use App\Presencard\Domain\Exception\InvalidPresencard;
use DateTimeImmutable;

class Presencard
{
    /** Enlace permanente presente en todas las tarjetas. */
    public const UNIVERSO_SOLUCIONES_URL = 'https://enlacesmultiples.pafar.com.ve/universodesoluciones/';

    private const MAX_NAME_LENGTH = 150;
    private const MAX_EMAIL_LENGTH = 180;
    private const MAX_PHONE_LENGTH = 30;

    private ?int $id = null;
    private string $hashId;
    private int $ownerId;
    private string $fullName;
    private ?string $jobTitle;
    private ?string $company;
    private ?string $email;
    private ?string $phone;
    private ?string $whatsapp;
    private ?string $instagram;
    private ?string $telegram = null;
    private ?string $colorPrimary = null;
    private ?string $colorAccent = null;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }

    public static function create(
        HashId $hashId,
        int $ownerId,
        string $fullName,
        ?string $jobTitle,
        ?string $company,
        ?string $email,
        ?string $phone,
        ?string $whatsapp,
        ?string $instagram,
        DateTimeImmutable $now,
        ?string $telegram = null,
        ?string $colorPrimary = null,
        ?string $colorAccent = null
    ): self {
        $card = new self();
        $card->hashId = $hashId->value();
        $card->ownerId = $ownerId;
        $card->applyContent($fullName, $jobTitle, $company, $email, $phone, $whatsapp, $instagram, $telegram, $colorPrimary, $colorAccent);
        $card->createdAt = $now;
        $card->updatedAt = $now;

        return $card;
    }

    public function update(
        string $fullName,
        ?string $jobTitle,
        ?string $company,
        ?string $email,
        ?string $phone,
        ?string $whatsapp,
        ?string $instagram,
        DateTimeImmutable $now,
        ?string $telegram = null,
        ?string $colorPrimary = null,
        ?string $colorAccent = null
    ): void {
        $this->applyContent($fullName, $jobTitle, $company, $email, $phone, $whatsapp, $instagram, $telegram, $colorPrimary, $colorAccent);
        $this->updatedAt = $now;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function hashId(): HashId
    {
        return HashId::fromString($this->hashId);
    }

    /** Id del usuario responsable de la tarjeta (quien la creó, o a quien se transfirió). */
    public function ownerId(): int
    {
        return $this->ownerId;
    }

    public function isOwnedBy(int $userId): bool
    {
        return $this->ownerId === $userId;
    }

    public function fullName(): string
    {
        return $this->fullName;
    }

    public function jobTitle(): ?string
    {
        return $this->jobTitle;
    }

    /** Empresa u organización de la persona (opcional); se exporta como ORG en el contacto .vcf. */
    public function company(): ?string
    {
        return $this->company;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function whatsapp(): ?string
    {
        return $this->whatsapp;
    }

    public function instagram(): ?string
    {
        return $this->instagram;
    }

    public function telegram(): ?string
    {
        return $this->telegram;
    }

    /** Color principal de la tarjeta (#RRGGBB); null = paleta por defecto. */
    public function colorPrimary(): ?string
    {
        return $this->colorPrimary;
    }

    /** Color de acento de la tarjeta (#RRGGBB); null = paleta por defecto. */
    public function colorAccent(): ?string
    {
        return $this->colorAccent;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function universoSolucionesUrl(): string
    {
        return self::UNIVERSO_SOLUCIONES_URL;
    }

    private function applyContent(
        string $fullName,
        ?string $jobTitle,
        ?string $company,
        ?string $email,
        ?string $phone,
        ?string $whatsapp,
        ?string $instagram,
        ?string $telegram,
        ?string $colorPrimary,
        ?string $colorAccent
    ): void {
        // Solo el nombre es obligatorio: no siempre se conoce el cargo, el correo o el teléfono de la persona.
        $this->fullName = self::requiredText($fullName, 'El nombre completo', self::MAX_NAME_LENGTH);
        $this->jobTitle = self::optionalText($jobTitle, 'El cargo', self::MAX_NAME_LENGTH);
        $this->company = self::optionalText($company, 'La empresa', self::MAX_NAME_LENGTH);
        $this->email = self::optionalEmail($email);
        $this->phone = self::optionalText($phone, 'El teléfono', self::MAX_PHONE_LENGTH);
        $this->whatsapp = self::normalizeWhatsapp($whatsapp);
        $this->instagram = self::normalizeInstagram($instagram);
        $this->telegram = self::normalizeTelegram($telegram);
        $this->colorPrimary = self::optionalColor($colorPrimary, 'El color principal');
        $this->colorAccent = self::optionalColor($colorAccent, 'El color de acento');
    }

    private static function requiredText(string $value, string $label, int $maxLength): string
    {
        $value = trim($value);

        if ($value === '') {
            throw InvalidPresencard::because(sprintf('%s es obligatorio.', $label));
        }

        if (mb_strlen($value) > $maxLength) {
            throw InvalidPresencard::because(sprintf('%s no puede superar %d caracteres.', $label, $maxLength));
        }

        return $value;
    }

    /** Vacío o solo espacios se guarda como null; si hay texto, se valida su longitud. */
    private static function optionalText(?string $value, string $label, int $maxLength): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return self::requiredText($value, $label, $maxLength);
    }

    /** Opcional, pero si se escribe debe ser un correo válido. */
    private static function optionalEmail(?string $email): ?string
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        $email = trim($email);

        if (mb_strlen($email) > self::MAX_EMAIL_LENGTH || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw InvalidPresencard::because('El correo electrónico no es válido.');
        }

        return $email;
    }

    /** Guarda solo dígitos con código de país (ej. 584123258691), listo para wa.me. */
    private static function normalizeWhatsapp(?string $whatsapp): ?string
    {
        if ($whatsapp === null || trim($whatsapp) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $whatsapp);

        if (strlen($digits) < 8 || strlen($digits) > 15) {
            throw InvalidPresencard::because('El WhatsApp debe tener entre 8 y 15 dígitos, incluyendo el código de país.');
        }

        return $digits;
    }

    /** Acepta "@usuario", "usuario" o la URL del perfil; guarda solo el usuario. */
    private static function normalizeInstagram(?string $instagram): ?string
    {
        if ($instagram === null || trim($instagram) === '') {
            return null;
        }

        $handle = trim($instagram);

        if (preg_match('#instagram\.com/([A-Za-z0-9._]+)#i', $handle, $matches)) {
            $handle = $matches[1];
        }

        $handle = ltrim($handle, '@');

        if (!preg_match('/^[A-Za-z0-9._]{1,30}$/', $handle)) {
            throw InvalidPresencard::because('El usuario de Instagram no es válido.');
        }

        return $handle;
    }

    /** Acepta "@usuario", "usuario" o el enlace t.me/usuario; guarda solo el usuario (5-32 caracteres). */
    private static function normalizeTelegram(?string $telegram): ?string
    {
        if ($telegram === null || trim($telegram) === '') {
            return null;
        }

        $handle = trim($telegram);

        if (preg_match('#(?:t\.me|telegram\.me)/([A-Za-z0-9_]+)#i', $handle, $matches)) {
            $handle = $matches[1];
        }

        $handle = ltrim($handle, '@');

        if (!preg_match('/^[A-Za-z0-9_]{5,32}$/', $handle)) {
            throw InvalidPresencard::because('El usuario de Telegram no es válido (5 a 32 letras, números o guion bajo).');
        }

        return $handle;
    }

    /** Color opcional en formato #RRGGBB; se guarda en mayúsculas. Vacío = paleta por defecto. */
    private static function optionalColor(?string $color, string $label): ?string
    {
        if ($color === null || trim($color) === '') {
            return null;
        }

        $color = trim($color);

        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            throw InvalidPresencard::because(sprintf('%s debe tener el formato #RRGGBB.', $label));
        }

        return strtoupper($color);
    }
}
