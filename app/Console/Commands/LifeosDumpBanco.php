<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * `pg_dump` do banco INTEIRO (todas as instâncias) para `storage/app/backups`, com
 * rotação. É a cópia que salva a VPS; o .zip do hub (lifeos-backup) é a que o usuário
 * controla. O agendador roda isto todo dia; copiar o arquivo para FORA da máquina
 * (rclone/rsync/S3) é responsabilidade do deploy — um backup só na mesma VPS não é backup.
 */
final class LifeosDumpBanco extends Command
{
    protected $signature = 'lifeos:dump-banco {--manter=14 : quantos dumps guardar}';

    protected $description = 'Gera um pg_dump comprimido do banco inteiro e apaga os mais antigos.';

    public function handle(): int
    {
        $c = config('database.connections.'.config('database.default'));
        $pasta = storage_path('app/backups');
        File::ensureDirectoryExists($pasta);
        $arquivo = $pasta.'/lifeos-'.now()->format('Ymd-His').'.sql.gz';

        $resultado = Process::env(['PGPASSWORD' => (string) ($c['password'] ?? '')])->run([
            'pg_dump', '--no-owner', '--no-privileges',
            '--host', (string) $c['host'], '--port', (string) $c['port'],
            '--username', (string) $c['username'], '--dbname', (string) $c['database'],
            '--file', $arquivo.'.tmp',
        ]);

        if (! $resultado->successful()) {
            File::delete($arquivo.'.tmp');
            $this->error('pg_dump falhou: '.trim($resultado->errorOutput()));

            return self::FAILURE;
        }

        file_put_contents($arquivo, gzencode((string) file_get_contents($arquivo.'.tmp'), 9));
        File::delete($arquivo.'.tmp');

        $antigos = collect(File::glob($pasta.'/lifeos-*.sql.gz'))->sort()->values();
        $antigos->slice(0, max(0, $antigos->count() - (int) $this->option('manter')))->each(fn (string $f) => File::delete($f));

        $this->info('Dump gerado: '.basename($arquivo).' ('.number_format((int) filesize($arquivo) / 1024, 1).' KB).');

        return self::SUCCESS;
    }
}
