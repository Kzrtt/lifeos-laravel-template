<?php

declare(strict_types=1);

namespace Modules\Projetos\Http\Resources;

use Modules\Projetos\Models\Tarefa;

final class TarefaResource
{
    /**
     * `updated_at` não é decorativo: a coluna "Feito" dos kanbans ordena por ele.
     *
     * @return array<string, mixed>
     */
    public static function make(Tarefa $t): array
    {
        return [
            'id' => $t->tar_id,
            'name' => $t->tar_name,
            'status' => $t->tar_status,
            'tipo' => $t->tar_tipo,
            'projeto_id' => $t->projeto_prj_id,
            'data_entrega' => $t->tar_data_entrega?->format('Y-m-d'),
            'descricao' => $t->tar_descricao,
            'updated_at' => $t->tar_updated_at?->toIso8601String(),
        ];
    }
}
