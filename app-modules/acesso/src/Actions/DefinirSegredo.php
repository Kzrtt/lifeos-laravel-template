<?php

declare(strict_types=1);

namespace Modules\Acesso\Actions;

use Modules\Acesso\Services\SegredoService;
use Modules\Core\Actions\RpcAction;

final class DefinirSegredo extends RpcAction
{
    public function __construct(private readonly SegredoService $segredos) {}

    protected function sanitize(array $input): array
    {
        return [
            'key' => trim((string) ($input['key'] ?? '')),
            'value' => trim((string) ($input['value'] ?? '')),
        ];
    }

    protected function rules(): array
    {
        return [
            'key' => ['required', 'in:'.implode(',', SegredoService::CHAVES_VALIDAS)],
            'value' => ['required', 'string'],
        ];
    }

    protected function codes(): array
    {
        return ['key' => 'invalid_key', 'value' => 'empty_value'];
    }

    /** @return array{key: string, mascarado: string, atualizado_em: ?string} */
    protected function handle(array $data): mixed
    {
        $linha = $this->segredos->set($data['key'], $data['value']);

        return [
            'key' => $data['key'],
            'mascarado' => SegredoService::mascarar($data['value']),
            'atualizado_em' => $linha->cfg_updated_at?->toIso8601String(),
        ];
    }
}
