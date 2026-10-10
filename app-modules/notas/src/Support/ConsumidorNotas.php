<?php

declare(strict_types=1);

namespace Modules\Notas\Support;

use Modules\Core\Support\ConsumidorPorModelo;
use Modules\Notas\Models\Nota;

final class ConsumidorNotas extends ConsumidorPorModelo
{
    protected function mapa(): array
    {
        return ['nota_tipo' => [Nota::class, 'not_tipo', true]];
    }
}
