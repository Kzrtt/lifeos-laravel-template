<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Scopes\EntityScope;
use Modules\Core\Tenancy\TenantContext;

/**
 * Torna um Model multi-tenant de forma declarativa: aplica o EntityScope (filtro
 * automático por tenant na leitura) e preenche entity_ent_id na criação.
 *
 * Use em todo model com escopo de tenant: `use Entityable;`. Models de dado
 * mestre global e o próprio tenant (Entity) NÃO usam.
 *
 * Comportamento ao criar sem contexto de tenant: o entity_ent_id NÃO é
 * preenchido automaticamente — deve ser informado explicitamente (seeders/CLI).
 * Como a coluna é NOT NULL, criar sem contexto e sem valor falha no banco, o que
 * impede por construção a criação de registro com tenant indefinido.
 */
trait Entityable
{
    public static function bootEntityable(): void
    {
        static::addGlobalScope(new EntityScope);

        static::creating(function ($model): void {
            $column = EntityScope::COLUMN;

            if (empty($model->{$column}) && TenantContext::has()) {
                $model->{$column} = TenantContext::id();
            }
        });
    }

    /** Relação com o tenant dono do registro. */
    public function entity(): BelongsTo
    {
        // Model do tenant resolvido por config para não acoplar o core ao tenancy.
        return $this->belongsTo(config('models.entity'), EntityScope::COLUMN, 'ent_id');
    }

    public function getQualifiedEntityColumn(): string
    {
        return $this->getTable().'.'.EntityScope::COLUMN;
    }

    /**
     * Atalho para executar uma query/bloco sem o filtro de tenant
     * (acesso administrativo). Ver TenantContext::withoutScope().
     *
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    public static function withoutEntityScope(callable $callback): mixed
    {
        return TenantContext::withoutScope($callback);
    }
}
