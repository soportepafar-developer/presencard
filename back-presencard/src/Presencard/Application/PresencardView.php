<?php

declare(strict_types=1);

namespace App\Presencard\Application;

use App\Presencard\Domain\Presencard;
use DateTimeInterface;

/**
 * DTO de salida de los casos de uso: desacopla el dominio de las capas externas.
 */
final class PresencardView
{
    public ?int $id;
    public string $hashId;
    public int $ownerId;
    public ?string $ownerName;
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
    public string $universoSolucionesUrl;
    public string $createdAt;
    public string $updatedAt;

    private function __construct()
    {
    }

    public static function fromPresencard(Presencard $presencard, ?string $ownerName = null): self
    {
        $view = new self();
        $view->id = $presencard->id();
        $view->hashId = $presencard->hashId()->value();
        $view->ownerId = $presencard->ownerId();
        $view->ownerName = $ownerName;
        $view->fullName = $presencard->fullName();
        $view->jobTitle = $presencard->jobTitle();
        $view->company = $presencard->company();
        $view->email = $presencard->email();
        $view->phone = $presencard->phone();
        $view->whatsapp = $presencard->whatsapp();
        $view->instagram = $presencard->instagram();
        $view->telegram = $presencard->telegram();
        $view->colorPrimary = $presencard->colorPrimary();
        $view->colorAccent = $presencard->colorAccent();
        $view->universoSolucionesUrl = $presencard->universoSolucionesUrl();
        $view->createdAt = $presencard->createdAt()->format(DateTimeInterface::ATOM);
        $view->updatedAt = $presencard->updatedAt()->format(DateTimeInterface::ATOM);

        return $view;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
