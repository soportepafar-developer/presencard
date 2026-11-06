<?php

declare(strict_types=1);

namespace App\Presencard\Application;

use App\Presencard\Domain\Presencard;

/** Quién ejecuta el caso de uso: define qué tarjetas puede ver y modificar. */
final class Actor
{
    private int $userId;
    private bool $admin;

    private function __construct(int $userId, bool $admin)
    {
        $this->userId = $userId;
        $this->admin = $admin;
    }

    public static function administrator(int $userId): self
    {
        return new self($userId, true);
    }

    public static function regularUser(int $userId): self
    {
        return new self($userId, false);
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function isAdministrator(): bool
    {
        return $this->admin;
    }

    /** El administrador gestiona todas; el usuario normal solo las suyas. */
    public function canManage(Presencard $presencard): bool
    {
        return $this->admin || $presencard->isOwnedBy($this->userId);
    }

    /** Filtro de propietario para los listados (null = sin filtro). */
    public function ownerFilter(): ?int
    {
        return $this->admin ? null : $this->userId;
    }
}
