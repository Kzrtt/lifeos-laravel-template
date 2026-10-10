<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\StaticPageController;
use Modules\Vocabularios\Http\Controllers\VocabulariosRpcController;

Route::middleware(['throttle:lifeos', 'lifeos.senha'])
    ->post('functions/v1/lifeos-vocabularios', VocabulariosRpcController::class);

Route::get('lifeos/tags.html', StaticPageController::class)
    ->defaults('file', 'app-modules/vocabularios/resources/pages/tags.html');
