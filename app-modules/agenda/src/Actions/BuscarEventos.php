<?php

declare(strict_types=1);

namespace Modules\Agenda\Actions;

use Illuminate\Database\Eloquent\Collection;
use Modules\Agenda\Models\Evento;
use Modules\Core\Actions\RpcAction;
use Modules\Core\Support\Mcp\Args;

/**
 * Consulta filtrada de eventos. A janela é por SOBREPOSIÇÃO, não só "date dentro
 * do intervalo": um evento de vários dias que começou antes de `data_inicio` mas
 * ainda estava em curso precisa entrar (o fim do evento — `date_fim`, ou `date`
 * quando é de um dia só — >= data_inicio).
 */
final class BuscarEventos extends RpcAction
{
    protected function rules(): array
    {
        return [];
    }

    /** @return array{total: int, eventos: Collection<int, Evento>} */
    protected function handle(array $data): mixed
    {
        $inicio = $data['data_inicio'] ?? '';

        $query = Evento::query()
            ->when(($data['nome'] ?? '') !== '', fn ($q) => $q->where('evt_name', 'ilike', Args::contem($data['nome'])))
            ->when(($data['tipos'] ?? []) !== [], fn ($q) => $q->whereIn('evt_tipo', $data['tipos']))
            ->when(($data['projeto_ids'] ?? null) !== null, fn ($q) => $q->whereIn('projeto_prj_id', $data['projeto_ids']))
            ->when($inicio !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('evt_date_fim', '>=', $inicio)
                ->orWhere(fn ($s) => $s->whereNull('evt_date_fim')->where('evt_date', '>=', $inicio))))
            ->when(($data['data_fim'] ?? '') !== '', fn ($q) => $q->where('evt_date', '<=', $data['data_fim']));

        return [
            'total' => (clone $query)->count(),
            'eventos' => $query->orderByDesc('evt_date')->orderByDesc('evt_created_at')->orderByDesc('evt_id')->limit($data['limit'])->get(),
        ];
    }
}
