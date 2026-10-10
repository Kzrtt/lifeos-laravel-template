<?php

declare(strict_types=1);

namespace Modules\Acesso\Http\Controllers;

use Modules\Acesso\Actions\DefinirSegredo;
use Modules\Acesso\Actions\ExcluirSegredo;
use Modules\Acesso\Actions\LerConfig;
use Modules\Acesso\Actions\MontarMcpUrl;
use Modules\Core\Http\RpcController;

/** POST /functions/v1/lifeos-config — query, set, delete, mcp_url. */
final class ConfigRpcController extends RpcController
{
    protected function actions(): array
    {
        return [
            'query' => [LerConfig::class, fn (array $config): array => ['config' => $config]],
            'set' => [DefinirSegredo::class, fn (array $r): array => $r],
            'delete' => [ExcluirSegredo::class, fn (string $key): array => ['key' => $key]],
            'mcp_url' => [MontarMcpUrl::class, fn (array $r): array => $r],
        ];
    }
}
