<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Contracts\EntityDirectory;
use Modules\Core\Tenancy\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * `lifeos.publico` — rotas sem senha (manifest, entradas, galeria) precisam saber de
 * quem é a página ANTES de qualquer login. Resolve a instância padrão do `.env`
 * (LIFEOS_ENTITY_SLUG); com uma segunda instância, o mesmo ponto passa a resolver
 * pelo host (subdomínio) sem mudar mais nada.
 */
final class ResolvePublicInstance
{
    public function __construct(private readonly EntityDirectory $entidades) {}

    public function handle(Request $request, Closure $next): Response
    {
        $instancia = $this->entidades->bySlug((string) config('lifeos.entity_slug'));

        abort_if($instancia === null, 404);

        TenantContext::set($instancia->id);

        return $next($request);
    }
}
