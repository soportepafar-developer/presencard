<?php

declare(strict_types=1);

namespace App\Presencard\Application\Query;

use App\Presencard\Application\Actor;
use App\Presencard\Application\Port\OwnerDirectory;
use App\Presencard\Application\PresencardPage;
use App\Presencard\Application\PresencardView;
use App\Presencard\Domain\Presencard;
use App\Presencard\Domain\PresencardRepository;

final class ListPresencardsHandler
{
    public const DEFAULT_PER_PAGE = 10;
    public const MAX_PER_PAGE = 50;

    private PresencardRepository $repository;
    private OwnerDirectory $owners;

    public function __construct(PresencardRepository $repository, OwnerDirectory $owners)
    {
        $this->repository = $repository;
        $this->owners = $owners;
    }

    public function handle(Actor $actor, ?string $search, int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): PresencardPage
    {
        $search = $search !== null ? trim($search) : null;
        $search = $search === '' ? null : $search;
        $page = max(1, $page);
        $perPage = min(self::MAX_PER_PAGE, max(1, $perPage));
        $ownerId = $actor->ownerFilter();

        $cards = $this->repository->search($search, $page, $perPage, $ownerId);
        $names = $this->owners->namesByIds(array_values(array_unique(array_map(static function (Presencard $card): int {
            return $card->ownerId();
        }, $cards))));

        $items = array_map(static function (Presencard $card) use ($names): PresencardView {
            return PresencardView::fromPresencard($card, $names[$card->ownerId()] ?? null);
        }, $cards);

        return new PresencardPage($items, $this->repository->count($search, $ownerId), $page, $perPage);
    }
}
