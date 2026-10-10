<?php

declare(strict_types=1);

namespace Modules\Visoes\Http\Resources;

use Modules\Visoes\Models\Visao;

final class VisaoResource
{
    /** @return array<string, mixed> */
    public static function make(Visao $v): array
    {
        return [
            'id' => $v->vis_id,
            'tabela' => $v->vis_tabela,
            'nome' => $v->vis_nome,
            'modo' => $v->vis_modo,
            'regras' => $v->vis_regras,
            'ordem' => $v->vis_ordem,
            'padrao' => $v->vis_padrao,
            'created_at' => $v->vis_created_at?->toIso8601String(),
            'updated_at' => $v->vis_updated_at?->toIso8601String(),
        ];
    }
}
