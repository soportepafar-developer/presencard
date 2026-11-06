<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Security;

use App\User\Domain\User;
use App\User\Domain\UserRole;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Adaptador: representa a un User del dominio ante Symfony Security.
 *
 * Implementa EquatableInterface para que la sesión se invalide sola cuando cambian la
 * contraseña, el rol o el estado de la cuenta (p. ej. un administrador desactiva al usuario).
 * El correo no cuenta: cambiarlo en el perfil no debe cerrar la sesión.
 */
final class SecurityUser implements UserInterface, EquatableInterface
{
    private int $id;
    private string $email;
    private string $passwordHash;
    private string $role;
    private bool $active;

    public function __construct(int $id, string $email, string $passwordHash, string $role, bool $active)
    {
        $this->id = $id;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->role = $role;
        $this->active = $active;
    }

    public static function fromUser(User $user): self
    {
        return new self((int) $user->id(), $user->email(), $user->passwordHash(), $user->role(), $user->isActive());
    }

    public function id(): int
    {
        return $this->id;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getRoles(): array
    {
        return $this->isAdmin() ? [UserRole::ADMIN, UserRole::USER] : [UserRole::USER];
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function getUsername(): string
    {
        return $this->email;
    }

    public function eraseCredentials(): void
    {
    }

    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self
            && $user->id === $this->id
            && $user->passwordHash === $this->passwordHash
            && $user->role === $this->role
            && $user->active === $this->active;
    }
}
