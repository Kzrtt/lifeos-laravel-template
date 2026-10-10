<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Citacoes\Http\Controllers\CitacoesRpcController;

Route::middleware(['throttle:lifeos', 'lifeos.senha'])
    ->post('functions/v1/lifeos-citacoes', CitacoesRpcController::class);
