<?php

declare(strict_types=1);

namespace Modules\Projetos\Actions;

use Illuminate\Validation\Rule;
use Modules\Core\Actions\RpcAction;
use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Core\Exceptions\LifeosException;
use Modules\Projetos\Models\Projeto;

final class AtualizarProjeto extends RpcAction
{
    public function __construct(private readonly VocabularioReadModel $vocabulario) {}

    protected function sanitize(array $input): array
    {
        $patch = $input['patch'] ?? null;

        if (is_array($patch)) {
            if (array_key_exists('name', $patch)) {
                $patch['name'] = $this->texto($patch['name']);
            }
            if (array_key_exists('emoji', $patch)) {
                $patch['emoji'] = is_string($patch['emoji']) ? $this->textoOuNull($patch['emoji']) : null;
            }
            if (array_key_exists('status', $patch)) {
                $patch['status'] = (string) ($patch['status'] ?? '');
            }
            if (array_key_exists('tags', $patch)) {
                $patch['tags'] = $this->lista($patch['tags']);
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
            'patch.status' => ['sometimes', 'required', Rule::in($this->vocabulario->valores('projeto_status'))],
            'patch.tags.*' => [Rule::in($this->vocabulario->valores('projeto_tag'))],
        ];
    }

    protected function codes(): array
    {
        return [
            'id' => 'missing_id',
            'patch' => 'empty_patch',
            'patch.name' => 'invalid_name',
            'patch.status' => 'invalid_status',
            'patch.tags.*' => 'invalid_tags',
        ];
    }

    protected function handle(array $data): mixed
    {
        $mapa = ['name' => 'prj_name', 'emoji' => 'prj_emoji', 'status' => 'prj_status', 'tags' => 'prj_tags'];
        $update = [];
        foreach ($mapa as $campo => $coluna) {
            if (array_key_exists($campo, $data['patch'])) {
                $update[$coluna] = $data['patch'][$campo];
            }
        }
        if ($update === []) {
            throw new LifeosException('empty_patch');
        }

        $projeto = $this->achar(Projeto::class, $data['id']);
        $projeto->update($update);

        return $projeto;
    }
}
