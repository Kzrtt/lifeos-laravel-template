<?php

declare(strict_types=1);

namespace Modules\Financas\Actions;

use Illuminate\Database\Eloquent\Collection;
use Modules\Financas\Models\Recorrencia;

final class ListarRecorrencias extends AbstractRecorrenciaAction
{
    protected function rules(): array
    {
        return [];
    }

    /** @return Collection<int, Recorrencia> */
    protected function handle(array $data): mixed
    {
        return Recorrencia::query()->orderBy('rec_direcao')->orderBy('rec_nome')->get();
    }
}
