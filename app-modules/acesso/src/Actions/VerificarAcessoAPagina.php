<?php

declare(strict_types=1);

namespace Modules\Acesso\Actions;

use Modules\Acesso\Services\GateDePaginas;
use Modules\Core\Actions\RpcAction;

/**
 * check_page_access(p_token, p_page): o gate das páginas protegidas do archive.
 * Mestre passa em QUALQUER página; as demais, só nas concedidas. Responde só
 * true/false — nunca diz por que falhou.
 */
final class VerificarAcessoAPagina extends RpcAction
{
    public function __construct(private readonly GateDePaginas $gate) {}

    protected function sanitize(array $input): array
    {
        return [
            'p_token' => trim((string) ($input['p_token'] ?? '')),
            'p_page' => trim((string) ($input['p_page'] ?? '')),
        ];
    }

    protected function rules(): array
    {
        return [];
    }

    protected function handle(array $data): mixed
    {
        return $this->gate->entidadeSeAutorizado($data['p_token'], $data['p_page']) !== null;
    }
}
