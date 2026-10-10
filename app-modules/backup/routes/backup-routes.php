<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Backup\Http\Controllers\BackupRpcController;

Route::middleware(['throttle:lifeos', 'lifeos.senha'])
    ->post('functions/v1/lifeos-backup', BackupRpcController::class);
