<?php

declare(strict_types=1);

namespace App\Presencard\Application\Query;

use App\Presencard\Application\Actor;
use App\Presencard\Application\Port\OwnerDirectory;
use App\Presencard\Application\PresencardView;
use App\Presencard\Domain\Exception\PresencardNotFound;
use App\Presencard\Domain\PresencardRepository;

final class GetPresencardHandler
{
    private PresencardRepository $repository;
    private OwnerDirectory $owners;

    public function __construct(PresencardRepository $repository, OwnerDirectory $owners)
    {
        $this->repository = $repository;
        $this->owners = $owners;
    }

    public function handle(Actor $actor, int $id): PresencardView
    {
        $presencard = $this->repository->byId($id);

        // Una tarjeta ajena se trata como inexistente: no se revela que existe.
        if ($presencard === null || !$actor->canManage($presencard)) {
            throw PresencardNotFound::withId($id);
        }

        $names = $this->owners->namesByIds([$presencard->ownerId()]);

        return PresencardView::fromPresencard($presencard, $names[$presencard->ownerId()] ?? null);
    }
}
