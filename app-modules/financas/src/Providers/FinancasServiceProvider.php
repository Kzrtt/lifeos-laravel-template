<?php

declare(strict_types=1);

namespace Modules\Financas\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\ConsumidorDeVocabulario;
use Modules\Core\Contracts\McpTool;
use Modules\Financas\Mcp\Tools\CreateMovimentacaoTool;
use Modules\Financas\Mcp\Tools\ResumoFinanceiroTool;
use Modules\Financas\Mcp\Tools\SearchMovimentacoesTool;
use Modules\Financas\Mcp\Tools\UpdateMovimentacaoTool;
use Modules\Financas\Support\ConsumidorMovimentacoes;
use Modules\Financas\Support\ConsumidorRecorrencias;

class FinancasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([ConsumidorMovimentacoes::class, ConsumidorRecorrencias::class], ConsumidorDeVocabulario::TAG);
        $this->app->tag([ResumoFinanceiroTool::class, SearchMovimentacoesTool::class, CreateMovimentacaoTool::class, UpdateMovimentacaoTool::class], McpTool::TAG);
    }

    public function boot(): void {}
}
