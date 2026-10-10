<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

it('gera o dump comprimido e mantém só os N mais recentes', function () {
    $pasta = storage_path('app/backups');
    File::deleteDirectory($pasta);
    File::ensureDirectoryExists($pasta);
    foreach (['20260101-000000', '20260102-000000', '20260103-000000'] as $velho) {
        file_put_contents("{$pasta}/lifeos-{$velho}.sql.gz", 'x');
    }

    Process::fake(function ($processo) {
        $args = $processo->command;
        file_put_contents($args[array_search('--file', $args, true) + 1], "-- dump falso\n");

        return Process::result('');
    });

    $this->artisan('lifeos:dump-banco', ['--manter' => 2])->expectsOutputToContain('Dump gerado: lifeos-')->assertSuccessful();

    $restantes = collect(File::glob("{$pasta}/lifeos-*.sql.gz"))->map(fn ($f) => basename($f))->sort()->values();
    expect($restantes)->toHaveCount(2)->and($restantes->first())->toBe('lifeos-20260103-000000.sql.gz')
        ->and(gzdecode((string) file_get_contents("{$pasta}/".$restantes->last())))->toBe("-- dump falso\n");

    File::deleteDirectory($pasta);
});

it('falha com a mensagem do pg_dump e não deixa arquivo pela metade', function () {
    $pasta = storage_path('app/backups');
    File::deleteDirectory($pasta);
    Process::fake(fn () => Process::result('', 'connection refused', 1));

    $this->artisan('lifeos:dump-banco')->expectsOutputToContain('pg_dump falhou: connection refused')->assertFailed();

    expect(File::glob("{$pasta}/*"))->toBe([]);
});
