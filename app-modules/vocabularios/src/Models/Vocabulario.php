<?php

declare(strict_types=1);

namespace Modules\Vocabularios\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\Concerns\Entityable;
use Modules\Vocabularios\Observers\VocabularioObserver;

/**
 * @property string $voc_id
 * @property string $voc_dominio
 * @property string $voc_valor
 * @property ?string $voc_cor
 * @property int $voc_ordem
 * @property bool $voc_protegido
 */
#[ObservedBy([VocabularioObserver::class])]
final class Vocabulario extends Model
{
    use Entityable;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'lifeos_vocabularios';

    protected $primaryKey = 'voc_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'voc_created_at';

    public const UPDATED_AT = 'voc_updated_at';

    public const DELETED_AT = 'voc_deleted_at';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['voc_ordem' => 'integer', 'voc_protegido' => 'boolean'];
    }
}
