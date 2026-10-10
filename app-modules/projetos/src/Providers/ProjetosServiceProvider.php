<?php

declare(strict_types=1);

namespace Modules\Projetos\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\ConsumidorDeVocabulario;
use Modules\Core\Contracts\McpTool;
use Modules\Core\Contracts\ProjetoReadModel;
use Modules\Projetos\Mcp\Tools\CreateTarefaTool;
use Modules\Projetos\Mcp\Tools\SearchProjetosTool;
use Modules\Projetos\Mcp\Tools\SearchTarefasTool;
use Modules\Projetos\Mcp\Tools\UpdateTarefaTool;
use Modules\Projetos\ReadModels\EloquentProjetoReadModel;
use Modules\Projetos\Support\ConsumidorProjetos;

class ProjetosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProjetoReadModel::class, EloquentProjetoReadModel::class);
        $this->app->tag([ConsumidorProjetos::class], ConsumidorDeVocabulario::TAG);
        $this->app->tag([SearchTarefasTool::class, CreateTarefaTool::class, UpdateTarefaTool::class, SearchProjetosTool::class], McpTool::TAG);
    }

    public function boot(): void {}
}
