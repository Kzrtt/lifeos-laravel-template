<?php

declare(strict_types=1);

namespace Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Tenancy\Database\Factories\EntityFactory;
use Modules\Tenancy\Observers\EntityObserver;

/**
 * O tenant. NÃO usa Entityable — é a própria raiz do multi-tenancy.
 *
 * O core nunca importa esta classe: quem precisa dela fora do módulo resolve por
 * `config('models.entity')` (ver config/models.php). Ao evoluir o sistema, os
 * campos do tenant (plano, documento, endereço...) crescem aqui.
 *
 * @property string $ent_id
 * @property string $ent_name
 * @property ?string $ent_email
 * @property string $ent_slug
 * @property ?string $ent_sub
 * @property ?string $ent_banner
 * @property ?string $ent_avatar
 * @property string $ent_tema
 * @property string $ent_hub_capa
 * @property bool $ent_blog_habilitado
 */
#[ObservedBy([EntityObserver::class])]
final class Entity extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'entities';

    protected $primaryKey = 'ent_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'ent_created_at';

    public const UPDATED_AT = 'ent_updated_at';

    public const DELETED_AT = 'ent_deleted_at';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['ent_blog_habilitado' => 'boolean'];
    }

    protected static function newFactory(): EntityFactory
    {
        return EntityFactory::new();
    }
}
