<?php

declare(strict_types=1);

namespace Modules\Citacoes\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Citacoes\Mcp\Tools\CreateCitacaoTool;
use Modules\Citacoes\Mcp\Tools\SearchCitacoesTool;
use Modules\Core\Contracts\McpTool;

class CitacoesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([SearchCitacoesTool::class, CreateCitacaoTool::class], McpTool::TAG);
    }

    public function boot(): void {}
}
