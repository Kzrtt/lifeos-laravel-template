<?php

declare(strict_types=1);

namespace Modules\Memoria\Actions;

use Illuminate\Validation\Rule;
use Modules\Core\Exceptions\LifeosException;
use Modules\Memoria\Models\Memoria;

final class AtualizarMemoria extends AbstractMemoriaAction
{
    protected function sanitize(array $input): array
    {
        $patch = $input['patch'] ?? null;

        if (is_array($patch)) {
            if (array_key_exists('titulo', $patch)) {
                $patch['titulo'] = $this->limpo($patch['titulo'], self::MAX_TITULO) ?? '';
            }
            foreach (['descricao', 'categoria'] as $campo) {
                if (array_key_exists($campo, $patch)) {
                    $patch[$campo] = $this->texto($patch[$campo]);
                }
            }
        }

        return ['id' => $this->texto($input['id'] ?? null), 'patch' => is_array($patch) ? $patch : null];
    }

    protected function rules(): array
    {
        return [
            'id' => ['required', 'string'],
            'patch' => ['required', 'array', 'min:1'],
            'patch.titulo' => ['sometimes', 'required', 'string'],
            'patch.descricao' => ['sometimes', 'max:'.self::MAX_DESCRICAO],
            'patch.categoria' => ['sometimes', Rule::in($this->vocabulario->valores('memoria_categoria'))],
        ];
    }

    protected function codes(): array
    {
        return [
            'id' => 'missing_id', 'patch' => 'empty_patch', 'patch.titulo' => 'invalid_titulo',
            'patch.descricao' => 'invalid_descricao', 'patch.categoria' => 'invalid_categoria',
        ];
    }

    protected function handle(array $data): mixed
    {
        $mapa = ['titulo' => 'mem_titulo', 'descricao' => 'mem_descricao', 'categoria' => 'mem_categoria'];
        $update = [];
        foreach ($mapa as $campo => $coluna) {
            if (array_key_exists($campo, $data['patch'])) {
                $update[$coluna] = $data['patch'][$campo];
            }
        }
        if ($update === []) {
            throw new LifeosException('empty_patch');
        }

        $memoria = $this->achar(Memoria::class, $data['id']);

        if (isset($update['mem_titulo']) && $this->tituloDuplicado($update['mem_titulo'], $memoria->mem_id)) {
            throw LifeosException::conflict('titulo_duplicado');
        }

        $this->protegendoTitulo(fn () => $memoria->update($update));

        return $memoria->load('registros');
    }
}
