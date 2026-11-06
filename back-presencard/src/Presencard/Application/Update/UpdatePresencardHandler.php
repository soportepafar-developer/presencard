<?php

declare(strict_types=1);

namespace App\Presencard\Application\Update;

use App\Presencard\Application\PresencardView;
use App\Presencard\Domain\Exception\PresencardNotFound;
use App\Presencard\Domain\PresencardRepository;
use DateTimeImmutable;

final class UpdatePresencardHandler
{
    private PresencardRepository $repository;

    public function __construct(PresencardRepository $repository)
    {
        $this->repository = $repository;
    }

    public function handle(UpdatePresencardCommand $command): PresencardView
    {
        $presencard = $this->repository->byId($command->id);

        // Una tarjeta ajena se trata como inexistente: no se revela que existe.
        if ($presencard === null || !$command->actor->canManage($presencard)) {
            throw PresencardNotFound::withId($command->id);
        }

        // El hash_id nunca cambia: la URL ya compartida debe seguir funcionando.
        $presencard->update(
            $command->fullName,
            $command->jobTitle,
            $command->company,
            $command->email,
            $command->phone,
            $command->whatsapp,
            $command->instagram,
            new DateTimeImmutable(),
            $command->telegram,
            $command->colorPrimary,
            $command->colorAccent
        );

        $this->repository->save($presencard);

        return PresencardView::fromPresencard($presencard);
    }
}
