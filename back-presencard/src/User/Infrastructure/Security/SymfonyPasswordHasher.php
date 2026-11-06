<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Security;

use App\User\Application\Port\PasswordHasher;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

/** Adaptador del puerto PasswordHasher sobre los encoders configurados en security.yaml. */
final class SymfonyPasswordHasher implements PasswordHasher
{
    private EncoderFactoryInterface $encoders;

    public function __construct(EncoderFactoryInterface $encoders)
    {
        $this->encoders = $encoders;
    }

    public function hash(string $plainPassword): string
    {
        return $this->encoders->getEncoder(SecurityUser::class)->encodePassword($plainPassword, null);
    }

    public function verify(string $passwordHash, string $plainPassword): bool
    {
        return $this->encoders->getEncoder(SecurityUser::class)->isPasswordValid($passwordHash, $plainPassword, null);
    }
}
