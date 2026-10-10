<?php

declare(strict_types=1);

namespace Modules\Projetos\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Casts\PgTextArray;
use Modules\Core\Models\Concerns\Entityable;
use Modules\Core\Models\Concerns\QueriesTextArrays;
use Modules\Projetos\Database\Factories\ProjetoFactory;
use Modules\Projetos\Observers\ProjetoObserver;

/**
 * @property string $prj_id
 * @property string $prj_name
 * @property ?string $prj_emoji
 * @property string $prj_status
 * @property list<string> $prj_tags
 */
#[ObservedBy([ProjetoObserver::class])]
final class Projeto extends Model
{
    use Entityable;
    use HasFactory;
    use HasUuids;
    use QueriesTextArrays;
    use SoftDeletes;

    protected $table = 'lifeos_projetos';

    protected $primaryKey = 'prj_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'prj_created_at';

    public const UPDATED_AT = 'prj_updated_at';

    public const DELETED_AT = 'prj_deleted_at';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['prj_tags' => PgTextArray::class];
    }

    /** @return HasMany<Tarefa, $this> */
    public function tarefas(): HasMany
    {
        return $this->hasMany(Tarefa::class, 'projeto_prj_id', 'prj_id');
    }

    protected static function newFactory(): ProjetoFactory
    {
        return ProjetoFactory::new();
    }
}
