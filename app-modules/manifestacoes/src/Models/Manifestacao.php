<?php

declare(strict_types=1);

namespace Modules\Manifestacoes\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Casts\PgTextArray;
use Modules\Core\Models\Concerns\Entityable;
use Modules\Core\Models\Concerns\QueriesTextArrays;
use Modules\Manifestacoes\Observers\ManifestacaoObserver;

/**
 * @property string $man_id
 * @property string $man_name
 * @property string $man_status
 * @property list<string> $man_tags
 * @property ?string $man_banner_url
 * @property ?string $man_descricao
 */
#[ObservedBy([ManifestacaoObserver::class])]
final class Manifestacao extends Model
{
    use Entityable;
    use HasUuids;
    use QueriesTextArrays;
    use SoftDeletes;

    protected $table = 'lifeos_manifestacoes';

    protected $primaryKey = 'man_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'man_created_at';

    public const UPDATED_AT = 'man_updated_at';

    public const DELETED_AT = 'man_deleted_at';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['man_tags' => PgTextArray::class];
    }
}
