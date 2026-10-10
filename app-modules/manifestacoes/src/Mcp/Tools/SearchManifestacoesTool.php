<?php

declare(strict_types=1);

namespace Modules\Manifestacoes\Mcp\Tools;

use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Core\Support\Mcp\Args;
use Modules\Core\Support\Mcp\Resultado;
use Modules\Core\Support\Mcp\ToolPorArquivo;
use Modules\Manifestacoes\Actions\BuscarManifestacoes;
use Modules\Manifestacoes\Models\Manifestacao;

final class SearchManifestacoesTool extends ToolPorArquivo
{
    public function __construct(VocabularioReadModel $vocabulario, private readonly BuscarManifestacoes $buscar)
    {
        parent::__construct($vocabulario);
    }

    public function ordem(): int
    {
        return 11;
    }

    protected function arquivo(): string
    {
        return dirname(__DIR__, 3).'/resources/mcp/search_manifestacoes.json';
    }

    public function chamar(array $args): array
    {
        $nome = Args::textoOpcional($args, 'nome');
        $status = ! empty($args['status']) ? Args::str($args['status']) : '';
        $statusValidos = $this->vocabulario->valores('manifestacao_status');
        if ($status !== '' && ! in_array($status, $statusValidos, true)) {
            return Resultado::erro("Status inválido: {$status}. Valores aceitos: ".Args::juntar($statusValidos).'.');
        }
        $tags = Args::lista($args['tags'] ?? null);
        $tagsValidas = $this->vocabulario->valores('manifestacao_tag');
        $invalidas = array_values(array_filter($tags, fn (string $t): bool => ! in_array($t, $tagsValidas, true)));
        if ($invalidas !== []) {
            return Resultado::erro('Tag(s) inválida(s): '.Args::juntar($invalidas).'. Valores aceitos: '.Args::juntar($tagsValidas).'.');
        }

        $r = $this->buscar->execute(['nome' => $nome, 'status' => $status, 'tags' => $tags, 'limit' => Args::limite($args['limit'] ?? null)]);

        $devolvidas = $r['manifestacoes']->map(fn (Manifestacao $m): array => [
            'id' => $m->man_id, 'name' => $m->man_name, 'status' => $m->man_status, 'tags' => $m->man_tags,
            'descricao' => $m->man_descricao, 'banner_url' => $m->man_banner_url,
        ])->all();

        return Resultado::json([
            'total_matches' => $r['total'], 'returned' => count($devolvidas), 'truncated' => $r['total'] > count($devolvidas), 'manifestacoes' => $devolvidas,
        ]);
    }
}
