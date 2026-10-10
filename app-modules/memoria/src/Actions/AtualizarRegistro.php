<?php

declare(strict_types=1);

namespace Modules\Memoria\Actions;

use Modules\Memoria\Models\MemoriaRegistro;

final class AtualizarRegistro extends AbstractMemoriaAction
{
    protected function sanitize(array $input): array
    {
        $patch = is_array($input['patch'] ?? null) ? $input['patch'] : [];

        return [
            'id' => $this->texto($input['id'] ?? null),
            'texto' => $this->limpo($patch['texto'] ?? null, self::MAX_TEXTO) ?? '',
        ];
    }

    protected function rules(): array
    {
        return ['id' => ['required', 'string'], 'texto' => ['required', 'string']];
    }

    protected function codes(): array
    {
        return ['id' => 'missing_id', 'texto' => 'invalid_texto'];
    }

    protected function handle(array $data): mixed
    {
        $registro = $this->achar(MemoriaRegistro::class, $data['id']);
        $registro->update(['mrg_texto' => $data['texto']]);
        $registro->memoria?->touch();

        return $registro;
    }
}
