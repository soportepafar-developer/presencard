<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Security;

use App\User\Domain\UserRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class SecurityUserProvider implements UserProviderInterface
{
    private UserRepository $users;

    public function __construct(UserRepository $users)
    {
        $this->users = $users;
    }

    public function loadUserByUsername(string $username): UserInterface
    {
        $user = $this->users->byEmail($username);

        if ($user === null) {
            $exception = new UsernameNotFoundException();
            $exception->setUsername($username);

            throw $exception;
        }

        return SecurityUser::fromUser($user);
    }

    /** Se recarga por id (no por correo) para que un cambio de correo no rompa la sesión. */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(sprintf('Usuario no soportado: %s.', get_class($user)));
        }

        $fresh = $this->users->byId($user->id());

        if ($fresh === null) {
            $exception = new UsernameNotFoundException();
            $exception->setUsername($user->getUsername());

            throw $exception;
        }

        return SecurityUser::fromUser($fresh);
    }

    public function supportsClass(string $class): bool
    {
        return $class === SecurityUser::class;
    }
}
