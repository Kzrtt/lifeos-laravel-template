<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Acesso\Services\TokenService;
use Modules\Core\Exceptions\LifeosException;
use Modules\Core\Tenancy\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * `lifeos.senha` — a borda de autenticação das rotas `/functions/v1/lifeos-*`.
 *
 * Lê a senha do corpo (`token`) — ou da query, no webhook do atalho do iPhone —,
 * acha a senha mestre dona dela, descobre a entidade e chama
 * TenantContext::set(). Os módulos nunca sabem como o tenant foi resolvido.
 *
 * Mesmos códigos de hoje: sem senha `missing_token` (400), senha errada ou não
 * mestre `unauthorized` (401), corpo ilegível `bad_request` (400). Errar a senha
 * demais vira `rate_limited` (429): o que antes era força bruta possível.
 */
final class AuthenticateLifeosToken
{
    private const MAX_FALHAS = 10;

    public function __construct(private readonly TokenService $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isJson() && $request->getContent() !== '' && json_decode($request->getContent()) === null) {
            throw new LifeosException('bad_request', 400);
        }

        $plain = trim((string) $request->input('token', ''));
        if ($plain === '') {
            throw new LifeosException('missing_token', 400);
        }

        $key = 'lifeos-falhas:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, self::MAX_FALHAS)) {
            throw new LifeosException('rate_limited', 429);
        }

        $token = $this->tokens->findByPlain($plain);
        if ($token === null || ! $token->tok_is_master) {
            RateLimiter::hit($key, 60);
            throw new LifeosException('unauthorized', 401);
        }

        TenantContext::set($token->entity_ent_id);
        $request->attributes->set('lifeos.token_id', $token->tok_id);

        return $next($request);
    }
}
