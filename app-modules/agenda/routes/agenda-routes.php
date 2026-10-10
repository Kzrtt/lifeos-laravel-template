<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Agenda\Http\Controllers\EventosRpcController;

Route::middleware(['throttle:lifeos', 'lifeos.senha'])
    ->post('functions/v1/lifeos-eventos', EventosRpcController::class);
