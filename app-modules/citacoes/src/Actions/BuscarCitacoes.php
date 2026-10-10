<?php

declare(strict_types=1);

namespace Modules\Citacoes\Actions;

use Illuminate\Database\Eloquent\Collection;
use Modules\Citacoes\Models\Citacao;
use Modules\Core\Actions\RpcAction;
use Modules\Core\Support\Mcp\Args;

/** Consulta de citações por trecho do texto e/ou do autor. */
final class BuscarCitacoes extends RpcAction
{
    protected function rules(): array
    {
        return [];
    }

    /** @return array{total: int, citacoes: Collection<int, Citacao>} */
    protected function handle(array $data): mixed
    {
        $query = Citacao::query()
            ->when(($data['texto'] ?? '') !== '', fn ($q) => $q->where('cit_texto', 'ilike', Args::contem($data['texto'])))
            ->when(($data['autor'] ?? '') !== '', fn ($q) => $q->where('cit_autor', 'ilike', Args::contem($data['autor'])));

        return [
            'total' => (clone $query)->count(),
            'citacoes' => $query->orderBy('cit_created_at')->orderBy('cit_id')->limit($data['limit'])->get(),
        ];
    }
}
