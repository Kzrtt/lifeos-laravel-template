<?php

declare(strict_types=1);

namespace Modules\Renuncias\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\McpTool;
use Modules\Renuncias\Mcp\Tools\SearchRenunciasTool;

class RenunciasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([SearchRenunciasTool::class], McpTool::TAG);
    }

    public function boot(): void {}
}
