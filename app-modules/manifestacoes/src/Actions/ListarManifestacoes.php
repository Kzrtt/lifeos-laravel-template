<?php

declare(strict_types=1);

namespace Modules\Manifestacoes\Actions;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Actions\RpcAction;
use Modules\Manifestacoes\Models\Manifestacao;

final class ListarManifestacoes extends RpcAction
{
    protected function rules(): array
    {
        return [];
    }

    /** @return Collection<int, Manifestacao> */
    protected function handle(array $data): mixed
    {
        return Manifestacao::query()->orderBy('man_created_at')->orderBy('man_id')->get();
    }
}
