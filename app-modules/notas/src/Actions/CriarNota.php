<?php

declare(strict_types=1);

namespace Modules\Notas\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Core\Contracts\ProjetoReadModel;
use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Core\Exceptions\LifeosException;
use Modules\Notas\Models\Nota;

final class CriarNota extends AbstractNotaAction
{
    public function __construct(
        private readonly VocabularioReadModel $vocabulario,
        private readonly ProjetoReadModel $projetos,
    ) {}

    protected function sanitize(array $input): array
    {
        $n = $input['nota'] ?? null;

        if (is_array($n)) {
            $n = [
                'name' => $this->texto($n['name'] ?? null),
                'tipo' => $this->lista($n['tipo'] ?? null),
                'data' => $n['data'] ?? null,
                'conteudo_md' => isset($n['conteudo_md']) ? (string) $n['conteudo_md'] : null,
                'projeto_ids' => $this->projetoIds($n, 'projeto_ids'),
            ];
        }

        return ['nota' => is_array($n) ? $n : null];
    }

    protected function rules(): array
    {
        return [
            'nota' => ['required', 'array'],
            'nota.name' => ['required', 'string'],
            'nota.tipo.*' => [Rule::in($this->vocabulario->valores('nota_tipo'))],
            'nota.data' => ['nullable', 'date_format:Y-m-d'],
            'nota.projeto_ids' => ['array'],
            'nota.projeto_ids.*' => ['required', 'string'],
        ];
    }

    protected function codes(): array
    {
        return [
            'nota' => 'missing_nota',
            'nota.name' => 'invalid_name',
            'nota.tipo.*' => 'invalid_tipo',
            'nota.data' => 'invalid_data',
            'nota.projeto_ids' => 'invalid_projeto_ids',
            'nota.projeto_ids.*' => 'invalid_projeto_ids',
        ];
    }

    protected function handle(array $data): mixed
    {
        $n = $data['nota'];
        $ids = array_values(array_unique($n['projeto_ids']));

        if (count($this->projetos->existentes($ids)) !== count($ids)) {
            throw new LifeosException('invalid_projeto_ids');
        }

        return DB::transaction(function () use ($n, $ids): Nota {
            $nota = Nota::query()->create([
                'not_name' => $n['name'],
                'not_tipo' => $n['tipo'],
                'not_data' => $n['data'],
                'not_conteudo_md' => $n['conteudo_md'],
            ]);
            $this->sincronizar($nota, $ids);

            return $nota->load('vinculos');
        });
    }
}
