<?php

declare(strict_types=1);

namespace Modules\Notas\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Concerns\Entityable;
use Modules\Notas\Observers\NotaProjetoObserver;

/**
 * Vínculo nota <-> projeto. Sem SoftDeletes de propósito: é relação, não
 * conteúdo; criar e desfazer vínculos fica na auditoria.
 *
 * @property string $npj_id
 * @property string $nota_not_id
 * @property string $projeto_prj_id
 */
#[ObservedBy([NotaProjetoObserver::class])]
final class NotaProjeto extends Model
{
    use Entityable;
    use HasUuids;

    protected $table = 'lifeos_notas_projetos';

    protected $primaryKey = 'npj_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'npj_created_at';

    public const UPDATED_AT = 'npj_updated_at';

    protected $guarded = [];
}
