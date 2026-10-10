<?php

declare(strict_types=1);

namespace Modules\Core\Http;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serve CRU um arquivo HTML do módulo dono — o Laravel serve as telas, não as
 * gera. Bytes intactos: é o que permite provar "front byte a byte" contra o
 * repositório antigo, e evita que o Blade interprete `{{` e `@` do JS/CSS.
 *
 * A rota declara o arquivo em `->defaults('file', 'app-modules/<mod>/resources/pages/x.html')`
 * (relativo à raiz do projeto) e, se não for HTML, o `->defaults('tipo', 'text/plain; charset=utf-8')`. Não usa closure para que `route:cache` funcione.
 */
final class StaticPageController
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $file = base_path((string) $request->route('file'));

        abort_unless(is_file($file), 404);

        return response()->file($file, [
            'Content-Type' => (string) ($request->route('tipo') ?? 'text/html; charset=utf-8'),
            'Cache-Control' => 'no-cache',
        ]);
    }
}
