<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Acesso\Http\Controllers\ConfigRpcController;
use Modules\Acesso\Http\Controllers\PageAccessController;
use Modules\Acesso\Http\Controllers\SenhasRpcController;
use Modules\Core\Http\StaticPageController;

/*
| Rotas de compatibilidade do contrato congelado: mesmos caminhos das Edge
| Functions de hoje, para que nenhuma tela mude. `lifeos.senha` (borda, em app/)
| resolve a entidade pela senha mestre; check_page_access é público e fica sob
| o limitador de tentativas.
*/
Route::middleware(['throttle:lifeos', 'lifeos.senha'])
    ->prefix('functions/v1')
    ->group(function (): void {
        Route::post('lifeos-senhas', SenhasRpcController::class);
        Route::post('lifeos-config', ConfigRpcController::class);
    });

Route::middleware(['throttle:lifeos-gate'])
    ->post('rest/v1/rpc/check_page_access', PageAccessController::class);

Route::get('lifeos/senhas.html', StaticPageController::class)
    ->defaults('file', 'app-modules/acesso/resources/pages/senhas.html');
