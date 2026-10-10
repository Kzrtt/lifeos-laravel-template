<?php

declare(strict_types=1);

namespace Modules\Renuncias\Actions;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Actions\RpcAction;
use Modules\Core\Support\Mcp\Args;
use Modules\Renuncias\Models\Renuncia;

final class BuscarRenuncias extends RpcAction
{
    protected function rules(): array
    {
        return [];
    }

    /** @return array{total: int, renuncias: Collection<int, Renuncia>} */
    protected function handle(array $data): mixed
    {
        $query = Renuncia::query()
            ->when(($data['nome'] ?? '') !== '', fn ($q) => $q->where('ren_nome', 'ilike', Args::contem($data['nome'])))
            ->when(! ($data['incluir_arquivadas'] ?? false), fn ($q) => $q->where('ren_arquivada', false));

        return [
            'total' => (clone $query)->count(),
            'renuncias' => $query->with('tentativas')->orderBy('ren_desde')->orderBy('ren_id')->limit($data['limit'])->get(),
        ];
    }
}
