<?php

declare(strict_types=1);

namespace Modules\Citacoes\Actions;

use Modules\Citacoes\Models\Citacao;
use Modules\Core\Actions\RpcAction;

final class CriarCitacao extends RpcAction
{
    public const MAX_TEXTO = 2000;

    public const MAX_AUTOR = 200;

    protected function sanitize(array $input): array
    {
        $citacao = $input['citacao'] ?? null;

        if (is_array($citacao)) {
            $citacao['texto'] = trim((string) ($citacao['texto'] ?? ''));
            $citacao['autor'] = trim((string) ($citacao['autor'] ?? ''));
        }

        return ['citacao' => is_array($citacao) ? $citacao : null];
    }

    protected function rules(): array
    {
        return [
            'citacao' => ['required', 'array'],
            'citacao.texto' => ['required', 'string', 'max:'.self::MAX_TEXTO],
            'citacao.autor' => ['required', 'string', 'max:'.self::MAX_AUTOR],
        ];
    }

    protected function codes(): array
    {
        return [
            'citacao' => 'missing_citacao',
            'citacao.texto' => 'invalid_texto',
            'citacao.autor' => 'invalid_autor',
        ];
    }

    protected function handle(array $data): mixed
    {
        return Citacao::query()->create([
            'cit_texto' => $data['citacao']['texto'],
            'cit_autor' => $data['citacao']['autor'],
        ]);
    }
}
