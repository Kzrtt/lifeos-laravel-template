<?php

declare(strict_types=1);

namespace Modules\Acesso\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Acesso\Observers\AccessTokenObserver;
use Modules\Core\Models\Concerns\Entityable;

/**
 * Uma senha de acesso. Mestre abre o LifeOS inteiro e qualquer página; as demais
 * abrem só as páginas concedidas em token_pages. O valor em claro não existe
 * aqui: só o hash, a máscara e o tamanho (ver TokenService).
 *
 * @property string $tok_id
 * @property string $entity_ent_id
 * @property string $tok_hash
 * @property string $tok_hint
 * @property int $tok_len
 * @property bool $tok_is_master
 * @property ?string $tok_label
 */
#[ObservedBy([AccessTokenObserver::class])]
final class AccessToken extends Model
{
    use Entityable;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'access_tokens';

    protected $primaryKey = 'tok_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'tok_created_at';

    public const UPDATED_AT = 'tok_updated_at';

    public const DELETED_AT = 'tok_deleted_at';

    protected $guarded = [];

    protected $hidden = ['tok_hash'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['tok_is_master' => 'boolean', 'tok_len' => 'integer'];
    }

    /** @return HasMany<TokenPage, $this> */
    public function pages(): HasMany
    {
        return $this->hasMany(TokenPage::class, 'access_token_tok_id', 'tok_id');
    }
}
