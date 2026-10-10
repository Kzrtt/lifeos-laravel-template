<?php

declare(strict_types=1);

namespace Modules\Renuncias\Actions;

use Illuminate\Database\Eloquent\Collection;
use Modules\Renuncias\Models\Renuncia;

final class ListarRenuncias extends AbstractRenunciaAction
{
    protected function rules(): array
    {
        return [];
    }

    /** @return Collection<int, Renuncia> */
    protected function handle(array $data): mixed
    {
        return Renuncia::query()->with('tentativas')->orderBy('ren_desde')->orderBy('ren_id')->get();
    }
}
