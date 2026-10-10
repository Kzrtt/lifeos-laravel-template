<?php

declare(strict_types=1);

namespace Modules\Financas\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\Concerns\Entityable;
use Modules\Financas\Database\Factories\RecorrenciaFactory;
use Modules\Financas\Observers\RecorrenciaObserver;

/**
 * @property string $rec_id
 * @property string $rec_nome
 * @property string $rec_direcao
 * @property string $rec_valor_min
 * @property string $rec_valor_max
 * @property ?string $rec_meio
 * @property ?int $rec_dia
 * @property bool $rec_ativa
 */
#[ObservedBy([RecorrenciaObserver::class])]
final class Recorrencia extends Model
{
    use Entityable;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'lifeos_recorrencias';

    protected $primaryKey = 'rec_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'rec_created_at';

    public const UPDATED_AT = 'rec_updated_at';

    public const DELETED_AT = 'rec_deleted_at';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['rec_valor_min' => 'decimal:2', 'rec_valor_max' => 'decimal:2', 'rec_dia' => 'integer', 'rec_ativa' => 'boolean'];
    }

    protected static function newFactory(): RecorrenciaFactory
    {
        return RecorrenciaFactory::new();
    }
}
