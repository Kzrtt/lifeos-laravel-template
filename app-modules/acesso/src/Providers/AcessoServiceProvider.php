<?php

declare(strict_types=1);

namespace Modules\Acesso\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Acesso\Services\GateDePaginas;
use Modules\Acesso\Services\SegredoService;
use Modules\Acesso\Services\TokenService;
use Modules\Core\Contracts\AcessoAPagina;
use Modules\Core\Contracts\McpTokenResolver;

class AcessoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TokenService::class);
        $this->app->singleton(SegredoService::class);
        $this->app->bind(McpTokenResolver::class, SegredoService::class);
        $this->app->bind(AcessoAPagina::class, GateDePaginas::class);
    }

    public function boot(): void {}
}
