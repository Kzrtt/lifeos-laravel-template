<?php

declare(strict_types=1);

namespace Modules\Financas\Mcp\Tools;

use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Core\Support\Mcp\Args;
use Modules\Core\Support\Mcp\Resultado;
use Modules\Core\Support\Mcp\ToolPorArquivo;
use Modules\Financas\Actions\BuscarMovimentacoes;
use Modules\Financas\Models\Movimentacao;

final class SearchMovimentacoesTool extends ToolPorArquivo
{
    public function __construct(VocabularioReadModel $vocabulario, private readonly BuscarMovimentacoes $buscar)
    {
        parent::__construct($vocabulario);
    }

    public function ordem(): int
    {
        return 22;
    }

    protected function arquivo(): string
    {
        return dirname(__DIR__, 3).'/resources/mcp/search_movimentacoes.json';
    }

    public function chamar(array $args): array
    {
        $nome = Args::textoOpcional($args, 'nome');
        $direcao = ! empty($args['direcao']) ? Args::str($args['direcao']) : '';
        $direcoes = $this->vocabulario->valores('mov_direcao');
        if ($direcao !== '' && ! in_array($direcao, $direcoes, true)) {
            return Resultado::erro("Direção inválida: {$direcao}. Valores aceitos: ".Args::juntar($direcoes).'.');
        }
        $meios = Args::lista($args['meio'] ?? null);
        $meiosValidos = $this->vocabulario->valores('mov_meio');
        $invalidos = array_values(array_filter($meios, fn (string $m): bool => ! in_array($m, $meiosValidos, true)));
        if ($invalidos !== []) {
            return Resultado::erro('Meio(s) inválido(s): '.Args::juntar($invalidos).'. Valores aceitos: '.Args::juntar($meiosValidos).'.');
        }
        $dataInicio = ! empty($args['data_inicio']) ? Args::str($args['data_inicio']) : '';
        $dataFim = ! empty($args['data_fim']) ? Args::str($args['data_fim']) : '';
        if ($erro = Args::erroDatas($dataInicio, $dataFim)) {
            return $erro;
        }

        $valorMin = Args::tem($args, 'valor_min') ? $this->numero($args['valor_min']) : null;
        $valorMax = Args::tem($args, 'valor_max') ? $this->numero($args['valor_max']) : null;
        if ((Args::tem($args, 'valor_min') && $valorMin === null) || (Args::tem($args, 'valor_max') && $valorMax === null)) {
            return Resultado::erro('valor_min e valor_max precisam ser números.');
        }

        // Intervalo fechado de datas = "ler o período": um mês tem ~100 linhas, e com
        // teto de 50 o modelo receberia meio mês achando que era tudo.
        $limit = Args::limite($args['limit'] ?? null, 20, ($dataInicio !== '' && $dataFim !== '') ? 300 : 50);

        $r = $this->buscar->execute([
            'nome' => $nome, 'direcao' => $direcao, 'meios' => $meios, 'data_inicio' => $dataInicio, 'data_fim' => $dataFim,
            'valor_min' => $valorMin, 'valor_max' => $valorMax, 'limit' => $limit,
        ]);

        $devolvidas = $r['movimentacoes']->map(fn (Movimentacao $m): array => [
            'id' => $m->mov_id, 'name' => $m->mov_name, 'valor' => (float) $m->mov_valor,
            'date' => $m->mov_date->format('Y-m-d'), 'tipo' => $m->mov_tipo,
        ])->all();

        return Resultado::json([
            'total_matches' => $r['total'], 'returned' => count($devolvidas), 'truncated' => $r['total'] > count($devolvidas), 'movimentacoes' => $devolvidas,
        ]);
    }

    /** `Number(v)` do JS: numérico (ou string numérica) vira float; null vira 0; o resto é inválido (null). */
    private function numero(mixed $v): ?float
    {
        return match (true) {
            $v === null => 0.0,
            is_bool($v) => $v ? 1.0 : 0.0,
            is_int($v) || is_float($v) => is_finite((float) $v) ? (float) $v : null,
            is_string($v) => trim($v) === '' ? 0.0 : (is_numeric(trim($v)) ? (float) trim($v) : null),
            default => null,
        };
    }
}
