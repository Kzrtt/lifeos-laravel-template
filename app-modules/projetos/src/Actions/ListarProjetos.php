<?php

declare(strict_types=1);

namespace Modules\Projetos\Actions;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Actions\RpcAction;
use Modules\Projetos\Models\Projeto;

final class ListarProjetos extends RpcAction
{
    protected function rules(): array
    {
        return [];
    }

    /** @return Collection<int, Projeto> */
    protected function handle(array $data): mixed
    {
        return Projeto::query()->orderBy('prj_name')->orderBy('prj_id')->get();
    }
}
