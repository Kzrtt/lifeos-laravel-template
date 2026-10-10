<?php

declare(strict_types=1);

namespace Modules\Notas\Mcp\Tools;

use Modules\Core\Contracts\ProjetoReadModel;
use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Core\Support\Mcp\Args;
use Modules\Core\Support\Mcp\ResolveProjetos;
use Modules\Core\Support\Mcp\Resultado;
use Modules\Core\Support\Mcp\ToolPorArquivo;
use Modules\Notas\Actions\BuscarNotas;
use Modules\Notas\Models\Nota;

final class SearchNotasTool extends ToolPorArquivo
{
    public function __construct(VocabularioReadModel $vocabulario, private readonly ProjetoReadModel $projetos, private readonly BuscarNotas $buscar)
    {
        parent::__construct($vocabulario);
    }

    public function ordem(): int
    {
        return 1;
    }

    protected function arquivo(): string
    {
        return dirname(__DIR__, 3).'/resources/mcp/search_notas.json';
    }

    public function chamar(array $args): array
    {
        $nome = Args::textoOpcional($args, 'nome');
        $tipos = Args::lista($args['tipo'] ?? null);
        $dataInicio = ! empty($args['data_inicio']) ? Args::str($args['data_inicio']) : '';
        $dataFim = ! empty($args['data_fim']) ? Args::str($args['data_fim']) : '';
        // Intervalo fechado de datas = "ler o período": o teto sobe.
        $limit = Args::limite($args['limit'] ?? null, 20, ($dataInicio !== '' && $dataFim !== '') ? 100 : 50);

        $validos = $this->vocabulario->valores('nota_tipo');
        $invalidos = array_values(array_filter($tipos, fn (string $t): bool => ! in_array($t, $validos, true)));
        if ($invalidos !== []) {
            return Resultado::erro('Tipo(s) inválido(s): '.Args::juntar($invalidos).'. Valores aceitos: '.Args::juntar($validos).'.');
        }
        if ($erro = Args::erroDatas($dataInicio, $dataFim)) {
            return $erro;
        }

        $catalogo = $this->projetos->catalogo();
        $termos = Args::lista($args['projetos'] ?? null);
        ['ids' => $ids, 'warnings' => $warnings] = $termos !== [] ? ResolveProjetos::filtro($catalogo, $termos) : ['ids' => null, 'warnings' => []];

        // Nenhum termo bateu com projeto: o filtro é vazio e nada passa.
        if ($ids !== null && $ids === []) {
            return Resultado::json(['total_matches' => 0, 'returned' => 0, 'truncated' => false, 'warnings' => $warnings, 'notas' => []]);
        }

        $r = $this->buscar->execute(['nome' => $nome, 'tipos' => $tipos, 'data_inicio' => $dataInicio, 'data_fim' => $dataFim, 'projeto_ids' => $ids, 'limit' => $limit]);
        $porId = ResolveProjetos::porId($catalogo);

        $devolvidas = $r['notas']->map(fn (Nota $n): array => [
            'id' => $n->not_id,
            'name' => $n->not_name,
            'tipo' => $n->not_tipo,
            'data' => $n->not_data?->format('Y-m-d'),
            // TODOS os projetos da nota (o filtro não encolhe a lista).
            'projetos' => $n->vinculos->map(fn ($v): ?array => isset($porId[$v->projeto_prj_id])
                ? ['id' => $v->projeto_prj_id, 'name' => $porId[$v->projeto_prj_id]->rotulo()] : null)->filter()->values()->all(),
            'snippet' => Args::snippet($n->not_conteudo_md),
            'conteudo_md' => $n->not_conteudo_md,
        ])->all();

        return Resultado::json([
            'total_matches' => $r['total'],
            'returned' => count($devolvidas),
            'truncated' => $r['total'] > count($devolvidas),
            ...($warnings !== [] ? ['warnings' => $warnings] : []),
            'notas' => $devolvidas,
        ]);
    }
}
