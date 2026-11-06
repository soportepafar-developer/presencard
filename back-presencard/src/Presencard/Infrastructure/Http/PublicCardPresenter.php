<?php

declare(strict_types=1);

namespace App\Presencard\Infrastructure\Http;

use App\Presencard\Application\PresencardView;

/** Datos que puede ver cualquier visitante: sin id interno ni fechas de auditoría. */
final class PublicCardPresenter
{
    private PublicUrlBuilder $urls;

    public function __construct(PublicUrlBuilder $urls)
    {
        $this->urls = $urls;
    }

    /** @return array<string, mixed> */
    public function present(PresencardView $view): array
    {
        return [
            'hashId' => $view->hashId,
            'fullName' => $view->fullName,
            'jobTitle' => $view->jobTitle,
            'company' => $view->company,
            'email' => $view->email,
            'phone' => $view->phone,
            'whatsapp' => $view->whatsapp,
            'instagram' => $view->instagram,
            'telegram' => $view->telegram,
            'colorPrimary' => $view->colorPrimary,
            'colorAccent' => $view->colorAccent,
            'universoSolucionesUrl' => $view->universoSolucionesUrl,
            'publicUrl' => $this->urls->forHashId($view->hashId),
        ];
    }
}
