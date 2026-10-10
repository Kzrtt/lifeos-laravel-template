<?php

declare(strict_types=1);

namespace Modules\Financas\Mcp\Tools;

use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Core\Support\Mcp\Args;
use Modules\Core\Support\Mcp\Resultado;
use Modules\Core\Support\Mcp\ToolPorArquivo;
use Modules\Financas\Models\Movimentacao;
use Modules\Financas\Models\Recorrencia;
use Modules\Financas\Services\ResumoFinanceiroService;
use Modules\Financas\Services\SaldoService;

/**
 * Panorama financeiro já AGREGADO com as regras da tela de Finanças. Lê do começo
 * da tabela até 2 meses depois do último mês pedido ou do mês corrente (o que vier
 * depois): sem limite inferior porque a cadeia de faturas olha para trás, e o teto
 * cobre os lançamentos futuros que a projeção usa.
 */
final class ResumoFinanceiroTool extends ToolPorArquivo
{
    private const FORMATO_MES = '/^\d{4}-(0[1-9]|1[0-2])$/';

    public function __construct(VocabularioReadModel $vocabulario, private readonly SaldoService $saldo)
    {
        parent::__construct($vocabulario);
    }

    public function ordem(): int
    {
        return 21;
    }

    protected function arquivo(): string
    {
        return dirname(__DIR__, 3).'/resources/mcp/resumo_financeiro.json';
    }

    public function chamar(array $args): array
    {
        $hoje = Args::hojeSaoPaulo();
        $mesHoje = substr($hoje, 0, 7);
        $de = Args::str($args['de'] ?? $args['mes'] ?? $mesHoje);
        $ate = Args::str($args['ate'] ?? $args['mes'] ?? $de);
        $incluirMov = ($args['incluir_movimentacoes'] ?? null) === true || ($args['incluir_movimentacoes'] ?? null) === 'true';

        if (preg_match(self::FORMATO_MES, $de) !== 1 || preg_match(self::FORMATO_MES, $ate) !== 1) {
            return Resultado::erro('Meses no formato YYYY-MM (ex.: 2026-09).');
        }
        if (strcmp($ate, $de) < 0) {
            return Resultado::erro('O parâmetro ate não pode ser anterior a de.');
        }

        $meses = [];
        for ($ym = $de; strcmp($ym, $ate) <= 0; $ym = $this->proximo($ym)) {
            $meses[] = $ym;
            if (count($meses) > 12) {
                return Resultado::erro('No máximo 12 meses por chamada.');
            }
        }
        if ($incluirMov && count($meses) > 3) {
            return Resultado::erro('Com incluir_movimentacoes, no máximo 3 meses por chamada (o intervalo pedido tem '.count($meses).'). Divida em chamadas menores ou chame sem incluir_movimentacoes.');
        }

        $limite = $this->proximo($this->proximo($this->proximo(strcmp($ate, $mesHoje) > 0 ? $ate : $mesHoje))).'-01';
        $todas = [];
        foreach (Movimentacao::query()->where('mov_date', '<', $limite)->orderBy('mov_date')->orderBy('mov_created_at')->orderBy('mov_id')->cursor() as $i => $m) {
            $todas[] = [
                '_i' => $i, 'id' => $m->mov_id, 'name' => $m->mov_name, 'valor' => (float) $m->mov_valor,
                'date' => $m->mov_date->format('Y-m-d'), 'tipo' => $m->mov_tipo, 'created_at' => $m->mov_created_at?->toIso8601String() ?? '',
            ];
        }

        // O mês corrente entra mesmo fora do intervalo: a projeção parte do saldo de hoje.
        $aberturas = [];
        foreach (array_unique([...$meses, $mesHoje]) as $ym) {
            $aberturas[$ym] = $this->saldo->saldoAbertura($ym.'-01');
        }

        $cadastradas = Recorrencia::query()->where('rec_ativa', true)->orderBy('rec_nome')->get()
            ->map(fn (Recorrencia $r): array => [
                'nome' => $r->rec_nome, 'direcao' => $r->rec_direcao, 'valor_min' => (float) $r->rec_valor_min,
                'valor_max' => (float) $r->rec_valor_max, 'meio' => $r->rec_meio, 'dia' => $r->rec_dia,
            ])->all();

        $servico = new ResumoFinanceiroService($todas, $hoje, $this->vocabulario->valores('mov_meio'), $cadastradas);

        return Resultado::json($servico->gerar($meses, $aberturas, $incluirMov));
    }

    private function proximo(string $ym): string
    {
        [$y, $m] = array_map('intval', explode('-', $ym));

        return $m === 12 ? ($y + 1).'-01' : $y.'-'.str_pad((string) ($m + 1), 2, '0', STR_PAD_LEFT);
    }
}
