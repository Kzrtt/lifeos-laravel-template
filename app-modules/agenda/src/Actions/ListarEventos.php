<?php

declare(strict_types=1);

namespace Modules\Agenda\Actions;

use Illuminate\Database\Eloquent\Collection;
use Modules\Agenda\Models\Evento;
use Modules\Core\Actions\RpcAction;

/** Janela [from, to]: o evento entra se começa até `to` e termina (ou, sem fim, começa) a partir de `from`. */
final class ListarEventos extends RpcAction
{
    protected function sanitize(array $input): array
    {
        return ['from' => $this->texto($input['from'] ?? null), 'to' => $this->texto($input['to'] ?? null)];
    }

    protected function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
        ];
    }

    protected function codes(): array
    {
        return ['from' => 'invalid_range', 'to' => 'invalid_range'];
    }

    /** @return Collection<int, Evento> */
    protected function handle(array $data): mixed
    {
        return Evento::query()
            ->where('evt_date', '<=', $data['to'])
            ->where(fn ($q) => $q
                ->where('evt_date_fim', '>=', $data['from'])
                ->orWhere(fn ($q) => $q->whereNull('evt_date_fim')->where('evt_date', '>=', $data['from'])))
            ->orderBy('evt_date')->orderBy('evt_created_at')->orderBy('evt_id')
            ->get();
    }
}
