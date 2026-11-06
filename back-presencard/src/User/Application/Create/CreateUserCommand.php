<?php

declare(strict_types=1);

namespace App\User\Application\Create;

final class CreateUserCommand
{
    public string $email;
    public string $fullName;
    public string $password;
    public string $role;

    public function __construct(string $email, string $fullName, string $password, string $role)
    {
        $this->email = $email;
        $this->fullName = $fullName;
        $this->password = $password;
        $this->role = $role;
    }
}
