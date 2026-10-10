<?php

declare(strict_types=1);

namespace Modules\Financas\Actions;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Actions\RpcAction;
use Modules\Core\Support\Mcp\Args;
use Modules\Financas\Models\Movimentacao;

/**
 * Consulta filtrada de movimentações. `tipo` guarda direção e meio juntos: a
 * direção é um "contém" e os meios, um "qualquer um", na mesma coluna.
 */
final class BuscarMovimentacoes extends RpcAction
{
    protected function rules(): array
    {
        return [];
    }

    /** @return array{total: int, movimentacoes: Collection<int, Movimentacao>} */
    protected function handle(array $data): mixed
    {
        $query = Movimentacao::query()
            ->when(($data['nome'] ?? '') !== '', fn ($q) => $q->where('mov_name', 'ilike', Args::contem($data['nome'])))
            ->when(($data['direcao'] ?? '') !== '', fn ($q) => $q->whereArrayHas('mov_tipo', $data['direcao']))
            ->when(($data['meios'] ?? []) !== [], fn ($q) => $q->whereArrayOverlaps('mov_tipo', $data['meios']))
            ->when(($data['data_inicio'] ?? '') !== '', fn ($q) => $q->where('mov_date', '>=', $data['data_inicio']))
            ->when(($data['data_fim'] ?? '') !== '', fn ($q) => $q->where('mov_date', '<=', $data['data_fim']))
            ->when(($data['valor_min'] ?? null) !== null, fn ($q) => $q->where('mov_valor', '>=', $data['valor_min']))
            ->when(($data['valor_max'] ?? null) !== null, fn ($q) => $q->where('mov_valor', '<=', $data['valor_max']));

        return [
            'total' => (clone $query)->count(),
            'movimentacoes' => $query->orderByDesc('mov_date')->orderByDesc('mov_created_at')->orderByDesc('mov_id')->limit($data['limit'])->get(),
        ];
    }
}
