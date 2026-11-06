<?php

declare(strict_types=1);

namespace App\Presencard\Application\Port;

use App\Presencard\Domain\HashId;

/**
 * Puerto de salida: generación de identificadores públicos aleatorios.
 */
interface HashGenerator
{
    public function generate(): HashId;
}
