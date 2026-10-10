<?php

declare(strict_types=1);

namespace Modules\Memoria\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\Concerns\Entityable;
use Modules\Memoria\Observers\MemoriaRegistroObserver;

/**
 * @property string $mrg_id
 * @property string $memoria_mem_id
 * @property string $mrg_texto
 * @property ?string $mrg_origem
 */
#[ObservedBy([MemoriaRegistroObserver::class])]
final class MemoriaRegistro extends Model
{
    use Entityable;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'lifeos_memoria_registros';

    protected $primaryKey = 'mrg_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'mrg_created_at';

    public const UPDATED_AT = 'mrg_updated_at';

    public const DELETED_AT = 'mrg_deleted_at';

    protected $guarded = [];

    /** @return BelongsTo<Memoria, $this> */
    public function memoria(): BelongsTo
    {
        return $this->belongsTo(Memoria::class, 'memoria_mem_id', 'mem_id');
    }
}
