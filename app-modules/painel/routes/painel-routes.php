<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\StaticPageController;
use Modules\Painel\Http\Controllers\DocsSiteController;
use Modules\Painel\Http\Controllers\LifeosConfigScriptController;

/*
| O front é HTML estático: o Laravel serve os arquivos, não os gera. Cada tela é
| servida CRUA (StaticPageController) — não é view Blade, de propósito: as
| páginas têm `{{` e `@` no JS/CSS, e servir os bytes intactos permite provar
| "front byte a byte" contra o repositório antigo. A rota preserva o `.html`.
*/
foreach (['lifeos', 'index', 'tutorial', 'temas'] as $pagina) {
    Route::get("lifeos/{$pagina}.html", StaticPageController::class)
        ->defaults('file', "app-modules/painel/resources/pages/{$pagina}.html");
}

Route::redirect('/', '/lifeos/index.html');
Route::redirect('index.html', '/lifeos/index.html');
Route::redirect('lifeos', '/lifeos/index.html');

Route::get('assets/js/lifeos-config.js', LifeosConfigScriptController::class);

// docs-site/: documentação estática pública (regras de negócio, DER, contrato).
Route::redirect('docs-site', '/docs-site/index.html');
Route::get('docs-site/{caminho}', DocsSiteController::class)->where('caminho', '[A-Za-z0-9._/-]+');
