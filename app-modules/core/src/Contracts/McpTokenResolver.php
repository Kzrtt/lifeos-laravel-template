<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Descobre a instância dona de um token de conector MCP (o `mcp_token` que vai no
 * path da URL). Cada instância tem o seu. Implementada por `acesso`.
 */
interface McpTokenResolver
{
    public function entityIdForMcpToken(string $token): ?string;
}
