<?php

declare(strict_types=1);

namespace Modules\Projetos\Mcp\Tools;

use Modules\Core\Contracts\ProjetoReadModel;
use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Core\Support\Mcp\Args;
use Modules\Core\Support\Mcp\ResolveProjetos;
use Modules\Core\Support\Mcp\Resultado;
use Modules\Core\Support\Mcp\ToolPorArquivo;
use Modules\Projetos\Actions\BuscarTarefas;
use Modules\Projetos\Models\Tarefa;

final class SearchTarefasTool extends ToolPorArquivo
{
    public function __construct(VocabularioReadModel $vocabulario, private readonly ProjetoReadModel $projetos, private readonly BuscarTarefas $buscar)
    {
        parent::__construct($vocabulario);
    }

    public function ordem(): int
    {
        return 4;
    }

    protected function arquivo(): string
    {
        return dirname(__DIR__, 3).'/resources/mcp/search_tarefas.json';
    }

    public function chamar(array $args): array
    {
        $nome = Args::textoOpcional($args, 'nome');
        $status = ! empty($args['status']) ? Args::str($args['status']) : '';
        $statusValidos = $this->vocabulario->valores('tarefa_status');
        if ($status !== '' && ! in_array($status, $statusValidos, true)) {
            return Resultado::erro("Status inválido: {$status}. Valores aceitos: ".Args::juntar($statusValidos).'.');
        }
        $tipos = Args::lista($args['tipo'] ?? null);
        $tiposValidos = $this->vocabulario->valores('tarefa_tipo');
        $invalidos = array_values(array_filter($tipos, fn (string $t): bool => ! in_array($t, $tiposValidos, true)));
        if ($invalidos !== []) {
            return Resultado::erro('Tipo(s) inválido(s): '.Args::juntar($invalidos).'. Valores aceitos: '.Args::juntar($tiposValidos).'.');
        }
        $dataInicio = ! empty($args['data_entrega_inicio']) ? Args::str($args['data_entrega_inicio']) : '';
        $dataFim = ! empty($args['data_entrega_fim']) ? Args::str($args['data_entrega_fim']) : '';
        if ($erro = Args::erroDatas($dataInicio, $dataFim)) {
            return $erro;
        }
        $limit = Args::limite($args['limit'] ?? null);

        $catalogo = $this->projetos->catalogo();
        $termos = Args::lista($args['projetos'] ?? null);
        ['ids' => $ids, 'warnings' => $warnings] = $termos !== [] ? ResolveProjetos::filtro($catalogo, $termos) : ['ids' => null, 'warnings' => []];
        if ($ids !== null && $ids === []) {
            return Resultado::json(['total_matches' => 0, 'returned' => 0, 'truncated' => false, 'warnings' => $warnings, 'tarefas' => []]);
        }

        $r = $this->buscar->execute([
            'nome' => $nome, 'status' => $status, 'tipos' => $tipos, 'projeto_ids' => $ids,
            'data_inicio' => $dataInicio, 'data_fim' => $dataFim, 'limit' => $limit,
        ]);
        $porId = ResolveProjetos::porId($catalogo);

        $devolvidas = $r['tarefas']->map(fn (Tarefa $t): array => [
            'id' => $t->tar_id, 'name' => $t->tar_name, 'status' => $t->tar_status, 'tipo' => $t->tar_tipo,
            'data_entrega' => $t->tar_data_entrega?->format('Y-m-d'),
            'projeto' => isset($porId[$t->projeto_prj_id]) ? ['id' => $t->projeto_prj_id, 'name' => $porId[$t->projeto_prj_id]->rotulo()] : null,
        ])->all();

        return Resultado::json([
            'total_matches' => $r['total'], 'returned' => count($devolvidas), 'truncated' => $r['total'] > count($devolvidas),
            ...($warnings !== [] ? ['warnings' => $warnings] : []),
            'tarefas' => $devolvidas,
        ]);
    }
}
