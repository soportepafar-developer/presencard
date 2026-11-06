<?php

declare(strict_types=1);

namespace App\Presencard\Application\Delete;

use App\Presencard\Application\Actor;
use App\Presencard\Domain\Exception\PresencardNotFound;
use App\Presencard\Domain\PresencardRepository;

final class DeletePresencardHandler
{
    private PresencardRepository $repository;

    public function __construct(PresencardRepository $repository)
    {
        $this->repository = $repository;
    }

    public function handle(Actor $actor, int $id): void
    {
        $presencard = $this->repository->byId($id);

        // Una tarjeta ajena se trata como inexistente: no se revela que existe.
        if ($presencard === null || !$actor->canManage($presencard)) {
            throw PresencardNotFound::withId($id);
        }

        $this->repository->remove($presencard);
    }
}
