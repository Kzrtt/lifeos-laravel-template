<?php

declare(strict_types=1);

namespace Modules\Notas\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Casts\PgTextArray;
use Modules\Core\Models\Concerns\Entityable;
use Modules\Core\Models\Concerns\QueriesTextArrays;
use Modules\Notas\Database\Factories\NotaFactory;
use Modules\Notas\Observers\NotaObserver;

/**
 * @property string $not_id
 * @property string $not_name
 * @property list<string> $not_tipo
 * @property ?\Illuminate\Support\Carbon $not_data
 * @property ?string $not_conteudo_md
 */
#[ObservedBy([NotaObserver::class])]
final class Nota extends Model
{
    use Entityable;
    use HasFactory;
    use HasUuids;
    use QueriesTextArrays;
    use SoftDeletes;

    protected $table = 'lifeos_notas';

    protected $primaryKey = 'not_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'not_created_at';

    public const UPDATED_AT = 'not_updated_at';

    public const DELETED_AT = 'not_deleted_at';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['not_tipo' => PgTextArray::class, 'not_data' => 'date'];
    }

    /** @return HasMany<NotaProjeto, $this> */
    public function vinculos(): HasMany
    {
        return $this->hasMany(NotaProjeto::class, 'nota_not_id', 'not_id');
    }

    protected static function newFactory(): NotaFactory
    {
        return NotaFactory::new();
    }
}
