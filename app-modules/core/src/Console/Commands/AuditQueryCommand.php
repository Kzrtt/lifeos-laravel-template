<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Models\AuditLog;

/** Lista a trilha de auditoria de um registro: `php artisan audit:query orders <uuid>`. */
final class AuditQueryCommand extends Command
{
    protected $signature = 'audit:query {table : Nome da tabela auditada} {record_id : ID do registro}';

    protected $description = 'Lista a trilha de auditoria (audit_logs) de um registro';

    public function handle(): int
    {
        $logs = AuditLog::query()
            ->where('aud_table', $this->argument('table'))
            ->where('aud_record_id', $this->argument('record_id'))
            ->orderBy('aud_created_at')
            ->get();

        if ($logs->isEmpty()) {
            $this->warn('Nenhum registro de auditoria encontrado.');

            return self::SUCCESS;
        }

        $this->table(
            ['Quando', 'Ação', 'Usuário', 'Antes', 'Depois'],
            $logs->map(fn (AuditLog $log): array => [
                (string) $log->aud_created_at,
                $log->aud_action,
                $log->aud_user_id ?? '-',
                $this->summarize($log->aud_before),
                $this->summarize($log->aud_after),
            ])->all()
        );

        return self::SUCCESS;
    }

    /** @param  array<string, mixed>|null  $data */
    private function summarize(?array $data): string
    {
        if ($data === null) {
            return '-';
        }

        $json = (string) json_encode($data, JSON_UNESCAPED_UNICODE);

        return mb_strlen($json) > 60 ? mb_substr($json, 0, 57).'...' : $json;
    }
}
