<?php

declare(strict_types=1);

namespace Modules\Citacoes\Mcp\Tools;

use Modules\Citacoes\Actions\BuscarCitacoes;
use Modules\Citacoes\Models\Citacao;
use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Core\Support\Mcp\Args;
use Modules\Core\Support\Mcp\Resultado;
use Modules\Core\Support\Mcp\ToolPorArquivo;

final class SearchCitacoesTool extends ToolPorArquivo
{
    public function __construct(VocabularioReadModel $vocabulario, private readonly BuscarCitacoes $buscar)
    {
        parent::__construct($vocabulario);
    }

    public function ordem(): int
    {
        return 12;
    }

    protected function arquivo(): string
    {
        return dirname(__DIR__, 3).'/resources/mcp/search_citacoes.json';
    }

    public function chamar(array $args): array
    {
        $r = $this->buscar->execute([
            'texto' => Args::textoOpcional($args, 'texto'),
            'autor' => Args::textoOpcional($args, 'autor'),
            'limit' => Args::limite($args['limit'] ?? null),
        ]);

        $devolvidas = $r['citacoes']->map(fn (Citacao $c): array => ['id' => $c->cit_id, 'texto' => $c->cit_texto, 'autor' => $c->cit_autor])->all();

        return Resultado::json([
            'total_matches' => $r['total'], 'returned' => count($devolvidas), 'truncated' => $r['total'] > count($devolvidas), 'citacoes' => $devolvidas,
        ]);
    }
}
