<?php

declare(strict_types=1);

namespace Modules\Renuncias\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\Concerns\Entityable;
use Modules\Renuncias\Database\Factories\RenunciaFactory;
use Modules\Renuncias\Observers\RenunciaObserver;

/**
 * @property string $ren_id
 * @property string $ren_nome
 * @property string $ren_emoji
 * @property \Illuminate\Support\Carbon $ren_desde
 * @property bool $ren_arquivada
 */
#[ObservedBy([RenunciaObserver::class])]
final class Renuncia extends Model
{
    use Entityable;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'lifeos_renuncias';

    protected $primaryKey = 'ren_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'ren_created_at';

    public const UPDATED_AT = 'ren_updated_at';

    public const DELETED_AT = 'ren_deleted_at';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['ren_desde' => 'datetime', 'ren_arquivada' => 'boolean'];
    }

    /** @return HasMany<Tentativa, $this> */
    public function tentativas(): HasMany
    {
        return $this->hasMany(Tentativa::class, 'renuncia_ren_id', 'ren_id')->orderByDesc('rtn_fim');
    }

    protected static function newFactory(): RenunciaFactory
    {
        return RenunciaFactory::new();
    }
}
