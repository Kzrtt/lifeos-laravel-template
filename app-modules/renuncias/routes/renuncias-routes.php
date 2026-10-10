<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\StaticPageController;
use Modules\Renuncias\Http\Controllers\RenunciasRpcController;

Route::middleware(['throttle:lifeos', 'lifeos.senha'])
    ->post('functions/v1/lifeos-renuncias', RenunciasRpcController::class);

Route::get('lifeos/renuncias.html', StaticPageController::class)
    ->defaults('file', 'app-modules/renuncias/resources/pages/renuncias.html');
