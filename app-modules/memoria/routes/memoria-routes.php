<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\StaticPageController;
use Modules\Memoria\Http\Controllers\MemoriasRpcController;

Route::middleware(['throttle:lifeos', 'lifeos.senha'])
    ->post('functions/v1/lifeos-memorias', MemoriasRpcController::class);

Route::get('lifeos/memoria.html', StaticPageController::class)
    ->defaults('file', 'app-modules/memoria/resources/pages/memoria.html');
