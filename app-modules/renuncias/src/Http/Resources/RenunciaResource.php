<?php

declare(strict_types=1);

namespace Modules\Renuncias\Http\Resources;

use Modules\Renuncias\Models\Renuncia;
use Modules\Renuncias\Models\Tentativa;

final class RenunciaResource
{
    /** @return array<string, mixed> */
    public static function make(Renuncia $r): array
    {
        return [
            'id' => $r->ren_id,
            'nome' => $r->ren_nome,
            'emoji' => $r->ren_emoji,
            'desde' => $r->ren_desde->toIso8601String(),
            'arquivada' => $r->ren_arquivada,
            'created_at' => $r->ren_created_at?->toIso8601String(),
            'updated_at' => $r->ren_updated_at?->toIso8601String(),
            'tentativas' => $r->tentativas->map(fn (Tentativa $t): array => [
                'id' => $t->rtn_id,
                'inicio' => $t->rtn_inicio->toIso8601String(),
                'fim' => $t->rtn_fim->toIso8601String(),
            ])->all(),
        ];
    }
}
