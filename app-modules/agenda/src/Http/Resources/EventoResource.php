<?php

declare(strict_types=1);

namespace Modules\Agenda\Http\Resources;

use Modules\Agenda\Models\Evento;

final class EventoResource
{
    /** @return array<string, mixed> */
    public static function make(Evento $e): array
    {
        return [
            'id' => $e->evt_id,
            'name' => $e->evt_name,
            'date' => $e->evt_date->format('Y-m-d'),
            'date_fim' => $e->evt_date_fim?->format('Y-m-d'),
            'tipo' => $e->evt_tipo,
            'projeto_id' => $e->projeto_prj_id,
        ];
    }
}
