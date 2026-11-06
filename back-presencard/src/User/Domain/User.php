<?php

declare(strict_types=1);

namespace App\User\Domain;

use App\User\Domain\Exception\InvalidUser;
use DateTimeImmutable;

class User
{
    private const MAX_NAME_LENGTH = 150;
    private const MAX_EMAIL_LENGTH = 180;

    private ?int $id = null;
    private string $email;
    private string $fullName;
    private string $passwordHash;
    private string $role;
    private bool $active;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }

    /** El hash lo calcula un adaptador: el dominio nunca maneja la contraseña en claro. */
    public static function register(
        string $email,
        string $fullName,
        string $passwordHash,
        string $role,
        DateTimeImmutable $now
    ): self {
        $user = new self();
        $user->applyProfile($fullName, $email);
        $user->setRole($role);
        $user->setPasswordHash($passwordHash);
        $user->active = true;
        $user->createdAt = $now;
        $user->updatedAt = $now;

        return $user;
    }

    public function updateProfile(string $fullName, string $email, DateTimeImmutable $now): void
    {
        $this->applyProfile($fullName, $email);
        $this->updatedAt = $now;
    }

    public function changeRole(string $role, DateTimeImmutable $now): void
    {
        $this->setRole($role);
        $this->updatedAt = $now;
    }

    public function changePasswordHash(string $passwordHash, DateTimeImmutable $now): void
    {
        $this->setPasswordHash($passwordHash);
        $this->updatedAt = $now;
    }

    public function activate(DateTimeImmutable $now): void
    {
        $this->active = true;
        $this->updatedAt = $now;
    }

    public function deactivate(DateTimeImmutable $now): void
    {
        $this->active = false;
        $this->updatedAt = $now;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function fullName(): string
    {
        return $this->fullName;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function role(): string
    {
        return $this->role;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function applyProfile(string $fullName, string $email): void
    {
        $fullName = trim($fullName);
        $email = trim($email);

        if ($fullName === '') {
            throw InvalidUser::because('El nombre es obligatorio.');
        }

        if (mb_strlen($fullName) > self::MAX_NAME_LENGTH) {
            throw InvalidUser::because(sprintf('El nombre no puede superar %d caracteres.', self::MAX_NAME_LENGTH));
        }

        if ($email === '' || mb_strlen($email) > self::MAX_EMAIL_LENGTH || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw InvalidUser::because('El correo electrónico no es válido.');
        }

        $this->fullName = $fullName;
        $this->email = $email;
    }

    private function setRole(string $role): void
    {
        UserRole::assertValid($role);
        $this->role = $role;
    }

    private function setPasswordHash(string $passwordHash): void
    {
        if ($passwordHash === '') {
            throw InvalidUser::because('El hash de la contraseña no puede estar vacío.');
        }

        $this->passwordHash = $passwordHash;
    }
}
