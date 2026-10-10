<?php

declare(strict_types=1);

namespace Modules\Financas\Http\Resources;

use Modules\Financas\Models\Movimentacao;

final class MovimentacaoResource
{
    /** @return array<string, mixed> */
    public static function make(Movimentacao $m): array
    {
        return [
            'id' => $m->mov_id,
            'name' => $m->mov_name,
            'valor' => (float) $m->mov_valor,
            'date' => $m->mov_date->format('Y-m-d'),
            'tipo' => $m->mov_tipo,
            'created_at' => $m->mov_created_at?->toIso8601String(),
        ];
    }
}
