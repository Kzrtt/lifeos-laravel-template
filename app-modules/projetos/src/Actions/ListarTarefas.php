<?php

declare(strict_types=1);

namespace Modules\Projetos\Actions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Modules\Core\Actions\RpcAction;
use Modules\Projetos\Models\Tarefa;

final class ListarTarefas extends RpcAction
{
    protected function sanitize(array $input): array
    {
        return ['projeto_id' => $this->texto($input['projeto_id'] ?? null)];
    }

    protected function rules(): array
    {
        return [];
    }

    /** @return Collection<int, Tarefa> */
    protected function handle(array $data): mixed
    {
        $query = Tarefa::query()->orderBy('tar_created_at')->orderBy('tar_id');

        if ($data['projeto_id'] !== '') {
            // Um id que não é UUID não casa com nada (e não vira erro de SQL).
            Str::isUuid($data['projeto_id'])
                ? $query->where('projeto_prj_id', $data['projeto_id'])
                : $query->whereRaw('false');
        }

        return $query->get();
    }
}
