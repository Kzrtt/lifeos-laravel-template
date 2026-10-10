<?php

declare(strict_types=1);

namespace Modules\Manifestacoes\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\ConsumidorDeVocabulario;
use Modules\Core\Contracts\McpTool;
use Modules\Manifestacoes\Mcp\Tools\SearchManifestacoesTool;
use Modules\Manifestacoes\Support\ConsumidorManifestacoes;

class ManifestacoesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([ConsumidorManifestacoes::class], ConsumidorDeVocabulario::TAG);
        $this->app->tag([SearchManifestacoesTool::class], McpTool::TAG);
    }

    public function boot(): void {}
}
