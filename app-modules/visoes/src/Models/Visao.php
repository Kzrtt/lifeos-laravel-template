<?php

declare(strict_types=1);

namespace Modules\Visoes\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\Concerns\Entityable;
use Modules\Visoes\Observers\VisaoObserver;

/**
 * @property string $vis_id
 * @property string $vis_tabela
 * @property string $vis_nome
 * @property string $vis_modo
 * @property list<array{campo: string, operador: string, valores: list<string>}> $vis_regras
 * @property int $vis_ordem
 * @property bool $vis_padrao
 */
#[ObservedBy([VisaoObserver::class])]
final class Visao extends Model
{
    use Entityable;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'lifeos_views';

    protected $primaryKey = 'vis_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'vis_created_at';

    public const UPDATED_AT = 'vis_updated_at';

    public const DELETED_AT = 'vis_deleted_at';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['vis_regras' => 'array', 'vis_ordem' => 'integer', 'vis_padrao' => 'boolean'];
    }
}
