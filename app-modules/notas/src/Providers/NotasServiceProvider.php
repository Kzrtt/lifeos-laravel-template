<?php

declare(strict_types=1);

namespace Modules\Notas\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\ConsumidorDeVocabulario;
use Modules\Core\Contracts\McpTool;
use Modules\Core\Events\ProjetoExcluido;
use Modules\Notas\Listeners\DesvincularProjetoExcluido;
use Modules\Notas\Mcp\Tools\CreateNotaTool;
use Modules\Notas\Mcp\Tools\SearchNotasTool;
use Modules\Notas\Mcp\Tools\UpdateNotaTool;
use Modules\Notas\Support\ConsumidorNotas;

class NotasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([ConsumidorNotas::class], ConsumidorDeVocabulario::TAG);
        $this->app->tag([SearchNotasTool::class, CreateNotaTool::class, UpdateNotaTool::class], McpTool::TAG);
    }

    public function boot(): void
    {
        Event::listen(ProjetoExcluido::class, DesvincularProjetoExcluido::class);
    }
}
