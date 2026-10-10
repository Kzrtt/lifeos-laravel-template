<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Visoes\Http\Controllers\VisoesRpcController;

Route::middleware(['throttle:lifeos', 'lifeos.senha'])
    ->post('functions/v1/lifeos-views', VisoesRpcController::class);
