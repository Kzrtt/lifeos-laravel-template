<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\StaticPageController;
use Modules\Financas\Http\Controllers\IngestController;
use Modules\Financas\Http\Controllers\MovimentacoesRpcController;
use Modules\Financas\Http\Controllers\RecorrenciasRpcController;

Route::middleware(['throttle:lifeos', 'lifeos.senha'])
    ->prefix('functions/v1')
    ->group(function (): void {
        Route::post('lifeos-movimentacoes', MovimentacoesRpcController::class);
        Route::post('lifeos-recorrencias', RecorrenciasRpcController::class);
    });

// O webhook leva a senha na URL (?token=): o atalho do iOS não guarda header.
// Limite mais apertado, por ser público.
Route::middleware(['throttle:lifeos-gate', 'lifeos.senha'])
    ->post('functions/v1/lifeos-ingest', IngestController::class);

foreach (['financas', 'automacao'] as $pagina) {
    Route::get("lifeos/{$pagina}.html", StaticPageController::class)
        ->defaults('file', "app-modules/financas/resources/pages/{$pagina}.html");
}
