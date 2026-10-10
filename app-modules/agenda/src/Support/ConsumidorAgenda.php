<?php

declare(strict_types=1);

namespace Modules\Agenda\Support;

use Modules\Agenda\Models\Evento;
use Modules\Core\Support\ConsumidorPorModelo;

final class ConsumidorAgenda extends ConsumidorPorModelo
{
    protected function mapa(): array
    {
        return ['evento_tipo' => [Evento::class, 'evt_tipo', false]];
    }
}
