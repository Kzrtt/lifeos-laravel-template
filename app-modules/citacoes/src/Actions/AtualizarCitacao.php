<?php

declare(strict_types=1);

namespace Modules\Citacoes\Actions;

use Illuminate\Support\Str;
use Modules\Citacoes\Models\Citacao;
use Modules\Core\Actions\RpcAction;
use Modules\Core\Exceptions\LifeosException;

final class AtualizarCitacao extends RpcAction
{
    protected function sanitize(array $input): array
    {
        $patch = $input['patch'] ?? null;

        if (is_array($patch)) {
            foreach (['texto', 'autor'] as $campo) {
                if (array_key_exists($campo, $patch)) {
                    $patch[$campo] = trim((string) ($patch[$campo] ?? ''));
                }
            }
        }

        return [
            'id' => trim((string) ($input['id'] ?? '')),
            'patch' => is_array($patch) ? $patch : null,
        ];
    }

    protected function rules(): array
    {
        return [
            'id' => ['required', 'string'],
            'patch' => ['required', 'array', 'min:1'],
            'patch.texto' => ['sometimes', 'required', 'string', 'max:'.CriarCitacao::MAX_TEXTO],
            'patch.autor' => ['sometimes', 'required', 'string', 'max:'.CriarCitacao::MAX_AUTOR],
        ];
    }

    protected function codes(): array
    {
        return [
            'id' => 'missing_id',
            'patch' => 'empty_patch',
            'patch.texto' => 'invalid_texto',
            'patch.autor' => 'invalid_autor',
        ];
    }

    protected function handle(array $data): mixed
    {
        $update = [];
        if (array_key_exists('texto', $data['patch'])) {
            $update['cit_texto'] = $data['patch']['texto'];
        }
        if (array_key_exists('autor', $data['patch'])) {
            $update['cit_autor'] = $data['patch']['autor'];
        }
        if ($update === []) {
            throw new LifeosException('empty_patch');
        }

        $citacao = Str::isUuid($data['id']) ? Citacao::query()->find($data['id']) : null;
        if ($citacao === null) {
            throw LifeosException::notFound();
        }

        $citacao->update($update);

        return $citacao;
    }
}
