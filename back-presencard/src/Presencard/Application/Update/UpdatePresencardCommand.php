<?php

declare(strict_types=1);

namespace App\Presencard\Application\Update;

use App\Presencard\Application\Actor;

final class UpdatePresencardCommand
{
    public Actor $actor;
    public int $id;
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
        Actor $actor,
        int $id,
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
        $this->actor = $actor;
        $this->id = $id;
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
