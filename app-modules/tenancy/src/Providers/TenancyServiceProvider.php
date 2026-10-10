<?php

declare(strict_types=1);

namespace Modules\Tenancy\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\EntityDirectory;
use Modules\Tenancy\ReadModels\EloquentEntityDirectory;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EntityDirectory::class, EloquentEntityDirectory::class);
    }

    public function boot(): void {}
}
