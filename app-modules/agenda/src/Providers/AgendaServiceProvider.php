<?php

declare(strict_types=1);

namespace Modules\Agenda\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Agenda\Listeners\RemoverProjetoDosEventos;
use Modules\Agenda\Mcp\Tools\CreateEventoTool;
use Modules\Agenda\Mcp\Tools\SearchEventosTool;
use Modules\Agenda\Mcp\Tools\UpdateEventoTool;
use Modules\Agenda\Support\ConsumidorAgenda;
use Modules\Core\Contracts\ConsumidorDeVocabulario;
use Modules\Core\Contracts\McpTool;
use Modules\Core\Events\ProjetoExcluido;

class AgendaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([ConsumidorAgenda::class], ConsumidorDeVocabulario::TAG);
        $this->app->tag([SearchEventosTool::class, CreateEventoTool::class, UpdateEventoTool::class], McpTool::TAG);
    }

    public function boot(): void
    {
        Event::listen(ProjetoExcluido::class, RemoverProjetoDosEventos::class);
    }
}
