<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticateLifeosToken;
use App\Http\Middleware\AuthenticateMcpToken;
use App\Http\Middleware\ResolvePublicInstance;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Request;
use Modules\Core\Exceptions\LifeosException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // `lifeos.senha` é a borda de autenticação: resolve a entidade pela
        // senha mestre e popula o TenantContext. As rotas dos módulos só citam
        // o alias.
        // O conteúdo (notas, descrições) vai e volta byte a byte: nada de trim nem
        // "" -> null automáticos. Cada Action normaliza o que precisa.
        $middleware->remove([TrimStrings::class, ConvertEmptyStringsToNull::class]);

        $middleware->alias([
            'lifeos.senha' => AuthenticateLifeosToken::class,
            'lifeos.mcp' => AuthenticateMcpToken::class,
            'lifeos.publico' => ResolvePublicInstance::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // O contrato do LifeOS é `{ok:false, error:"codigo"}` com o status que a
        // Edge Function equivalente usava. Vale só para as rotas do contrato.
        $contrato = static fn (Request $r): bool => $r->is('functions/v1/*', 'rest/v1/*');

        $exceptions->shouldRenderJsonWhen(fn (Request $r, Throwable $e): bool => $contrato($r) || $r->expectsJson());

        $exceptions->render(function (LifeosException $e, Request $r) {
            return response()->json(['ok' => false, 'error' => $e->errorCode, ...$e->extra], $e->status);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $r) use ($contrato) {
            return $contrato($r)
                ? response()->json(['ok' => false, 'error' => 'method_not_allowed'], 405)
                : null;
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $r) use ($contrato) {
            return $contrato($r)
                ? response()->json(['ok' => false, 'error' => 'rate_limited'], 429, $e->getHeaders())
                : null;
        });

        $exceptions->render(function (Throwable $e, Request $r) use ($contrato) {
            if (! $contrato($r) || $e instanceof HttpExceptionInterface || ! app()->isProduction()) {
                return null;
            }

            report($e);

            return response()->json(['ok' => false, 'error' => 'internal_error'], 500);
        });
    })->create();
