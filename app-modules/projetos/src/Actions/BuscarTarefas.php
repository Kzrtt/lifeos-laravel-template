<?php

declare(strict_types=1);

namespace Modules\Projetos\Actions;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Actions\RpcAction;
use Modules\Core\Support\Mcp\Args;
use Modules\Projetos\Models\Tarefa;

final class BuscarTarefas extends RpcAction
{
    protected function rules(): array
    {
        return [];
    }

    /** @return array{total: int, tarefas: Collection<int, Tarefa>} */
    protected function handle(array $data): mixed
    {
        $query = Tarefa::query()
            ->when(($data['nome'] ?? '') !== '', fn ($q) => $q->where('tar_name', 'ilike', Args::contem($data['nome'])))
            ->when(($data['status'] ?? '') !== '', fn ($q) => $q->where('tar_status', $data['status']))
            ->when(($data['tipos'] ?? []) !== [], fn ($q) => $q->whereArrayOverlaps('tar_tipo', $data['tipos']))
            ->when(($data['projeto_ids'] ?? null) !== null, fn ($q) => $q->whereIn('projeto_prj_id', $data['projeto_ids']))
            ->when(($data['data_inicio'] ?? '') !== '', fn ($q) => $q->where('tar_data_entrega', '>=', $data['data_inicio']))
            ->when(($data['data_fim'] ?? '') !== '', fn ($q) => $q->where('tar_data_entrega', '<=', $data['data_fim']));

        return [
            'total' => (clone $query)->count(),
            'tarefas' => $query->orderBy('tar_created_at')->orderBy('tar_id')->limit($data['limit'])->get(),
        ];
    }
}
