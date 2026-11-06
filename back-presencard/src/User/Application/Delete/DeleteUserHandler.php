<?php

declare(strict_types=1);

namespace App\User\Application\Delete;

use App\User\Application\LastAdminGuard;
use App\User\Application\Port\CardOwnershipTransfer;
use App\User\Domain\Exception\OperationNotAllowed;
use App\User\Domain\Exception\UserNotFound;
use App\User\Domain\UserRepository;

final class DeleteUserHandler
{
    private UserRepository $users;
    private LastAdminGuard $lastAdmin;
    private CardOwnershipTransfer $cards;

    public function __construct(UserRepository $users, LastAdminGuard $lastAdmin, CardOwnershipTransfer $cards)
    {
        $this->users = $users;
        $this->lastAdmin = $lastAdmin;
        $this->cards = $cards;
    }

    /** Las tarjetas del usuario eliminado pasan a $actorId (el administrador que lo elimina). */
    public function handle(int $actorId, int $id): void
    {
        $user = $this->users->byId($id);

        if ($user === null) {
            throw UserNotFound::withId($id);
        }

        if ($actorId === $id) {
            throw OperationNotAllowed::ownAccount('eliminar');
        }

        $this->lastAdmin->assertNotTheLastAdmin($user);
        $this->cards->transfer($id, $actorId);
        $this->users->remove($user);
    }
}
