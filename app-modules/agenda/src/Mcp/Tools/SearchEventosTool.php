<?php

declare(strict_types=1);

namespace Modules\Agenda\Mcp\Tools;

use Modules\Agenda\Actions\BuscarEventos;
use Modules\Agenda\Models\Evento;
use Modules\Core\Contracts\ProjetoReadModel;
use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Core\Support\Mcp\Args;
use Modules\Core\Support\Mcp\ResolveProjetos;
use Modules\Core\Support\Mcp\Resultado;
use Modules\Core\Support\Mcp\ToolPorArquivo;

final class SearchEventosTool extends ToolPorArquivo
{
    public function __construct(VocabularioReadModel $vocabulario, private readonly ProjetoReadModel $projetos, private readonly BuscarEventos $buscar)
    {
        parent::__construct($vocabulario);
    }

    public function ordem(): int
    {
        return 8;
    }

    protected function arquivo(): string
    {
        return dirname(__DIR__, 3).'/resources/mcp/search_eventos.json';
    }

    public function chamar(array $args): array
    {
        $nome = Args::textoOpcional($args, 'nome');
        $tipos = Args::lista($args['tipo'] ?? null);
        $validos = $this->vocabulario->valores('evento_tipo');
        $invalidos = array_values(array_filter($tipos, fn (string $t): bool => ! in_array($t, $validos, true)));
        if ($invalidos !== []) {
            return Resultado::erro('Tipo(s) inválido(s): '.Args::juntar($invalidos).'. Valores aceitos: '.Args::juntar($validos).'.');
        }
        $dataInicio = ! empty($args['data_inicio']) ? Args::str($args['data_inicio']) : '';
        $dataFim = ! empty($args['data_fim']) ? Args::str($args['data_fim']) : '';
        if ($erro = Args::erroDatas($dataInicio, $dataFim)) {
            return $erro;
        }
        $limit = Args::limite($args['limit'] ?? null);

        $catalogo = $this->projetos->catalogo();
        $termos = Args::lista($args['projetos'] ?? null);
        ['ids' => $ids, 'warnings' => $warnings] = $termos !== [] ? ResolveProjetos::filtro($catalogo, $termos) : ['ids' => null, 'warnings' => []];
        if ($ids !== null && $ids === []) {
            return Resultado::json(['total_matches' => 0, 'returned' => 0, 'truncated' => false, 'warnings' => $warnings, 'eventos' => []]);
        }

        $r = $this->buscar->execute(['nome' => $nome, 'tipos' => $tipos, 'projeto_ids' => $ids, 'data_inicio' => $dataInicio, 'data_fim' => $dataFim, 'limit' => $limit]);
        $porId = ResolveProjetos::porId($catalogo);

        $devolvidos = $r['eventos']->map(fn (Evento $e): array => [
            'id' => $e->evt_id, 'name' => $e->evt_name, 'date' => $e->evt_date->format('Y-m-d'),
            'date_fim' => $e->evt_date_fim?->format('Y-m-d'), 'tipo' => $e->evt_tipo,
            'projeto' => ($e->projeto_prj_id && isset($porId[$e->projeto_prj_id])) ? ['id' => $e->projeto_prj_id, 'name' => $porId[$e->projeto_prj_id]->rotulo()] : null,
        ])->all();

        return Resultado::json([
            'total_matches' => $r['total'], 'returned' => count($devolvidos), 'truncated' => $r['total'] > count($devolvidos),
            ...($warnings !== [] ? ['warnings' => $warnings] : []),
            'eventos' => $devolvidos,
        ]);
    }
}
