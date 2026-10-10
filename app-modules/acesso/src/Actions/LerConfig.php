<?php

declare(strict_types=1);

namespace Modules\Acesso\Actions;

use Modules\Acesso\Models\AdminConfig;
use Modules\Acesso\Services\SegredoService;
use Modules\Core\Actions\RpcAction;

/** Estado dos segredos da lista branca — nunca o valor cru. */
final class LerConfig extends RpcAction
{
    protected function rules(): array
    {
        return [];
    }

    /** @return array<string, array{definido: bool, mascarado: string, atualizado_em: ?string}> */
    protected function handle(array $data): mixed
    {
        $linhas = AdminConfig::query()->whereIn('cfg_key', SegredoService::CHAVES_VALIDAS)->get()->keyBy('cfg_key');

        $config = [];
        foreach (SegredoService::CHAVES_VALIDAS as $chave) {
            $linha = $linhas->get($chave);
            $config[$chave] = [
                'definido' => $linha !== null && $linha->cfg_value !== '',
                'mascarado' => $linha !== null ? SegredoService::mascarar($linha->cfg_value) : '',
                'atualizado_em' => $linha?->cfg_updated_at?->toIso8601String(),
            ];
        }

        return $config;
    }
}
