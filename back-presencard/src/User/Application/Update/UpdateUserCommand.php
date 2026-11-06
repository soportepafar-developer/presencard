<?php

declare(strict_types=1);

namespace App\User\Application\Update;

final class UpdateUserCommand
{
    /** Quien realiza el cambio (para impedir que se modifique su propio rol). */
    public int $actorId;
    public int $id;
    public string $fullName;
    public string $email;
    public string $role;

    public function __construct(int $actorId, int $id, string $fullName, string $email, string $role)
    {
        $this->actorId = $actorId;
        $this->id = $id;
        $this->fullName = $fullName;
        $this->email = $email;
        $this->role = $role;
    }
}
