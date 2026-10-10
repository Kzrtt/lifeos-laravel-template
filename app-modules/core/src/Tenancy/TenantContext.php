<?php

declare(strict_types=1);

namespace Modules\Core\Tenancy;

/**
 * Ponto ÚNICO de acesso ao contexto de tenant (entity_id) do request/processo atual.
 *
 * Quem resolve o tenant é sempre a borda: o Middleware (HTTP) a partir da
 * credencial, um adapter de canal (MCP/CLI) a partir do token, e o Worker
 * restaurando via runAs() a partir do payload do job. Nenhum código de domínio
 * "descobre" o tenant por conta própria — ele pergunta aqui.
 *
 * É infraestrutura cross-module pura, sem regra de negócio — por isso vive no core.
 */
final class TenantContext
{
    public const SESSION_KEY = 'entity_id';

    /** entity_id do tenant atual, ou null em contexto sem tenant (CLI/seeder). */
    public static function id(): ?string
    {
        if (! app()->bound('session')) {
            return null;
        }

        return session()->get(self::SESSION_KEY);
    }

    public static function has(): bool
    {
        return self::id() !== null;
    }

    public static function set(string $entityId): void
    {
        session()->put(self::SESSION_KEY, $entityId);
    }

    public static function forget(): void
    {
        if (app()->bound('session')) {
            session()->forget(self::SESSION_KEY);
        }
    }

    /**
     * Executa o callback no contexto do tenant informado e restaura o anterior.
     * OBRIGATÓRIO em Jobs/Workers e em qualquer canal sem sessão HTTP — sem isso
     * o EntityScope não filtra e o multi-tenant vaza no primeiro find() esquecido.
     *
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    public static function runAs(string $entityId, callable $callback): mixed
    {
        $previous = self::id();
        self::set($entityId);

        try {
            return $callback();
        } finally {
            $previous === null ? self::forget() : self::set($previous);
        }
    }

    /**
     * Executa o callback SEM contexto de tenant (acesso administrativo deliberado),
     * restaurando o contexto anterior ao final.
     *
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    public static function withoutScope(callable $callback): mixed
    {
        $previous = self::id();
        self::forget();

        try {
            return $callback();
        } finally {
            if ($previous !== null) {
                self::set($previous);
            }
        }
    }
}
