<?php

declare(strict_types=1);

namespace Modules\Citacoes\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Citacoes\Database\Factories\CitacaoFactory;
use Modules\Citacoes\Observers\CitacaoObserver;
use Modules\Core\Models\Concerns\Entityable;

/**
 * Uma citação: só o texto e quem disse. O hub sorteia uma a cada abertura.
 *
 * @property string $cit_id
 * @property string $entity_ent_id
 * @property string $cit_texto
 * @property string $cit_autor
 */
#[ObservedBy([CitacaoObserver::class])]
final class Citacao extends Model
{
    use Entityable;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'lifeos_citacoes';

    protected $primaryKey = 'cit_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'cit_created_at';

    public const UPDATED_AT = 'cit_updated_at';

    public const DELETED_AT = 'cit_deleted_at';

    protected $guarded = [];

    protected static function newFactory(): CitacaoFactory
    {
        return CitacaoFactory::new();
    }
}
