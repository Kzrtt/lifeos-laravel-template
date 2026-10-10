<?php

declare(strict_types=1);

namespace Modules\Painel\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * GET /docs-site/{caminho} — o site estático de documentação (`docs-site/` na
 * raiz do repositório), público. Só serve html/css/js dentro da pasta; qualquer
 * caminho que escape dela (`..`, link simbólico) ou com outra extensão dá 404.
 * Os links do site são relativos, então `/docs-site` (sem barra) redireciona.
 */
final class DocsSiteController
{
    private const TIPOS = [
        'html' => 'text/html; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
    ];

    public function __invoke(string $caminho = 'index.html'): BinaryFileResponse
    {
        $base = realpath(base_path('docs-site'));
        $arquivo = $base === false ? false : realpath($base.'/'.$caminho);
        $tipo = self::TIPOS[strtolower(pathinfo($caminho, PATHINFO_EXTENSION))] ?? null;

        abort_if($arquivo === false || $tipo === null || ! str_starts_with($arquivo, $base.DIRECTORY_SEPARATOR) || ! is_file($arquivo), 404);

        return response()->file($arquivo, ['Content-Type' => $tipo, 'Cache-Control' => 'no-cache']);
    }
}
