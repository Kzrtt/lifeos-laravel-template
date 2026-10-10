<?php

declare(strict_types=1);

namespace Modules\Memoria\Actions;

use Modules\Memoria\Models\MemoriaRegistro;

final class ExcluirRegistro extends AbstractMemoriaAction
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
        $registro = $this->achar(MemoriaRegistro::class, $data['id']);
        $registro->delete();
        $registro->memoria?->touch();

        return $data['id'];
    }
}
