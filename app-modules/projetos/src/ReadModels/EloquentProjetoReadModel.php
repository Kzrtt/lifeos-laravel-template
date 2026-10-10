<?php

declare(strict_types=1);

namespace Modules\Projetos\ReadModels;

use Illuminate\Support\Str;
use Modules\Core\Contracts\ProjetoReadModel;
use Modules\Core\DTOs\ProjetoResumoDTO;
use Modules\Projetos\Models\Projeto;

final class EloquentProjetoReadModel implements ProjetoReadModel
{
    public function existentes(array $ids): array
    {
        $validos = array_values(array_unique(array_filter($ids, fn (string $id): bool => Str::isUuid($id))));

        return $validos === [] ? [] : Projeto::query()->whereKey($validos)->pluck('prj_id')->all();
    }

    public function catalogo(): array
    {
        return Projeto::query()->orderBy('prj_name')->orderBy('prj_id')->get()
            ->map(fn (Projeto $p): ProjetoResumoDTO => new ProjetoResumoDTO($p->prj_id, $p->prj_name, $p->prj_emoji, $p->prj_status, $p->prj_tags))
            ->all();
    }
}
