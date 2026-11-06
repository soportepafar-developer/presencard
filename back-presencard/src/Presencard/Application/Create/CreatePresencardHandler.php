<?php

declare(strict_types=1);

namespace App\Presencard\Application\Create;

use App\Presencard\Application\Port\HashGenerator;
use App\Presencard\Application\PresencardView;
use App\Presencard\Domain\HashId;
use App\Presencard\Domain\Presencard;
use App\Presencard\Domain\PresencardRepository;
use DateTimeImmutable;
use RuntimeException;

final class CreatePresencardHandler
{
    private const MAX_HASH_ATTEMPTS = 5;

    private PresencardRepository $repository;
    private HashGenerator $hashGenerator;

    public function __construct(PresencardRepository $repository, HashGenerator $hashGenerator)
    {
        $this->repository = $repository;
        $this->hashGenerator = $hashGenerator;
    }

    public function handle(CreatePresencardCommand $command): PresencardView
    {
        $presencard = Presencard::create(
            $this->generateUniqueHashId(),
            $command->ownerId,
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

    private function generateUniqueHashId(): HashId
    {
        for ($attempt = 0; $attempt < self::MAX_HASH_ATTEMPTS; ++$attempt) {
            $hashId = $this->hashGenerator->generate();

            if (!$this->repository->hashIdExists($hashId)) {
                return $hashId;
            }
        }

        throw new RuntimeException('No fue posible generar un identificador único para la tarjeta.');
    }
}
