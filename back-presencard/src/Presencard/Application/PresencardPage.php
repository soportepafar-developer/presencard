<?php

declare(strict_types=1);

namespace App\Presencard\Application;

final class PresencardPage
{
    /** @var PresencardView[] */
    public array $items;
    public int $total;
    public int $page;
    public int $perPage;

    /**
     * @param PresencardView[] $items
     */
    public function __construct(array $items, int $total, int $page, int $perPage)
    {
        $this->items = $items;
        $this->total = $total;
        $this->page = $page;
        $this->perPage = $perPage;
    }

    public function totalPages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'items' => array_map(static function (PresencardView $view): array {
                return $view->toArray();
            }, $this->items),
            'total' => $this->total,
            'page' => $this->page,
            'perPage' => $this->perPage,
            'totalPages' => $this->totalPages(),
        ];
    }
}
