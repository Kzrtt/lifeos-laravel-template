<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\StaticPageController;
use Modules\Projetos\Http\Controllers\ProjetosRpcController;
use Modules\Projetos\Http\Controllers\TarefasRpcController;

Route::middleware(['throttle:lifeos', 'lifeos.senha'])
    ->prefix('functions/v1')
    ->group(function (): void {
        Route::post('lifeos-projetos', ProjetosRpcController::class);
        Route::post('lifeos-tarefas', TarefasRpcController::class);
    });

Route::get('lifeos/tarefas.html', StaticPageController::class)
    ->defaults('file', 'app-modules/projetos/resources/pages/tarefas.html');
