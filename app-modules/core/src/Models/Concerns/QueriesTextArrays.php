<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Casts\PgTextArray;

/**
 * Filtros de coluna `text[]` do Postgres. O LifeOS filtra por "QUALQUER UM dos
 * valores" (`&&`) e por pertencimento (`= any`); Eloquent não tem isso nativo.
 * É condição de consulta, não regra de negócio.
 */
trait QueriesTextArrays
{
    /** @param Builder<static> $query */
    public function scopeWhereArrayHas(Builder $query, string $column, string $value): void
    {
        $query->whereRaw('? = any('.$query->getModel()->qualifyColumn($column).')', [$value]);
    }

    /**
     * @param  Builder<static>  $query
     * @param  list<string>  $values
     */
    public function scopeWhereArrayOverlaps(Builder $query, string $column, array $values): void
    {
        $query->whereRaw($query->getModel()->qualifyColumn($column).' && ?::text[]', [PgTextArray::literal($values)]);
    }
}
