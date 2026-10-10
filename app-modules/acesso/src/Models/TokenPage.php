<?php

declare(strict_types=1);

namespace Modules\Acesso\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Acesso\Observers\TokenPageObserver;
use Modules\Core\Models\Concerns\Entityable;

/**
 * Concessão de uma página (slug do manifest, o mesmo de gate.js) a uma senha.
 *
 * @property string $tpg_id
 * @property string $access_token_tok_id
 * @property string $tpg_page_slug
 */
#[ObservedBy([TokenPageObserver::class])]
final class TokenPage extends Model
{
    use Entityable;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'token_pages';

    protected $primaryKey = 'tpg_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'tpg_created_at';

    public const UPDATED_AT = 'tpg_updated_at';

    public const DELETED_AT = 'tpg_deleted_at';

    protected $guarded = [];

    /** @return BelongsTo<AccessToken, $this> */
    public function token(): BelongsTo
    {
        return $this->belongsTo(AccessToken::class, 'access_token_tok_id', 'tok_id');
    }
}
