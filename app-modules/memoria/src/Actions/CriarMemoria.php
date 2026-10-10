<?php

declare(strict_types=1);

namespace Modules\Memoria\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Core\Exceptions\LifeosException;
use Modules\Memoria\Models\Memoria;

final class CriarMemoria extends AbstractMemoriaAction
{
    protected function sanitize(array $input): array
    {
        $m = $input['memoria'] ?? null;

        if (is_array($m)) {
            $registros = [];
            foreach (is_array($m['registros'] ?? null) ? $m['registros'] : [] as $t) {
                $registros[] = $this->limpo($t, self::MAX_TEXTO) ?? '';
            }
            $m = [
                'titulo' => $this->limpo($m['titulo'] ?? null, self::MAX_TITULO) ?? '',
                'descricao' => $this->texto($m['descricao'] ?? null),
                'categoria' => $this->texto($m['categoria'] ?? null),
                'registros' => $registros,
                'origem' => $this->limpo($m['origem'] ?? null, self::MAX_ORIGEM) ?? 'manual',
            ];
        }

        return ['memoria' => is_array($m) ? $m : null];
    }

    protected function rules(): array
    {
        return [
            'memoria' => ['required', 'array'],
            'memoria.titulo' => ['required', 'string'],
            'memoria.descricao' => ['max:'.self::MAX_DESCRICAO],
            'memoria.categoria' => ['required', Rule::in($this->vocabulario->valores('memoria_categoria'))],
            'memoria.registros.*' => ['required', 'string'],
        ];
    }

    protected function codes(): array
    {
        return [
            'memoria' => 'missing_memoria',
            'memoria.titulo' => 'invalid_titulo',
            'memoria.descricao' => 'invalid_descricao',
            'memoria.categoria' => 'invalid_categoria',
            'memoria.registros.*' => 'invalid_texto',
        ];
    }

    protected function handle(array $data): mixed
    {
        $m = $data['memoria'];

        if ($this->tituloDuplicado($m['titulo'])) {
            throw LifeosException::conflict('titulo_duplicado');
        }

        return $this->protegendoTitulo(fn (): Memoria => DB::transaction(function () use ($m): Memoria {
            $memoria = Memoria::query()->create([
                'mem_titulo' => $m['titulo'],
                'mem_descricao' => $m['descricao'],
                'mem_categoria' => $m['categoria'],
            ]);
            foreach ($m['registros'] as $texto) {
                $memoria->registros()->create(['mrg_texto' => $texto, 'mrg_origem' => $m['origem']]);
            }

            return $memoria->load('registros');
        }));
    }
}
