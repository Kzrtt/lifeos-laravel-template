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

final class CriarTarefa extends RpcAction
{
    public function __construct(private readonly VocabularioReadModel $vocabulario) {}

    protected function sanitize(array $input): array
    {
        $t = $input['tarefa'] ?? null;

        if (is_array($t)) {
            $t = [
                'name' => $this->texto($t['name'] ?? null),
                'status' => (string) ($t['status'] ?? ''),
                'tipo' => $this->lista($t['tipo'] ?? null),
                'projeto_id' => $this->texto($t['projeto_id'] ?? null),
                'data_entrega' => $this->dataOuNull($t['data_entrega'] ?? null),
                'descricao' => $this->textoOuNull($t['descricao'] ?? null),
            ];
        }

        return ['tarefa' => is_array($t) ? $t : null];
    }

    protected function rules(): array
    {
        return [
            'tarefa' => ['required', 'array'],
            'tarefa.name' => ['required', 'string'],
            'tarefa.status' => ['required', Rule::in($this->vocabulario->valores('tarefa_status'))],
            'tarefa.tipo.*' => [Rule::in($this->vocabulario->valores('tarefa_tipo'))],
            'tarefa.projeto_id' => ['required', 'string'],
        ];
    }

    protected function codes(): array
    {
        return [
            'tarefa' => 'missing_tarefa',
            'tarefa.name' => 'invalid_name',
            'tarefa.status' => 'invalid_status',
            'tarefa.tipo.*' => 'invalid_tipo',
            'tarefa.projeto_id' => 'missing_projeto_id',
        ];
    }

    protected function handle(array $data): mixed
    {
        $t = $data['tarefa'];

        // Tarefa nunca existe sem projeto (e o projeto é da mesma instância).
        if (! Str::isUuid($t['projeto_id']) || ! Projeto::query()->whereKey($t['projeto_id'])->exists()) {
            throw new LifeosException('invalid_projeto_id');
        }

        return Tarefa::query()->create([
            'tar_name' => $t['name'],
            'tar_status' => $t['status'],
            'tar_tipo' => $t['tipo'],
            'projeto_prj_id' => $t['projeto_id'],
            'tar_data_entrega' => $t['data_entrega'],
            'tar_descricao' => $t['descricao'],
        ]);
    }
}
