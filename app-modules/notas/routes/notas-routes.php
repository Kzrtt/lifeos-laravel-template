<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\StaticPageController;
use Modules\Notas\Http\Controllers\NotasRpcController;

Route::middleware(['throttle:lifeos', 'lifeos.senha'])
    ->post('functions/v1/lifeos-notas', NotasRpcController::class);

Route::get('lifeos/notas.html', StaticPageController::class)
    ->defaults('file', 'app-modules/notas/resources/pages/notas.html');
