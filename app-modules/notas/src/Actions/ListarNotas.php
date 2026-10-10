<?php

declare(strict_types=1);

namespace Modules\Notas\Actions;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Actions\RpcAction;
use Modules\Notas\Models\Nota;

final class ListarNotas extends RpcAction
{
    protected function rules(): array
    {
        return [];
    }

    /** @return Collection<int, Nota> */
    protected function handle(array $data): mixed
    {
        return Nota::query()->with('vinculos')
            ->orderByRaw('not_data desc nulls last')
            ->orderByDesc('not_created_at')->orderByDesc('not_id')
            ->get();
    }
}
