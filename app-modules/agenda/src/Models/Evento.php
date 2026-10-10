<?php

declare(strict_types=1);

namespace Modules\Agenda\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Agenda\Database\Factories\EventoFactory;
use Modules\Agenda\Observers\EventoObserver;
use Modules\Core\Models\Concerns\Entityable;

/**
 * @property string $evt_id
 * @property ?string $projeto_prj_id
 * @property string $evt_name
 * @property \Illuminate\Support\Carbon $evt_date
 * @property ?\Illuminate\Support\Carbon $evt_date_fim
 * @property string $evt_tipo
 */
#[ObservedBy([EventoObserver::class])]
final class Evento extends Model
{
    use Entityable;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'lifeos_eventos';

    protected $primaryKey = 'evt_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'evt_created_at';

    public const UPDATED_AT = 'evt_updated_at';

    public const DELETED_AT = 'evt_deleted_at';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['evt_date' => 'date', 'evt_date_fim' => 'date'];
    }

    protected static function newFactory(): EventoFactory
    {
        return EventoFactory::new();
    }
}
