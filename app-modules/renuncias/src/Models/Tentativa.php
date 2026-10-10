<?php

declare(strict_types=1);

namespace Modules\Renuncias\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\Concerns\Entityable;
use Modules\Renuncias\Observers\TentativaObserver;

/**
 * Uma tentativa encerrada por recaída: de `rtn_inicio` até `rtn_fim`.
 *
 * @property string $rtn_id
 * @property string $renuncia_ren_id
 * @property \Illuminate\Support\Carbon $rtn_inicio
 * @property \Illuminate\Support\Carbon $rtn_fim
 */
#[ObservedBy([TentativaObserver::class])]
final class Tentativa extends Model
{
    use Entityable;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'lifeos_renuncia_tentativas';

    protected $primaryKey = 'rtn_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'rtn_created_at';

    public const UPDATED_AT = null;

    public const DELETED_AT = 'rtn_deleted_at';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['rtn_inicio' => 'datetime', 'rtn_fim' => 'datetime'];
    }
}
