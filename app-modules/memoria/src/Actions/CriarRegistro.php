<?php

declare(strict_types=1);

namespace Modules\Memoria\Actions;

use Modules\Memoria\Models\Memoria;

final class CriarRegistro extends AbstractMemoriaAction
{
    protected function sanitize(array $input): array
    {
        $r = $input['registro'] ?? null;

        if (is_array($r)) {
            $r = [
                'memoria_id' => $this->texto($r['memoria_id'] ?? null),
                'texto' => $this->limpo($r['texto'] ?? null, self::MAX_TEXTO) ?? '',
                'origem' => $this->limpo($r['origem'] ?? null, self::MAX_ORIGEM) ?? 'manual',
            ];
        }

        return ['registro' => is_array($r) ? $r : null];
    }

    protected function rules(): array
    {
        return [
            'registro' => ['required', 'array'],
            'registro.memoria_id' => ['required', 'string'],
            'registro.texto' => ['required', 'string'],
        ];
    }

    protected function codes(): array
    {
        return ['registro' => 'missing_registro', 'registro.memoria_id' => 'missing_memoria_id', 'registro.texto' => 'invalid_texto'];
    }

    protected function handle(array $data): mixed
    {
        $r = $data['registro'];
        $memoria = $this->achar(Memoria::class, $r['memoria_id']);

        $registro = $memoria->registros()->create(['mrg_texto' => $r['texto'], 'mrg_origem' => $r['origem']]);
        $this->tocar($memoria);

        return $registro;
    }

    protected function tocar(Memoria $memoria): void
    {
        // A memória sobe no índice: quem foi mexido por último aparece primeiro.
        $memoria->touch();
    }
}
