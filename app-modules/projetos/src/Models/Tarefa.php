<?php

declare(strict_types=1);

namespace Modules\Projetos\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Casts\PgTextArray;
use Modules\Core\Models\Concerns\Entityable;
use Modules\Core\Models\Concerns\QueriesTextArrays;
use Modules\Projetos\Database\Factories\TarefaFactory;
use Modules\Projetos\Observers\TarefaObserver;

/**
 * @property string $tar_id
 * @property string $projeto_prj_id
 * @property string $tar_name
 * @property string $tar_status
 * @property list<string> $tar_tipo
 * @property ?\Illuminate\Support\Carbon $tar_data_entrega
 * @property ?string $tar_descricao
 */
#[ObservedBy([TarefaObserver::class])]
final class Tarefa extends Model
{
    use Entityable;
    use HasFactory;
    use HasUuids;
    use QueriesTextArrays;
    use SoftDeletes;

    protected $table = 'lifeos_tarefas';

    protected $primaryKey = 'tar_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'tar_created_at';

    public const UPDATED_AT = 'tar_updated_at';

    public const DELETED_AT = 'tar_deleted_at';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['tar_tipo' => PgTextArray::class, 'tar_data_entrega' => 'date'];
    }

    /** @return BelongsTo<Projeto, $this> */
    public function projeto(): BelongsTo
    {
        return $this->belongsTo(Projeto::class, 'projeto_prj_id', 'prj_id');
    }

    protected static function newFactory(): TarefaFactory
    {
        return TarefaFactory::new();
    }
}
