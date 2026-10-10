<?php

declare(strict_types=1);

namespace Modules\Acesso\Actions;

use Modules\Acesso\Services\SegredoService;
use Modules\Core\Actions\RpcAction;

/**
 * A URL que se cola no claude.ai (Connectors). Única exceção ao "o valor nunca
 * volta": a URL só serve inteira. Continua atrás do gate mestre.
 */
final class MontarMcpUrl extends RpcAction
{
    public function __construct(private readonly SegredoService $segredos) {}

    protected function rules(): array
    {
        return [];
    }

    /** @return array{definido: bool, url: string, base: string} */
    protected function handle(array $data): mixed
    {
        $base = url('/functions/v1/lifeos-mcp');
        $token = $this->segredos->get('mcp_token')?->cfg_value ?? '';

        return $token === ''
            ? ['definido' => false, 'url' => '', 'base' => $base]
            : ['definido' => true, 'url' => $base.'/'.$token, 'base' => $base];
    }
}
