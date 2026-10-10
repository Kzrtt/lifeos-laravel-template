<?php

declare(strict_types=1);

namespace Modules\Memoria\Actions;

use Modules\Memoria\Models\Memoria;

final class ListarMemorias extends AbstractMemoriaAction
{
    protected function rules(): array
    {
        return [];
    }

    /** @return array{memorias: \Illuminate\Database\Eloquent\Collection<int, Memoria>, categorias: list<string>} */
    protected function handle(array $data): mixed
    {
        return [
            'memorias' => Memoria::query()->with('registros')->orderByDesc('mem_updated_at')->orderByDesc('mem_id')->get(),
            'categorias' => $this->vocabulario->valores('memoria_categoria'),
        ];
    }
}
