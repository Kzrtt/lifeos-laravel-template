<?php

declare(strict_types=1);

namespace Modules\Memoria\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\Concerns\Entityable;
use Modules\Memoria\Database\Factories\MemoriaFactory;
use Modules\Memoria\Observers\MemoriaObserver;

/**
 * @property string $mem_id
 * @property string $mem_titulo
 * @property string $mem_descricao
 * @property string $mem_categoria
 */
#[ObservedBy([MemoriaObserver::class])]
final class Memoria extends Model
{
    use Entityable;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'lifeos_memorias';

    protected $primaryKey = 'mem_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'mem_created_at';

    public const UPDATED_AT = 'mem_updated_at';

    public const DELETED_AT = 'mem_deleted_at';

    protected $guarded = [];

    /** @return HasMany<MemoriaRegistro, $this> */
    public function registros(): HasMany
    {
        return $this->hasMany(MemoriaRegistro::class, 'memoria_mem_id', 'mem_id')->orderBy('mrg_created_at');
    }

    protected static function newFactory(): MemoriaFactory
    {
        return MemoriaFactory::new();
    }
}
