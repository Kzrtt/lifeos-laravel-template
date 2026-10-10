<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Core\Contracts\McpTokenResolver;
use Modules\Core\Tenancy\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * `lifeos.mcp` — o gate do conector MCP. O token vai no path da URL; resolve a
 * instância dona dele ANTES de ler o corpo JSON-RPC (tools/list inclusive).
 * Sem token configurado, NINGUÉM entra: liberar quando a config falta
 * transformaria um erro de instalação num servidor aberto.
 *
 * O corpo de recusa é o de hoje: `{error:"unauthorized"}` (sem `ok`), 401.
 */
final class AuthenticateMcpToken
{
    private const MAX_FALHAS = 10;

    public function __construct(private readonly McpTokenResolver $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = 'lifeos-mcp-falhas:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, self::MAX_FALHAS)) {
            return new JsonResponse(['error' => 'rate_limited'], 429);
        }

        $entityId = $this->tokens->entityIdForMcpToken((string) $request->route('token', ''));
        if ($entityId === null) {
            RateLimiter::hit($key, 60);

            return new JsonResponse(['error' => 'unauthorized'], 401);
        }

        TenantContext::set($entityId);

        return $next($request);
    }
}
