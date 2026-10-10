<?php

declare(strict_types=1);

namespace Modules\Notas\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Core\Contracts\ProjetoReadModel;
use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Core\Exceptions\LifeosException;
use Modules\Notas\Models\Nota;

/**
 * A troca dos projetos vinculados acontece na MESMA transação da nota (hoje são
 * dois fetch separados). Reaproveita a sincronização de CriarNota.
 */
final class AtualizarNota extends AbstractNotaAction
{
    public function __construct(
        private readonly VocabularioReadModel $vocab,
        private readonly ProjetoReadModel $projs,
    ) {}

    protected function sanitize(array $input): array
    {
        $patch = $input['patch'] ?? null;

        if (is_array($patch)) {
            if (array_key_exists('name', $patch)) {
                $patch['name'] = $this->texto($patch['name']);
            }
            if (array_key_exists('tipo', $patch)) {
                $patch['tipo'] = $this->lista($patch['tipo']);
            }
            if (array_key_exists('conteudo_md', $patch)) {
                $patch['conteudo_md'] = $patch['conteudo_md'] === null ? null : (string) $patch['conteudo_md'];
            }
            if (array_key_exists('projeto_ids', $patch)) {
                $patch['projeto_ids'] = $this->projetoIds($patch, 'projeto_ids');
            }
        }

        return ['id' => $this->texto($input['id'] ?? null), 'patch' => is_array($patch) ? $patch : null];
    }

    protected function rules(): array
    {
        return [
            'id' => ['required', 'string'],
            'patch' => ['required', 'array', 'min:1'],
            'patch.name' => ['sometimes', 'required', 'string'],
            'patch.tipo.*' => [Rule::in($this->vocab->valores('nota_tipo'))],
            'patch.data' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'patch.projeto_ids' => ['array'],
            'patch.projeto_ids.*' => ['required', 'string'],
        ];
    }

    protected function codes(): array
    {
        return [
            'id' => 'missing_id',
            'patch' => 'empty_patch',
            'patch.name' => 'invalid_name',
            'patch.tipo.*' => 'invalid_tipo',
            'patch.data' => 'invalid_data',
            'patch.projeto_ids' => 'invalid_projeto_ids',
            'patch.projeto_ids.*' => 'invalid_projeto_ids',
        ];
    }

    protected function handle(array $data): mixed
    {
        $mapa = ['name' => 'not_name', 'tipo' => 'not_tipo', 'data' => 'not_data', 'conteudo_md' => 'not_conteudo_md'];
        $update = [];
        foreach ($mapa as $campo => $coluna) {
            if (array_key_exists($campo, $data['patch'])) {
                $update[$coluna] = $data['patch'][$campo];
            }
        }
        $ids = array_key_exists('projeto_ids', $data['patch']) ? array_values(array_unique($data['patch']['projeto_ids'])) : null;

        if ($update === [] && $ids === null) {
            throw new LifeosException('empty_patch');
        }

        $nota = $this->achar(Nota::class, $data['id']);

        if ($ids !== null && count($this->projs->existentes($ids)) !== count($ids)) {
            throw new LifeosException('invalid_projeto_ids');
        }

        return DB::transaction(function () use ($nota, $update, $ids): Nota {
            if ($update !== []) {
                $nota->update($update);
            }
            if ($ids !== null) {
                $this->sincronizar($nota, $ids);
            }

            return $nota->load('vinculos');
        });
    }
}
