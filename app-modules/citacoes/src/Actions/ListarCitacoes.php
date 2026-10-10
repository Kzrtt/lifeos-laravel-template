<?php

declare(strict_types=1);

namespace Modules\Citacoes\Actions;

use Illuminate\Database\Eloquent\Collection;
use Modules\Citacoes\Models\Citacao;
use Modules\Core\Actions\RpcAction;

final class ListarCitacoes extends RpcAction
{
    protected function rules(): array
    {
        return [];
    }

    /** @return Collection<int, Citacao> */
    protected function handle(array $data): mixed
    {
        return Citacao::query()->orderBy('cit_created_at')->orderBy('cit_id')->get();
    }
}
