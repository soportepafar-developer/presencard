<?php

declare(strict_types=1);

namespace App\Presencard\Application\Create;

final class CreatePresencardCommand
{
    /** Usuario que crea la tarjeta y queda como su responsable. */
    public int $ownerId;
    public string $fullName;
    public ?string $jobTitle;
    public ?string $company;
    public ?string $email;
    public ?string $phone;
    public ?string $whatsapp;
    public ?string $instagram;
    public ?string $telegram;
    public ?string $colorPrimary;
    public ?string $colorAccent;

    public function __construct(
        int $ownerId,
        string $fullName,
        ?string $jobTitle,
        ?string $company,
        ?string $email,
        ?string $phone,
        ?string $whatsapp = null,
        ?string $instagram = null,
        ?string $telegram = null,
        ?string $colorPrimary = null,
        ?string $colorAccent = null
    ) {
        $this->ownerId = $ownerId;
        $this->fullName = $fullName;
        $this->jobTitle = $jobTitle;
        $this->company = $company;
        $this->email = $email;
        $this->phone = $phone;
        $this->whatsapp = $whatsapp;
        $this->instagram = $instagram;
        $this->telegram = $telegram;
        $this->colorPrimary = $colorPrimary;
        $this->colorAccent = $colorAccent;
    }
}
