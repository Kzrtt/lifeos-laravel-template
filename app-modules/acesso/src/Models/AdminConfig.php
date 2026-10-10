<?php

declare(strict_types=1);

namespace Modules\Acesso\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Acesso\Observers\AdminConfigObserver;
use Modules\Core\Models\Concerns\Entityable;

/**
 * Segredo chave/valor da instância (hoje `github_pat` e `mcp_token`). O valor é
 * cifrado com Crypt — não pode ser hash: mcp.html precisa mostrar a URL inteira.
 *
 * @property string $cfg_id
 * @property string $cfg_key
 * @property string $cfg_value
 */
#[ObservedBy([AdminConfigObserver::class])]
final class AdminConfig extends Model
{
    use Entityable;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'admin_configs';

    protected $primaryKey = 'cfg_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'cfg_created_at';

    public const UPDATED_AT = 'cfg_updated_at';

    public const DELETED_AT = 'cfg_deleted_at';

    protected $guarded = [];

    protected $hidden = ['cfg_value'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['cfg_value' => 'encrypted'];
    }
}
