<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\StaticPageController;
use Modules\Mcp\Http\Controllers\McpController;

/*
| O mesmo endereço de hoje: /functions/v1/lifeos-mcp/<TOKEN>. O token vai no PATH
| (o claude.ai não expõe header estático para conector pessoal). `lifeos.mcp`
| (borda, em app/) resolve a instância pelo token ANTES de ler o corpo —
| tools/list incluso fica atrás do gate.
*/
Route::middleware(['throttle:lifeos', 'lifeos.mcp'])
    ->post('functions/v1/lifeos-mcp/{token?}', McpController::class);

Route::get('lifeos/mcp.html', StaticPageController::class)
    ->defaults('file', 'app-modules/mcp/resources/pages/mcp.html');
