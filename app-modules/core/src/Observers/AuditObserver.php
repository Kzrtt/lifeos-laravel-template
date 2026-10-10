<?php

declare(strict_types=1);

namespace Modules\Core\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\AuditLog;
use Modules\Core\Scopes\EntityScope;
use Modules\Core\Tenancy\TenantContext;
use Throwable;

/**
 * Observer base de auditoria. Cada model transacional registra um observer
 * concreto que estende esta classe (ex.: OrderObserver no módulo orders) e
 * declara em $hidden as colunas sensíveis que NÃO podem entrar no diff.
 *
 * Invariantes:
 *  - aud_before é null em created; aud_after é null em deleted.
 *  - falha ao gravar a auditoria NUNCA aborta a operação principal (best-effort).
 *  - audit_logs é append-only: nada aqui (nem em lugar nenhum) faz update/delete.
 */
abstract class AuditObserver
{
    /**
     * Colunas sensíveis excluídas do diff (sobrescrever no observer concreto).
     *
     * @var list<string>
     */
    protected array $hidden = [];

    public function created(Model $model): void
    {
        $this->log('created', $model, null, $this->scrub($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();

        if ($model->usesTimestamps() && $model->getUpdatedAtColumn() !== null) {
            unset($changes[$model->getUpdatedAtColumn()]);
        }

        if ($changes === []) {
            return;
        }

        $before = [];
        foreach (array_keys($changes) as $key) {
            $before[$key] = $model->getOriginal($key);
        }

        $this->log('updated', $model, $this->scrub($before), $this->scrub($changes));
    }

    public function deleted(Model $model): void
    {
        $this->log('deleted', $model, $this->scrub($model->getAttributes()), null);
    }

    public function restored(Model $model): void
    {
        $this->log('restored', $model, null, $this->scrub($model->getAttributes()));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function scrub(array $attributes): array
    {
        foreach ($this->hidden as $key) {
            unset($attributes[$key]);
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    protected function log(string $action, Model $model, ?array $before, ?array $after): void
    {
        try {
            AuditLog::create([
                'entity_ent_id' => $this->resolveEntityId($model),
                'aud_user_id' => Auth::id() !== null ? (string) Auth::id() : null,
                'aud_table' => $model->getTable(),
                'aud_record_id' => (string) $model->getKey(),
                'aud_action' => $action,
                'aud_before' => $before,
                'aud_after' => $after,
            ]);
        } catch (Throwable $e) {
            // Auditoria é best-effort: nunca derruba a transação de negócio.
            Log::error('Falha ao gravar audit_log', [
                'tabela' => $model->getTable(),
                'acao' => $action,
                'erro' => $e->getMessage(),
            ]);
        }
    }

    protected function resolveEntityId(Model $model): ?string
    {
        return $model->getAttribute(EntityScope::COLUMN) ?? TenantContext::id();
    }
}
