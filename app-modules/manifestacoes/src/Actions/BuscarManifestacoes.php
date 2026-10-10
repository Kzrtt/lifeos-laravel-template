<?php

declare(strict_types=1);

namespace Modules\Manifestacoes\Actions;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Actions\RpcAction;
use Modules\Core\Support\Mcp\Args;
use Modules\Manifestacoes\Models\Manifestacao;

final class BuscarManifestacoes extends RpcAction
{
    protected function rules(): array
    {
        return [];
    }

    /** @return array{total: int, manifestacoes: Collection<int, Manifestacao>} */
    protected function handle(array $data): mixed
    {
        $query = Manifestacao::query()
            ->when(($data['nome'] ?? '') !== '', fn ($q) => $q->where('man_name', 'ilike', Args::contem($data['nome'])))
            ->when(($data['status'] ?? '') !== '', fn ($q) => $q->where('man_status', $data['status']))
            ->when(($data['tags'] ?? []) !== [], fn ($q) => $q->whereArrayOverlaps('man_tags', $data['tags']));

        return [
            'total' => (clone $query)->count(),
            'manifestacoes' => $query->orderBy('man_created_at')->orderBy('man_id')->limit($data['limit'])->get(),
        ];
    }
}
