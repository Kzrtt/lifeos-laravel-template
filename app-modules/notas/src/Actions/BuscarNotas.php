<?php

declare(strict_types=1);

namespace Modules\Notas\Actions;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Actions\RpcAction;
use Modules\Core\Support\Mcp\Args;
use Modules\Notas\Models\Nota;

/**
 * Consulta filtrada de notas (o que `search_notas` do MCP usa): nome, tipos
 * (qualquer um), intervalo de data e projetos vinculados (qualquer um). Devolve o
 * total que casou e uma página limitada.
 */
final class BuscarNotas extends RpcAction
{
    protected function rules(): array
    {
        return [];
    }

    /** @return array{total: int, notas: Collection<int, Nota>} */
    protected function handle(array $data): mixed
    {
        $query = Nota::query()
            ->when(($data['nome'] ?? '') !== '', fn ($q) => $q->where('not_name', 'ilike', Args::contem($data['nome'])))
            ->when(($data['tipos'] ?? []) !== [], fn ($q) => $q->whereArrayOverlaps('not_tipo', $data['tipos']))
            ->when(($data['data_inicio'] ?? '') !== '', fn ($q) => $q->where('not_data', '>=', $data['data_inicio']))
            ->when(($data['data_fim'] ?? '') !== '', fn ($q) => $q->where('not_data', '<=', $data['data_fim']))
            ->when(($data['projeto_ids'] ?? null) !== null, fn ($q) => $q->whereHas('vinculos', fn ($v) => $v->whereIn('projeto_prj_id', $data['projeto_ids'])));

        return [
            'total' => (clone $query)->count(),
            'notas' => $query->with('vinculos')
                ->orderByRaw('not_data desc nulls last')->orderByDesc('not_created_at')->orderByDesc('not_id')
                ->limit($data['limit'])->get(),
        ];
    }
}
