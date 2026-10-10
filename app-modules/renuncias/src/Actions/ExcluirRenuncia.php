<?php

declare(strict_types=1);

namespace Modules\Renuncias\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Renuncias\Models\Renuncia;

final class ExcluirRenuncia extends AbstractRenunciaAction
{
    protected function sanitize(array $input): array
    {
        return ['id' => $this->texto($input['id'] ?? null)];
    }

    protected function rules(): array
    {
        return ['id' => ['required', 'string']];
    }

    protected function codes(): array
    {
        return ['id' => 'missing_id'];
    }

    protected function handle(array $data): mixed
    {
        $renuncia = $this->achar(Renuncia::class, $data['id']);

        DB::transaction(function () use ($renuncia): void {
            $renuncia->tentativas()->get()->each->delete();
            $renuncia->delete();
        });

        return $data['id'];
    }
}
