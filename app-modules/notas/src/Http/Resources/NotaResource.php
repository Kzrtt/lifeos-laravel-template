<?php

declare(strict_types=1);

namespace Modules\Notas\Http\Resources;

use Modules\Notas\Models\Nota;

final class NotaResource
{
    /** @return array<string, mixed> */
    public static function make(Nota $n): array
    {
        return [
            'id' => $n->not_id,
            'name' => $n->not_name,
            'tipo' => $n->not_tipo,
            'data' => $n->not_data?->format('Y-m-d'),
            'conteudo_md' => $n->not_conteudo_md,
            'projeto_ids' => $n->vinculos->pluck('projeto_prj_id')->values()->all(),
            'created_at' => $n->not_created_at?->toIso8601String(),
            'updated_at' => $n->not_updated_at?->toIso8601String(),
        ];
    }
}
