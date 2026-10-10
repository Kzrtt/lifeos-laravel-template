<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Manifestacoes\Http\Controllers\ManifestacoesRpcController;

Route::middleware(['throttle:lifeos', 'lifeos.senha'])
    ->post('functions/v1/lifeos-manifestacoes', ManifestacoesRpcController::class);
