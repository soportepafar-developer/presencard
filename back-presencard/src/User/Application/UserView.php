<?php

declare(strict_types=1);

namespace App\User\Application;

use App\User\Domain\User;
use DateTimeInterface;

/** DTO de salida: nunca incluye el hash de la contraseña. */
final class UserView
{
    public ?int $id;
    public string $email;
    public string $fullName;
    public string $role;
    public bool $active;
    public string $createdAt;
    public string $updatedAt;

    private function __construct()
    {
    }

    public static function fromUser(User $user): self
    {
        $view = new self();
        $view->id = $user->id();
        $view->email = $user->email();
        $view->fullName = $user->fullName();
        $view->role = $user->role();
        $view->active = $user->isActive();
        $view->createdAt = $user->createdAt()->format(DateTimeInterface::ATOM);
        $view->updatedAt = $user->updatedAt()->format(DateTimeInterface::ATOM);

        return $view;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
