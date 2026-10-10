<?php

declare(strict_types=1);

namespace Modules\Vocabularios\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Vocabularios\Services\VocabularioService;

class VocabulariosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VocabularioService::class);
        $this->app->bind(VocabularioReadModel::class, VocabularioService::class);
    }

    public function boot(): void {}
}
