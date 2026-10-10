<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Trilha de auditoria imutável (append-only). NÃO usa Entityable: a auditoria
 * registra mutações de TODOS os tenants e não deve ser filtrada por escopo.
 * Gravada pelo AuditObserver; nunca sofre update/delete.
 *
 * `aud_user_id` é string (não FK tipada) de propósito: tolera qualquer tipo de
 * PK do model de usuário do host (int autoincrement do Laravel padrão ou UUID).
 *
 * @property string $aud_id
 * @property ?string $entity_ent_id
 * @property ?string $aud_user_id
 * @property string $aud_table
 * @property string $aud_record_id
 * @property string $aud_action
 * @property ?array $aud_before
 * @property ?array $aud_after
 */
final class AuditLog extends Model
{
    use HasUuids;

    protected $table = 'audit_logs';

    protected $primaryKey = 'aud_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'aud_created_at';

    public const UPDATED_AT = null; // imutável: sem updated_at

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'aud_before' => 'array',
            'aud_after' => 'array',
            'aud_created_at' => 'datetime',
        ];
    }
}
