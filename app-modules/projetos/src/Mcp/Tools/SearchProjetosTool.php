<?php

declare(strict_types=1);

namespace Modules\Projetos\Mcp\Tools;

use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Core\Support\Mcp\Args;
use Modules\Core\Support\Mcp\Resultado;
use Modules\Core\Support\Mcp\ToolPorArquivo;
use Modules\Projetos\Actions\BuscarProjetos;
use Modules\Projetos\Models\Projeto;

final class SearchProjetosTool extends ToolPorArquivo
{
    public function __construct(VocabularioReadModel $vocabulario, private readonly BuscarProjetos $buscar)
    {
        parent::__construct($vocabulario);
    }

    public function ordem(): int
    {
        return 7;
    }

    protected function arquivo(): string
    {
        return dirname(__DIR__, 3).'/resources/mcp/search_projetos.json';
    }

    public function chamar(array $args): array
    {
        $nome = Args::textoOpcional($args, 'nome');
        $status = ! empty($args['status']) ? Args::str($args['status']) : '';
        $statusValidos = $this->vocabulario->valores('projeto_status');
        if ($status !== '' && ! in_array($status, $statusValidos, true)) {
            return Resultado::erro("Status inválido: {$status}. Valores aceitos: ".Args::juntar($statusValidos).'.');
        }
        $tags = Args::lista($args['tags'] ?? null);
        $tagsValidas = $this->vocabulario->valores('projeto_tag');
        $invalidas = array_values(array_filter($tags, fn (string $t): bool => ! in_array($t, $tagsValidas, true)));
        if ($invalidas !== []) {
            return Resultado::erro('Tag(s) inválida(s): '.Args::juntar($invalidas).'. Valores aceitos: '.Args::juntar($tagsValidas).'.');
        }

        $r = $this->buscar->execute(['nome' => $nome, 'status' => $status, 'tags' => $tags, 'limit' => Args::limite($args['limit'] ?? null)]);

        $devolvidos = $r['projetos']->map(fn (Projeto $p): array => [
            'id' => $p->prj_id, 'name' => $p->prj_name, 'emoji' => $p->prj_emoji, 'status' => $p->prj_status, 'tags' => $p->prj_tags,
        ])->all();

        return Resultado::json([
            'total_matches' => $r['total'], 'returned' => count($devolvidos), 'truncated' => $r['total'] > count($devolvidos), 'projetos' => $devolvidos,
        ]);
    }
}
