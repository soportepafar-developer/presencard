<?php

declare(strict_types=1);

namespace App\Presencard\Application\Query;

use App\Presencard\Application\PresencardView;
use App\Presencard\Domain\Exception\InvalidPresencard;
use App\Presencard\Domain\Exception\PresencardNotFound;
use App\Presencard\Domain\HashId;
use App\Presencard\Domain\PresencardRepository;

final class GetPublicPresencardHandler
{
    private PresencardRepository $repository;

    public function __construct(PresencardRepository $repository)
    {
        $this->repository = $repository;
    }

    public function handle(string $hashId): PresencardView
    {
        try {
            $id = HashId::fromString($hashId);
        } catch (InvalidPresencard $e) {
            // Un hash con formato inválido, para el visitante, simplemente no existe.
            throw PresencardNotFound::withHashId($hashId);
        }

        $presencard = $this->repository->byHashId($id);

        if ($presencard === null) {
            throw PresencardNotFound::withHashId($hashId);
        }

        return PresencardView::fromPresencard($presencard);
    }
}
