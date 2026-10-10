<?php

declare(strict_types=1);

namespace Modules\Financas\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Casts\PgTextArray;
use Modules\Core\Models\Concerns\Entityable;
use Modules\Core\Models\Concerns\QueriesTextArrays;
use Modules\Financas\Database\Factories\MovimentacaoFactory;
use Modules\Financas\Observers\MovimentacaoObserver;

/**
 * @property string $mov_id
 * @property string $mov_name
 * @property string $mov_valor
 * @property \Illuminate\Support\Carbon $mov_date
 * @property list<string> $mov_tipo
 */
#[ObservedBy([MovimentacaoObserver::class])]
final class Movimentacao extends Model
{
    use Entityable;
    use HasFactory;
    use HasUuids;
    use QueriesTextArrays;
    use SoftDeletes;

    protected $table = 'lifeos_movimentacoes';

    protected $primaryKey = 'mov_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'mov_created_at';

    public const UPDATED_AT = 'mov_updated_at';

    public const DELETED_AT = 'mov_deleted_at';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['mov_valor' => 'decimal:2', 'mov_date' => 'date', 'mov_tipo' => PgTextArray::class];
    }

    protected static function newFactory(): MovimentacaoFactory
    {
        return MovimentacaoFactory::new();
    }
}
