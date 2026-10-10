<?php

declare(strict_types=1);

namespace Modules\Projetos\Actions;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Core\Actions\RpcAction;
use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Core\Exceptions\LifeosException;
use Modules\Projetos\Models\Projeto;
use Modules\Projetos\Models\Tarefa;

final class AtualizarTarefa extends RpcAction
{
    public function __construct(private readonly VocabularioReadModel $vocabulario) {}

    protected function sanitize(array $input): array
    {
        $patch = $input['patch'] ?? null;

        if (is_array($patch)) {
            $limpar = [
                'name' => fn ($v) => $this->texto($v),
                'status' => fn ($v) => (string) ($v ?? ''),
                'tipo' => fn ($v) => $this->lista($v),
                'data_entrega' => fn ($v) => $this->dataOuNull($v),
                'projeto_id' => fn ($v) => $this->texto($v),
                'descricao' => fn ($v) => $this->textoOuNull($v),
            ];
            foreach ($limpar as $campo => $fn) {
                if (array_key_exists($campo, $patch)) {
                    $patch[$campo] = $fn($patch[$campo]);
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
            'patch.name' => ['sometimes', 'required', 'string'],
            'patch.status' => ['sometimes', 'required', Rule::in($this->vocabulario->valores('tarefa_status'))],
            'patch.tipo.*' => [Rule::in($this->vocabulario->valores('tarefa_tipo'))],
            'patch.projeto_id' => ['sometimes', 'required', 'string'],
        ];
    }

    protected function codes(): array
    {
        return [
            'id' => 'missing_id',
            'patch' => 'empty_patch',
            'patch.name' => 'invalid_name',
            'patch.status' => 'invalid_status',
            'patch.tipo.*' => 'invalid_tipo',
            'patch.projeto_id' => 'invalid_projeto_id',
        ];
    }

    protected function handle(array $data): mixed
    {
        $mapa = [
            'name' => 'tar_name', 'status' => 'tar_status', 'tipo' => 'tar_tipo',
            'data_entrega' => 'tar_data_entrega', 'projeto_id' => 'projeto_prj_id', 'descricao' => 'tar_descricao',
        ];
        $update = [];
        foreach ($mapa as $campo => $coluna) {
            if (array_key_exists($campo, $data['patch'])) {
                $update[$coluna] = $data['patch'][$campo];
            }
        }
        if ($update === []) {
            throw new LifeosException('empty_patch');
        }

        $tarefa = $this->achar(Tarefa::class, $data['id']);

        // Trocar de projeto é permitido; ficar sem projeto, nunca.
        if (isset($update['projeto_prj_id'])
            && (! Str::isUuid($update['projeto_prj_id']) || ! Projeto::query()->whereKey($update['projeto_prj_id'])->exists())) {
            throw new LifeosException('invalid_projeto_id');
        }

        $tarefa->update($update);

        return $tarefa;
    }
}
