<?php

declare(strict_types=1);

namespace Modules\Memoria\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\ConsumidorDeVocabulario;
use Modules\Core\Contracts\InstrucoesDoServidor;
use Modules\Core\Contracts\McpTool;
use Modules\Memoria\Mcp\Tools\AddRegistroTool;
use Modules\Memoria\Mcp\Tools\CreateMemoriaTool;
use Modules\Memoria\Mcp\Tools\GetMemoriaTool;
use Modules\Memoria\Mcp\Tools\ListMemoriasTool;
use Modules\Memoria\Mcp\Tools\UpdateMemoriaTool;
use Modules\Memoria\Mcp\Tools\UpdateRegistroTool;
use Modules\Memoria\Support\ConsumidorMemoria;
use Modules\Memoria\Support\InstrucoesMemoria;

class MemoriaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([ConsumidorMemoria::class], ConsumidorDeVocabulario::TAG);
        $this->app->tag([
            ListMemoriasTool::class, GetMemoriaTool::class, CreateMemoriaTool::class,
            AddRegistroTool::class, UpdateMemoriaTool::class, UpdateRegistroTool::class,
        ], McpTool::TAG);
        $this->app->bind(InstrucoesDoServidor::class, InstrucoesMemoria::class);
    }

    public function boot(): void {}
}
