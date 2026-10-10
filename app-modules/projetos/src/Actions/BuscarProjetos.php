<?php

declare(strict_types=1);

namespace Modules\Projetos\Actions;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Actions\RpcAction;
use Modules\Core\Support\Mcp\Args;
use Modules\Projetos\Models\Projeto;

final class BuscarProjetos extends RpcAction
{
    protected function rules(): array
    {
        return [];
    }

    /** @return array{total: int, projetos: Collection<int, Projeto>} */
    protected function handle(array $data): mixed
    {
        $query = Projeto::query()
            ->when(($data['nome'] ?? '') !== '', fn ($q) => $q->where('prj_name', 'ilike', Args::contem($data['nome'])))
            ->when(($data['status'] ?? '') !== '', fn ($q) => $q->where('prj_status', $data['status']))
            ->when(($data['tags'] ?? []) !== [], fn ($q) => $q->whereArrayOverlaps('prj_tags', $data['tags']));

        return [
            'total' => (clone $query)->count(),
            'projetos' => $query->orderBy('prj_name')->orderBy('prj_id')->limit($data['limit'])->get(),
        ];
    }
}
