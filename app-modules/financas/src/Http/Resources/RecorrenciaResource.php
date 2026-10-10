<?php

declare(strict_types=1);

namespace Modules\Financas\Http\Resources;

use Modules\Financas\Models\Recorrencia;

final class RecorrenciaResource
{
    /** @return array<string, mixed> */
    public static function make(Recorrencia $r): array
    {
        return [
            'id' => $r->rec_id,
            'nome' => $r->rec_nome,
            'direcao' => $r->rec_direcao,
            'valor_min' => (float) $r->rec_valor_min,
            'valor_max' => (float) $r->rec_valor_max,
            'meio' => $r->rec_meio,
            'dia' => $r->rec_dia,
            'ativa' => $r->rec_ativa,
            'created_at' => $r->rec_created_at?->toIso8601String(),
            'updated_at' => $r->rec_updated_at?->toIso8601String(),
        ];
    }
}
