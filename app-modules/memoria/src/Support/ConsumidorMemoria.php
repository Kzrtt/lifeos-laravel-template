<?php

declare(strict_types=1);

namespace Modules\Memoria\Support;

use Modules\Core\Support\ConsumidorPorModelo;
use Modules\Memoria\Models\Memoria;

final class ConsumidorMemoria extends ConsumidorPorModelo
{
    protected function mapa(): array
    {
        return ['memoria_categoria' => [Memoria::class, 'mem_categoria', false]];
    }
}
