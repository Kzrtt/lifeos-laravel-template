<?php

declare(strict_types=1);

namespace Modules\Projetos\Actions;

use Illuminate\Validation\Rule;
use Modules\Core\Actions\RpcAction;
use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Projetos\Models\Projeto;

final class CriarProjeto extends RpcAction
{
    public function __construct(private readonly VocabularioReadModel $vocabulario) {}

    protected function sanitize(array $input): array
    {
        $p = $input['projeto'] ?? null;

        if (is_array($p)) {
            $p = [
                'name' => $this->texto($p['name'] ?? null),
                'status' => $this->texto($p['status'] ?? null) !== '' ? (string) $p['status'] : '',
                'emoji' => is_string($p['emoji'] ?? null) ? $this->textoOuNull($p['emoji']) : null,
                'tags' => $this->lista($p['tags'] ?? null),
            ];
        }

        return ['projeto' => is_array($p) ? $p : null];
    }

    protected function rules(): array
    {
        return [
            'projeto' => ['required', 'array'],
            'projeto.name' => ['required', 'string'],
            'projeto.status' => ['required', Rule::in($this->vocabulario->valores('projeto_status'))],
            'projeto.tags.*' => [Rule::in($this->vocabulario->valores('projeto_tag'))],
        ];
    }

    protected function codes(): array
    {
        return [
            'projeto' => 'missing_projeto',
            'projeto.name' => 'invalid_name',
            'projeto.status' => 'invalid_status',
            'projeto.tags.*' => 'invalid_tags',
        ];
    }

    protected function handle(array $data): mixed
    {
        $p = $data['projeto'];

        return Projeto::query()->create([
            'prj_name' => $p['name'],
            'prj_emoji' => $p['emoji'],
            'prj_status' => $p['status'],
            'prj_tags' => $p['tags'],
        ]);
    }
}
