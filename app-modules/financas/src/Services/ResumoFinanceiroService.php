<?php

declare(strict_types=1);

namespace Modules\Financas\Services;

use Carbon\CarbonImmutable;
use Normalizer;

/**
 * Os números da tela de Finanças calculados com as MESMAS regras — a terceira
 * cópia da lógica (financas.js, lifeos.js e esta), agora em PHP. Port da seção
 * "RESUMO FINANCEIRO" do servidor MCP de hoje, escrita sem fetch justamente para
 * ser testável: o teste de paridade roda o TypeScript original (Node) e esta
 * classe sobre as mesmas movimentações e exige saídas idênticas.
 *
 * Regras (modal "Como funciona" de financas.html):
 *  - Compra em Crédito não sai do caixa no mês: entra em Saídas "com crédito",
 *    fica fora do Saldo e vira fatura futura.
 *  - Saldo = abertura + entradas realizadas − saídas de caixa realizadas. Data
 *    futura conta nos totais, mas só entra no saldo quando a data chegar.
 *  - Pagamento de fatura = Saida SEM meio com "fatura" no nome. Abate a fatura
 *    que fecha no mês; o excedente vira adiantamento da seguinte, em cadeia.
 *    "adiant" no nome = adiantamento explícito, que pula para a próxima fatura.
 *  - A fatura fecha no último dia do mês: compra antes dele cai em M+1; no
 *    último dia, em M+2.
 *
 * Análise (consumo, rateios, compromissos, sinais, ritmo, projeção): campos
 * ACRESCENTADOS ao resumo. O nome da movimentação É a categoria; `chave` junta
 * grafias diferentes da mesma coisa.
 *
 * Uma movimentação ("Mov") é um array: _i (posição única, identidade), id, name,
 * valor (float), date (YYYY-MM-DD), tipo (list<string>), created_at (ISO).
 */
final class ResumoFinanceiroService
{
    /** Chave normalizada → chave canônica (grafias antigas de jun/2026). */
    private const ALIASES = [
        'conta luz' => 'luz', 'conta internet' => 'internet', 'financiamento carro' => 'carro',
        'crunchroll' => 'crunchyroll', 'posto delta' => 'delta', 'areia gato' => 'areia',
        'conta de luz (variavel)' => 'luz', 'conta de internet' => 'internet', 'aluguel (variavel)' => 'aluguel',
    ];

    /** Compras em nome de vários amigos: a Entrada com esse nome é a parte deles, não renda. */
    private const RATEIOS = ['corre', 'cogumelo'];

    private const MIN_MOVIMENTACOES_INICIO = 20;

    private const JANELA_COMPROMISSO = 3;

    private const MAX_OCORRENCIAS_COMPROMISSO = 2;

    private const TOLERANCIA_COMPROMISSO = 0.15;

    private const COMPROMISSOS_FORCAR = ['cartao'];

    private const COMPROMISSOS_IGNORAR = [];

    private const LIMIAR_PONTUAL = 200;

    private const MIN_REPETICOES_TICKET = 4;

    private const LIMIAR_ENTRADA_GRANDE = 300;

    /** @var array<string, list<array<string, mixed>>> */
    private array $porMes = [];

    /** @var array<string, string> */
    private array $nomes = [];

    /** @var array<string, array<string, int>> */
    private array $grafias = [];

    /** @var list<array<string, mixed>> */
    private array $entradasGrandes = [];

    /** @var array<string, array<string, list<array<string, mixed>>>> */
    private array $cadastroPorDirecao = ['Entrada' => [], 'Saida' => []];

    private string $inicio;

    private string $mesHoje;

    private int $diaHoje;

    private ?string $primeiroCheio = null;

    /** @var array<string, array<string, mixed>> */
    private array $recMemo = [];

    /** @var array<string, list<array<string, mixed>>> */
    private array $pontMemo = [];

    /** @var array<string, float> */
    private array $faturaRestanteEm = [];

    /**
     * @param  list<array<string, mixed>>  $todas  todas as movimentações lidas (já com _i)
     * @param  list<string>  $meios  vocabulário vigente de meios de pagamento
     * @param  list<array<string, mixed>>  $cadastradas  recorrências ativas (nome, direcao, valor_min, valor_max, meio, dia)
     */
    public function __construct(
        private readonly array $todas,
        private readonly string $hoje,
        private readonly array $meios,
        array $cadastradas = [],
    ) {
        foreach ($todas as $m) {
            $this->porMes[substr((string) $m['date'], 0, 7)][] = $m;
        }
        $this->mesHoje = substr($hoje, 0, 7);
        $this->diaHoje = (int) substr($hoje, 8, 2);

        foreach ($cadastradas as $c) {
            $lista = $this->cadastroPorDirecao[$c['direcao']] ?? null;
            if ($lista === null) {
                continue;
            }
            foreach ($lista as $x) {
                if ($this->chave($x['nome']) === $this->chave($c['nome'])) {
                    continue 2;
                }
            }
            $this->cadastroPorDirecao[$c['direcao']][] = $c;
        }

        $this->prepararAnalise();
    }

    // ───────────────────────── API

    /**
     * @param  list<string>  $meses  meses pedidos (YYYY-MM), em ordem
     * @param  array<string, float>  $aberturas  saldo de abertura por mês (inclui o mês corrente)
     * @return array<string, mixed>
     */
    public function gerar(array $meses, array $aberturas, bool $incluirMov): array
    {
        $intervalo = count($meses) > 1;

        $resumos = [];
        foreach ($meses as $ym) {
            $resumos[] = [
                ...$this->calcularResumoMes($ym, $aberturas[$ym]),
                ...$this->analisarMes($ym, ['porNome' => ! $intervalo, 'lista' => $incluirMov]),
            ];
        }

        $comparativo = $intervalo ? array_map(fn (array $x): array => [
            'mes' => $x['mes'], 'entradas' => $x['entradas'], 'saidas_caixa' => $x['saidas_caixa'],
            'saidas_com_credito' => $x['saidas_com_credito'], 'variacao_mes' => $x['variacao_mes'], 'saldo' => $x['saldo'],
            'fatura_deste_mes' => $x['fatura_deste_mes']['total'], 'parcial' => $x['parcial'], 'futuro' => $x['futuro'],
            'consumo_mes' => $x['consumo_mes'], 'entradas_proprias' => $x['entradas_proprias'],
        ], $resumos) : null;

        return [
            'hoje' => $this->hoje,
            'inicio_dados' => $this->inicio,
            ...($comparativo !== null ? ['comparativo' => $comparativo] : []),
            ...($intervalo ? ['comparativo_por_nome' => $this->comparativoPorNome($meses)] : []),
            'projecao' => $this->projecao($aberturas[$this->mesHoje]),
            'meses' => $resumos,
        ];
    }

    // ───────────────────────── helpers fin*

    private static function round2(float|int $v): float
    {
        return floor($v * 100 + 0.5) / 100; // Math.round do JS: meio arredonda para cima
    }

    private static function jsRound(float|int $v): float
    {
        return floor($v + 0.5);
    }

    private static function num(mixed $v): float
    {
        return is_numeric($v) && is_finite((float) $v) ? (float) $v : 0.0;
    }

    /** @param array<string, mixed> $m */
    private static function has(array $m, string $tag): bool
    {
        return in_array($tag, $m['tipo'], true);
    }

    /** @param array<string, mixed> $m */
    private static function isSaida(array $m): bool
    {
        return self::has($m, 'Saida');
    }

    /** @param array<string, mixed> $m */
    private static function isEntrada(array $m): bool
    {
        return self::has($m, 'Entrada');
    }

    private static function nextMonth(string $ym): string
    {
        [$y, $m] = array_map('intval', explode('-', $ym));

        return $m === 12 ? ($y + 1).'-01' : $y.'-'.str_pad((string) ($m + 1), 2, '0', STR_PAD_LEFT);
    }

    private static function prevMonth(string $ym): string
    {
        [$y, $m] = array_map('intval', explode('-', $ym));

        return $m === 1 ? ($y - 1).'-12' : $y.'-'.str_pad((string) ($m - 1), 2, '0', STR_PAD_LEFT);
    }

    private static function lastDay(string $ym): int
    {
        [$y, $m] = array_map('intval', explode('-', $ym));

        return CarbonImmutable::create($y, $m, 1)->daysInMonth;
    }

    private static function faturaDestino(string $ym, int $dia): string
    {
        $prox = self::nextMonth($ym);

        return $dia < self::lastDay($ym) ? $prox : self::nextMonth($prox);
    }

    /** @param array<string, mixed> $a @param array<string, mixed> $b */
    private static function cmpByDateAsc(array $a, array $b): int
    {
        return strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? '')) ?: strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? ''));
    }

    private static function jsTrim(string $s): string
    {
        return preg_replace('/^\s+|\s+$/u', '', $s) ?? $s;
    }

    private static function normName(?string $s): string
    {
        return preg_replace('/\s+/u', ' ', mb_strtolower(self::jsTrim($s ?? ''))) ?? '';
    }

    /** Sem acento, minúscula, espaços colapsados; depois os apelidos. */
    private function chave(?string $nome): string
    {
        $nome ??= '';
        $d = Normalizer::normalize($nome, Normalizer::FORM_D);
        $k = preg_replace('/\s+/u', ' ', self::jsTrim(mb_strtolower(preg_replace('/[\x{0300}-\x{036f}]/u', '', $d === false ? $nome : $d) ?? $nome))) ?? '';

        return self::ALIASES[$k] ?? $k;
    }

    /** @param array<string, mixed> $m */
    private function meioStr(array $m): ?string
    {
        $ms = array_values(array_filter($this->meios, fn (string $me): bool => self::has($m, $me)));

        return $ms === [] ? null : implode('+', $ms);
    }

    /** @param list<float|int> $xs */
    private static function mediana(array $xs): float
    {
        if ($xs === []) {
            return 0.0;
        }
        sort($xs);
        $h = intdiv(count($xs), 2);

        return count($xs) % 2 ? (float) $xs[$h] : ($xs[$h - 1] + $xs[$h]) / 2;
    }

    /** @param array<string, mixed> $m */
    private static function dia(array $m): int
    {
        return $m['date'] ? (int) substr((string) $m['date'], 8, 2) : 0;
    }

    private static function somaDias(string $iso, int $n): string
    {
        return CarbonImmutable::parse($iso.'T00:00:00Z')->addDays($n)->format('Y-m-d');
    }

    /** @param list<float|int> $xs */
    private static function soma(array $xs): float
    {
        return (float) array_sum($xs);
    }

    // ───────────────────────── contexto (finCtx)

    /** @param array<string, mixed> $m */
    private function hasAnyMeio(array $m): bool
    {
        foreach ($this->meios as $me) {
            if (self::has($m, $me)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $m */
    private function isSaidaCaixa(array $m): bool
    {
        return self::isSaida($m) && ! self::has($m, 'Crédito');
    }

    /** @param array<string, mixed> $m */
    private function isPagamentoFatura(array $m): bool
    {
        return self::isSaida($m) && ! $this->hasAnyMeio($m) && preg_match('/fatura/iu', (string) ($m['name'] ?? '')) === 1;
    }

    /** @param array<string, mixed> $m */
    private function isAdiantamentoExplicito(array $m): bool
    {
        return $this->isPagamentoFatura($m) && preg_match('/adiant/iu', (string) ($m['name'] ?? '')) === 1;
    }

    /** @param array<string, mixed> $m */
    private function isRealizado(array $m): bool
    {
        return ! $m['date'] || strcmp((string) $m['date'], $this->hoje) <= 0;
    }

    /** @return list<array<string, mixed>> */
    private function rowsDoMes(string $ym): array
    {
        return $this->porMes[$ym] ?? [];
    }

    // ───────────────────────── faturas

    /**
     * Comparação em CENTAVOS (cópia de splitPagamentosFatura).
     *
     * @param  list<array<string, mixed>>  $pagamentos
     * @return array{atual: list<array<string, mixed>>, adiantamento: list<array<string, mixed>>}
     */
    private function splitPagamentos(array $pagamentos, float $totalFatura): array
    {
        usort($pagamentos, fn (array $a, array $b): int => self::cmpByDateAsc($a, $b));
        $atual = [];
        $adiantamento = [];
        $cumC = 0;
        $totalC = (int) self::jsRound($totalFatura * 100);
        foreach ($pagamentos as $m) {
            $valorC = (int) self::jsRound(self::num($m['valor']) * 100);
            $restanteC = $totalC - $cumC;
            if ($restanteC <= 0) {
                $adiantamento[] = $m;
            } elseif ($valorC > $restanteC) {
                $atual[] = [...$m, 'valor' => $restanteC / 100];
                $adiantamento[] = [...$m, 'valor' => ($valorC - $restanteC) / 100];
            } else {
                $atual[] = $m;
            }
            $cumC += $valorC;
        }

        return ['atual' => $atual, 'adiantamento' => $adiantamento];
    }

    /**
     * Compras no crédito do mês agrupadas pela fatura em que caem.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array{total: float, rows: list<array<string, mixed>>}>
     */
    private function projecaoFatura(array $rows, string $ym): array
    {
        $groups = [];
        foreach ($rows as $m) {
            if (! self::isSaida($m) || ! self::has($m, 'Crédito')) {
                continue;
            }
            if (preg_match('/fatura/iu', (string) ($m['name'] ?? '')) === 1) {
                continue; // pagamento da fatura, não compra nova
            }
            $destino = self::faturaDestino($ym, self::dia($m));
            $groups[$destino] ??= ['total' => 0.0, 'rows' => []];
            $groups[$destino]['total'] += self::num($m['valor']);
            $groups[$destino]['rows'][] = $m;
        }

        return $groups;
    }

    // ───────────────────────── resumo de um mês

    /** @return array<string, mixed> */
    private function calcularResumoMes(string $ym, float $abertura): array
    {
        $mrows = $this->rowsDoMes($ym);

        $entradas = $saidasTotais = $entradasRealizadas = $saidasCaixaRealizadas = 0.0;
        $futuras = 0;
        foreach ($mrows as $m) {
            $v = self::num($m['valor']);
            $realizado = $this->isRealizado($m);
            if (! $realizado) {
                $futuras++;
            }
            if (self::isEntrada($m)) {
                $entradas += $v;
                if ($realizado) {
                    $entradasRealizadas += $v;
                }
            }
            if (self::isSaida($m)) {
                $saidasTotais += $v;
            }
            if ($this->isSaidaCaixa($m) && $realizado) {
                $saidasCaixaRealizadas += $v;
            }
        }
        $variacaoMes = $entradasRealizadas - $saidasCaixaRealizadas;

        $porMeio = function (callable $dir) use ($mrows): object {
            $out = [];
            $semMeio = 0.0;
            foreach ($mrows as $m) {
                if (! $dir($m)) {
                    continue;
                }
                $v = self::num($m['valor']);
                $achou = false;
                foreach ($this->meios as $me) {
                    if (self::has($m, $me)) {
                        $out[$me] = ($out[$me] ?? 0) + $v;
                        $achou = true;
                    }
                }
                if (! $achou) {
                    $semMeio += $v;
                }
            }
            if ($semMeio > 0) {
                $out['Sem meio'] = $semMeio;
            }
            foreach ($out as $k => $v) {
                $out[$k] = self::round2($v);
            }

            return (object) $out;
        };

        $porDia = [];
        foreach ($mrows as $m) {
            if (! $this->isRealizado($m)) {
                continue;
            }
            $delta = 0.0;
            if (self::isEntrada($m)) {
                $delta += self::num($m['valor']);
            }
            if ($this->isSaidaCaixa($m)) {
                $delta -= self::num($m['valor']);
            }
            $porDia[$m['date']] = ($porDia[$m['date']] ?? 0) + $delta;
        }
        $acc = $abertura;
        $saldoMinimo = ['valor' => $abertura, 'dia' => null];
        $dias = array_keys($porDia);
        sort($dias, SORT_STRING);
        foreach ($dias as $d) {
            $acc += $porDia[$d];
            if ($acc < $saldoMinimo['valor']) {
                $saldoMinimo = ['valor' => $acc, 'dia' => $d];
            }
        }

        // Cadeia de faturas (carryInto), com memo por mês.
        $memo = [];
        $carryInto = function (string $m) use (&$carryInto, &$memo): array {
            if (array_key_exists($m, $memo)) {
                return $memo[$m];
            }
            $rows = $this->rowsDoMes($m);
            $pagamentosFatura = array_values(array_filter($rows, fn (array $x): bool => $this->isPagamentoFatura($x)));
            if ($pagamentosFatura === []) {
                $res = [];
            } else {
                $explicitos = array_values(array_filter($pagamentosFatura, fn (array $x): bool => $this->isAdiantamentoExplicito($x)));
                $normais = array_values(array_filter($pagamentosFatura, fn (array $x): bool => ! $this->isAdiantamentoExplicito($x)));
                if ($normais === []) {
                    $res = $explicitos;
                } else {
                    $pm = self::prevMonth($m);
                    $g = $this->projecaoFatura($this->rowsDoMes($pm), $pm)[$m] ?? null;
                    $totalFaturaM = ($g !== null && $g['rows'] !== []) ? $g['total'] : 0.0;
                    $split = $this->splitPagamentos([...$normais, ...$carryInto($pm)], $totalFaturaM);
                    $res = [...$split['adiantamento'], ...$explicitos];
                }
            }

            return $memo[$m] = $res;
        };

        $pYm = self::prevMonth($ym);
        $pagamentosFaturaAtual = array_values(array_filter($mrows, fn (array $m): bool => $this->isPagamentoFatura($m)));
        $explicitosAtual = array_values(array_filter($pagamentosFaturaAtual, fn (array $m): bool => $this->isAdiantamentoExplicito($m)));
        $normaisAtual = array_values(array_filter($pagamentosFaturaAtual, fn (array $m): bool => ! $this->isAdiantamentoExplicito($m)));
        $gAtual = $this->projecaoFatura($this->rowsDoMes($pYm), $pYm)[$ym] ?? null;
        $totalFatura = ($gAtual !== null && $gAtual['rows'] !== []) ? $gAtual['total'] : 0.0;
        $pagamentosFatura = [...$normaisAtual, ...$carryInto($pYm)];
        $pago = array_sum(array_map(fn (array $m): float => self::num($m['valor']), $pagamentosFatura));
        $split = $this->splitPagamentos($pagamentosFatura, $totalFatura);
        $carryOut = [...$split['adiantamento'], ...$explicitosAtual];
        $excedente = self::round2(array_sum(array_map(fn (array $m): float => self::num($m['valor']), $carryOut)));

        $proj = $this->projecaoFatura($mrows, $ym);
        $destinos = array_keys($proj);
        sort($destinos, SORT_STRING);
        $faturaProjetada = [];
        foreach ($destinos as $i => $dest) {
            $faturaProjetada[] = [
                'fatura' => $dest,
                'total' => self::round2($proj[$dest]['total']),
                'compras' => count($proj[$dest]['rows']),
                ...($i === 0 && $excedente > 0 ? ['valor_apos_adiantamento' => self::round2(max(0, $proj[$dest]['total'] - $excedente))] : []),
            ];
        }

        // Recorrências (sem filtros), só as que repetem.
        $grupos = [];
        foreach ($mrows as $m) {
            $key = self::normName($m['name']) ?: '—';
            $grupos[$key] ??= ['descricao' => $m['name'] ?: '—', 'items' => []];
            $grupos[$key]['items'][] = $m;
        }
        $recorrencias = [];
        foreach ($grupos as $g) {
            $e = $s = 0.0;
            foreach ($g['items'] as $m) {
                if (self::isEntrada($m)) {
                    $e += self::num($m['valor']);
                }
                if (self::isSaida($m)) {
                    $s += self::num($m['valor']);
                }
            }
            $recorrencias[] = [
                'descricao' => $g['descricao'], 'vezes' => count($g['items']),
                'entradas' => self::round2($e), 'saidas' => self::round2($s), 'liquido' => self::round2($e - $s),
            ];
        }
        usort($recorrencias, fn (array $a, array $b): int => ($b['vezes'] <=> $a['vezes']) ?: (abs($b['liquido']) <=> abs($a['liquido'])));
        $recorrencias = array_values(array_filter($recorrencias, fn (array $g): bool => $g['vezes'] > 1));

        $saidas = array_values(array_filter($mrows, fn (array $m): bool => self::isSaida($m)));
        usort($saidas, fn (array $a, array $b): int => self::num($b['valor']) <=> self::num($a['valor']));
        $maioresSaidas = array_map(fn (array $m): array => [
            'data' => $m['date'], 'descricao' => $m['name'], 'valor' => self::round2(self::num($m['valor'])),
            'meio' => array_values(array_filter($this->meios, fn (string $me): bool => self::has($m, $me))),
        ], array_slice($saidas, 0, 5));

        return [
            'mes' => $ym,
            'movimentacoes' => count($mrows),
            'lancamentos_futuros' => $futuras,
            'entradas' => self::round2($entradas),
            'saidas_caixa' => self::round2($saidasCaixaRealizadas),
            'saidas_com_credito' => self::round2($saidasTotais),
            'saldo_abertura' => self::round2($abertura),
            'variacao_mes' => self::round2($variacaoMes),
            'saldo' => self::round2($abertura + $variacaoMes),
            'saldo_minimo' => ['valor' => self::round2($saldoMinimo['valor']), 'dia' => $saldoMinimo['dia']],
            'saidas_por_meio' => $porMeio(fn (array $m): bool => self::isSaida($m)),
            'entradas_por_meio' => $porMeio(fn (array $m): bool => self::isEntrada($m)),
            'fatura_deste_mes' => [
                'compras_de' => $pYm,
                'total' => self::round2($totalFatura),
                'compras' => $gAtual !== null ? count($gAtual['rows']) : 0,
                'pago' => self::round2(min($pago, $totalFatura)),
                'pagamentos' => count($split['atual']),
                'restante' => self::round2(max(0, $totalFatura - $pago)),
                'quitada' => $totalFatura > 0 && self::round2(max(0, $totalFatura - $pago)) <= 0,
                'adiantamento_para_proxima' => $excedente,
            ],
            'fatura_projetada' => $faturaProjetada,
            'recorrencias' => array_slice($recorrencias, 0, 8),
            'maiores_saidas' => $maioresSaidas,
        ];
    }

    // ───────────────────────── análise

    /** @param array<string, mixed> $m */
    private function ehFatura(array $m): bool
    {
        return self::isSaida($m) && str_contains($this->chave($m['name']), 'fatura');
    }

    /** @param array<string, mixed> $m */
    private function ehRateio(array $m): bool
    {
        return in_array($this->chave($m['name']), self::RATEIOS, true);
    }

    private function prepararAnalise(): void
    {
        $porMesQtd = [];
        foreach ($this->todas as $m) {
            $ym = substr((string) $m['date'], 0, 7);
            $porMesQtd[$ym] = ($porMesQtd[$ym] ?? 0) + 1;
        }
        $mesesOrdenados = array_keys($porMesQtd);
        sort($mesesOrdenados, SORT_STRING);
        $this->inicio = $this->mesHoje;
        foreach ($mesesOrdenados as $ym) {
            if ($porMesQtd[$ym] >= self::MIN_MOVIMENTACOES_INICIO) {
                $this->inicio = (string) $ym;
                break;
            }
        }

        foreach ($this->todas as $m) {
            $g = self::jsTrim((string) $m['name']);
            $k = $this->chave($m['name']);
            $this->grafias[$k][$g] = ($this->grafias[$k][$g] ?? 0) + 1;
        }

        $this->entradasGrandes = array_values(array_filter(
            $this->todas,
            fn (array $m): bool => self::isEntrada($m) && ! $this->ehRateio($m) && self::num($m['valor']) >= self::LIMIAR_ENTRADA_GRANDE,
        ));

        for ($m = $this->inicio; strcmp($m, $this->mesHoje) < 0; $m = self::nextMonth($m)) {
            if (count($this->janela($m)) === self::JANELA_COMPROMISSO) {
                $this->primeiroCheio = $m;
                break;
            }
        }
    }

    private function completo(string $ym): bool
    {
        return strcmp($ym, $this->inicio) >= 0 && strcmp($ym, $this->mesHoje) < 0;
    }

    /** A grafia de exibição mais usada da chave. */
    private function nomeDe(string $k): string
    {
        if (isset($this->nomes[$k])) {
            return $this->nomes[$k];
        }
        $melhor = null;
        $max = -1;
        foreach ($this->grafias[$k] ?? [] as $g => $n) {
            if ($n > $max) {
                $max = $n;
                $melhor = (string) $g;
            }
        }

        return $this->nomes[$k] = $melhor ?? $k;
    }

    /**
     * Os JANELA_COMPROMISSO meses completos mais recentes até ym; perto do início
     * dos dados, os que houver.
     *
     * @return list<string>
     */
    private function janela(string $ym): array
    {
        $out = [];
        $m = strcmp($ym, $this->mesHoje) < 0 ? $ym : self::prevMonth($this->mesHoje);
        for ($i = 0; $i < self::JANELA_COMPROMISSO && strcmp($m, $this->inicio) >= 0; $i++) {
            array_unshift($out, $m);
            $m = self::prevMonth($m);
        }

        return $out;
    }

    private function consumoMes(string $ym): float
    {
        $s = 0.0;
        foreach ($this->rowsDoMes($ym) as $m) {
            if (self::isSaida($m) && ! $this->ehFatura($m)) {
                $s += self::num($m['valor']);
            } elseif (self::isEntrada($m) && $this->ehRateio($m)) {
                $s -= self::num($m['valor']);
            }
        }

        return $s;
    }

    /**
     * Gasto variável acumulado até o dia: consumo sem os compromissos. `semPontuais`
     * é para as médias: um conserto de 1600 não é ritmo.
     *
     * @param  array<string, true>  $comp  chaves de compromisso
     */
    private function variavelAte(string $ym, int $dia, array $comp, bool $semPontuais = false): float
    {
        $fora = [];
        if ($semPontuais) {
            foreach ($this->pontuaisDe($ym) as $p) {
                $fora[$p['_i']] = true;
            }
        }
        $s = 0.0;
        foreach ($this->rowsDoMes($ym) as $m) {
            if (self::dia($m) > $dia || isset($fora[$m['_i']])) {
                continue;
            }
            if (self::isSaida($m) && ! $this->ehFatura($m) && ! isset($comp[$this->chave($m['name'])])) {
                $s += self::num($m['valor']);
            } elseif (self::isEntrada($m) && $this->ehRateio($m)) {
                $s -= self::num($m['valor']);
            }
        }

        return $s;
    }

    /**
     * Compromissos (saídas) ou renda recorrente (entradas) vistos de ym: chave
     * presente em TODOS os meses da janela, no máximo N vezes por mês.
     *
     * @return array<string, mixed>
     */
    private function recorrentes(string $ym, bool $entrada): array
    {
        $memoKey = $ym.($entrada ? 'E' : 'S');
        if (isset($this->recMemo[$memoKey])) {
            return $this->recMemo[$memoKey];
        }
        $propria = $this->janela($ym);
        $incompleta = count($propria) < self::JANELA_COMPROMISSO;
        $emprestada = $incompleta ? $this->primeiroCheio : null;
        $jan = $emprestada ? $this->janela($emprestada) : $propria;
        $elegivel = fn (array $m): bool => ($entrada ? self::isEntrada($m) : self::isSaida($m)) && ! $this->ehFatura($m) && ! $this->ehRateio($m);

        $st = [];
        foreach ($jan as $j) {
            foreach ($this->rowsDoMes($j) as $m) {
                if (! $elegivel($m)) {
                    continue;
                }
                $k = $this->chave($m['name']);
                $st[$k] ??= ['meses' => [], 'dias' => [], 'meios' => []];
                $st[$k]['meses'][$j] ??= ['qtd' => 0, 'soma' => 0.0];
                $st[$k]['meses'][$j]['qtd']++;
                $st[$k]['meses'][$j]['soma'] += self::num($m['valor']);
                $st[$k]['dias'][] = self::dia($m);
                $me = $this->meioStr($m) ?? '';
                $st[$k]['meios'][$me] = ($st[$k]['meios'][$me] ?? 0) + 1;
            }
        }
        $lancado = [];
        foreach ($this->rowsDoMes($ym) as $m) {
            if ($elegivel($m)) {
                $k = $this->chave($m['name']);
                $lancado[$k] = ($lancado[$k] ?? 0) + self::num($m['valor']);
            }
        }

        $chaves = [];
        if (count($jan) >= 2) {
            foreach (array_keys($st) as $k) {
                $k = (string) $k;
                $todos = true;
                foreach ($jan as $j) {
                    if (! isset($st[$k]['meses'][$j]) || $st[$k]['meses'][$j]['qtd'] > self::MAX_OCORRENCIAS_COMPROMISSO) {
                        $todos = false;
                        break;
                    }
                }
                if ($todos) {
                    $chaves[] = $k;
                }
            }
        }
        if (! $entrada && count($jan) >= 2) {
            foreach (self::COMPROMISSOS_FORCAR as $k) {
                if (isset($st[$k]) && ! in_array($k, $chaves, true)) {
                    $chaves[] = $k;
                }
            }
            $chaves = array_values(array_filter($chaves, fn (string $k): bool => ! in_array($k, self::COMPROMISSOS_IGNORAR, true)));
        }

        $somasDe = fn (string $k): array => isset($st[$k])
            ? array_values(array_map(fn (string $j): float => $st[$k]['meses'][$j]['soma'], array_filter($jan, fn (string $j): bool => isset($st[$k]['meses'][$j]))))
            : [];
        $meioHist = function (string $k) use ($st): ?string {
            if (! isset($st[$k])) {
                return null;
            }
            $melhor = null;
            $max = -1;
            foreach ($st[$k]['meios'] as $me => $n) {
                if ($n > $max) {
                    $max = $n;
                    $melhor = (string) $me;
                }
            }

            return $melhor ?: null;
        };
        $diaHist = fn (string $k): ?int => isset($st[$k]) ? (int) self::jsRound(self::mediana($st[$k]['dias'])) : null;

        $heuristica = [];
        foreach ($chaves as $k) {
            $somas = $somasDe($k);
            $tipico = $entrada ? $somas[count($somas) - 1] : self::mediana($somas);
            $variacao = false;
            foreach ($somas as $x) {
                if (abs($x - $tipico) > self::TOLERANCIA_COMPROMISSO * $tipico) {
                    $variacao = true;
                    break;
                }
            }
            $heuristica[] = [
                'chave' => $k,
                'nome' => $this->nomeDe($k),
                'valor_tipico' => self::round2($tipico),
                'dia_tipico' => $diaHist($k),
                'meio_tipico' => $meioHist($k),
                'variacao_alta' => $variacao,
                'ja_lancado' => array_key_exists($k, $lancado),
                'valor_lancado' => self::round2($lancado[$k] ?? 0),
                'origem' => 'heuristica',
            ];
        }
        usort($heuristica, fn (array $a, array $b): int => $b['valor_tipico'] <=> $a['valor_tipico']);

        // Cadastro manda, por direção; a heurística vira sugestão.
        $cad = $this->cadastroPorDirecao[$entrada ? 'Entrada' : 'Saida'];
        $itens = $heuristica;
        $sugestoes = null;
        if ($cad !== []) {
            $itens = [];
            foreach ($cad as $c) {
                $k = $this->chave($c['nome']);
                $somas = $somasDe($k);
                $min = $c['valor_min'];
                $max = $c['valor_max'];
                $fora = false;
                foreach ($somas as $x) {
                    if ($x < $min * (1 - self::TOLERANCIA_COMPROMISSO) || $x > $max * (1 + self::TOLERANCIA_COMPROMISSO)) {
                        $fora = true;
                        break;
                    }
                }
                $itens[] = [
                    'chave' => $k,
                    'nome' => $c['nome'],
                    'valor_tipico' => self::round2(($min + $max) / 2),
                    'valor_min' => self::round2($min),
                    'valor_max' => self::round2($max),
                    'dia_tipico' => $c['dia'] ?? $diaHist($k),
                    'meio_tipico' => $c['meio'] ?? $meioHist($k),
                    'variacao_alta' => $fora,
                    'ja_lancado' => array_key_exists($k, $lancado),
                    'valor_lancado' => self::round2($lancado[$k] ?? 0),
                    'origem' => 'cadastro',
                ];
            }
            usort($itens, fn (array $a, array $b): int => $b['valor_tipico'] <=> $a['valor_tipico']);
            $cadChaves = array_column($itens, 'chave');
            $sugestoes = array_map(
                fn (array $i): array => self::semChave($i),
                array_values(array_filter($heuristica, fn (array $i): bool => ! in_array($i['chave'], $cadChaves, true))),
            );
        }

        $resultado = [
            'itens' => $itens,
            'total' => self::round2(self::soma(array_column($itens, 'valor_tipico'))),
            'fonte' => $cad !== [] ? 'cadastro' : 'heuristica',
            'sugestoes' => $sugestoes,
            'janela' => $jan,
            'janela_incompleta' => $incompleta,
            'janela_emprestada_de' => $emprestada,
        ];
        if ($cad !== []) {
            $resultado['total_min'] = self::round2(self::soma(array_map(fn (array $i): float => $i['valor_min'] ?? $i['valor_tipico'], $itens)));
            $resultado['total_max'] = self::round2(self::soma(array_map(fn (array $i): float => $i['valor_max'] ?? $i['valor_tipico'], $itens)));
        }

        return $this->recMemo[$memoKey] = $resultado;
    }

    /** @param array<string, mixed> $i @return array<string, mixed> */
    private static function semChave(array $i): array
    {
        unset($i['chave']);

        return $i;
    }

    /** @param array<string, mixed> $i */
    private static function ehCredito(array $i): bool
    {
        return in_array('Crédito', explode('+', (string) ($i['meio_tipico'] ?? '')), true);
    }

    /**
     * Pontuais: saída grande cujo nome não aparece nos 3 meses anteriores (dentro
     * dos dados). Compromisso (inclusive forçado) nunca é pontual.
     *
     * @return list<array<string, mixed>>
     */
    private function pontuaisDe(string $ym): array
    {
        if (isset($this->pontMemo[$ym])) {
            return $this->pontMemo[$ym];
        }
        $comp = array_flip(array_column($this->recorrentes($ym, false)['itens'], 'chave'));
        $ref = [];
        for ($p = self::prevMonth($ym); count($ref) < 3 && strcmp($p, $this->inicio) >= 0; $p = self::prevMonth($p)) {
            $ref[] = $p;
        }
        for ($p = self::nextMonth($ym); count($ref) < 3 && $this->completo($p); $p = self::nextMonth($p)) {
            $ref[] = $p;
        }
        $vistas = [];
        foreach ($ref as $p) {
            foreach ($this->rowsDoMes($p) as $m) {
                $vistas[$this->chave($m['name'])] = true;
            }
        }

        return $this->pontMemo[$ym] = array_values(array_filter($this->rowsDoMes($ym), function (array $m) use ($comp, $vistas): bool {
            $k = $this->chave($m['name']);

            return self::isSaida($m) && ! $this->ehFatura($m) && ! $this->ehRateio($m) && ! isset($comp[$k])
                && self::num($m['valor']) >= self::LIMIAR_PONTUAL && ! isset($vistas[$k]);
        }));
    }

    /** @param list<string> $jan @param array<string, true> $comp */
    private function mediaDiariaJanela(array $jan, array $comp): float
    {
        return $jan !== []
            ? self::soma(array_map(fn (string $j): float => $this->variavelAte($j, 31, $comp, true), $jan)) / self::soma(array_map(fn (string $j): int => self::lastDay($j), $jan))
            : 0.0;
    }

    /**
     * @param  array{porNome: bool, lista: bool}  $opts
     * @return array<string, mixed>
     */
    private function analisarMes(string $ym, array $opts): array
    {
        $r2 = fn (float|int $v): float => self::round2($v);
        $rows = $this->rowsDoMes($ym);
        $comp = $this->recorrentes($ym, false);
        $renda = $this->recorrentes($ym, true);
        $setComp = array_fill_keys(array_column($comp['itens'], 'chave'), true);
        $consumo = $this->consumoMes($ym);

        // Rateios e entradas próprias.
        $rateiosK = [];
        $entradasK = [];
        $entradas = $entradasRateio = 0.0;
        foreach ($rows as $m) {
            $v = self::num($m['valor']);
            $k = $this->chave($m['name']);
            if (self::isEntrada($m)) {
                $entradas += $v;
            }
            if ($this->ehRateio($m)) {
                $rateiosK[$k] ??= ['entrou' => 0.0, 'saiu' => 0.0];
                if (self::isEntrada($m)) {
                    $rateiosK[$k]['entrou'] += $v;
                    $entradasRateio += $v;
                }
                if (self::isSaida($m)) {
                    $rateiosK[$k]['saiu'] += $v;
                }
            } elseif (self::isEntrada($m)) {
                $entradasK[$k] = ($entradasK[$k] ?? 0) + $v;
            }
        }
        $rateios = [];
        foreach ($rateiosK as $k => $r) {
            $rateios[$this->nomeDe((string) $k)] = ['entrou' => $r2($r['entrou']), 'saiu' => $r2($r['saiu']), 'custo_dono' => $r2($r['saiu'] - $r['entrou'])];
        }
        $ordenadas = $entradasK;
        uasort($ordenadas, fn ($a, $b): int => $b <=> $a);
        $entradasPorNome = [];
        foreach ($ordenadas as $k => $v) {
            $entradasPorNome[$this->nomeDe((string) $k)] = $r2($v);
        }

        // Por nome (só no modo mês único).
        $porNome = null;
        if ($opts['porNome']) {
            $g = [];
            foreach ($rows as $m) {
                if ($this->ehFatura($m)) {
                    continue;
                }
                $k = $this->chave($m['name']);
                $g[$k] ??= ['e' => 0.0, 's' => 0.0, 'qe' => 0, 'qs' => 0, 'meios' => []];
                $v = self::num($m['valor']);
                if (self::isEntrada($m)) {
                    $g[$k]['e'] += $v;
                    $g[$k]['qe']++;
                }
                if (self::isSaida($m)) {
                    $g[$k]['s'] += $v;
                    $g[$k]['qs']++;
                }
                foreach ($this->meios as $me) {
                    if (self::has($m, $me)) {
                        $g[$k]['meios'][$me] = true;
                    }
                }
            }
            $porNome = [];
            foreach ($g as $k => $x) {
                $porNome[] = [
                    'nome' => $this->nomeDe((string) $k), 'entradas' => $r2($x['e']), 'saidas' => $r2($x['s']), 'liquido' => $r2($x['e'] - $x['s']),
                    'qtd_entradas' => $x['qe'], 'qtd_saidas' => $x['qs'],
                    'ticket_medio' => $x['qs'] ? $r2($x['s'] / $x['qs']) : null, 'meios' => array_keys($x['meios']),
                ];
            }
            usort($porNome, fn (array $a, array $b): int => ($b['saidas'] <=> $a['saidas']) ?: ($b['entradas'] <=> $a['entradas']));
        }

        // Sinais.
        $ehVariavel = fn (array $m): bool => self::isSaida($m) && ! $this->ehFatura($m) && ! $this->ehRateio($m) && ! isset($setComp[$this->chave($m['name'])]);
        $diasAcc = [];
        foreach ($rows as $m) {
            if ($ehVariavel($m)) {
                $diasAcc[$m['date']] ??= ['total' => 0.0, 'itens' => []];
                $diasAcc[$m['date']]['total'] += self::num($m['valor']);
                $diasAcc[$m['date']]['itens'][] = ['nome' => self::jsTrim((string) $m['name']), 'valor' => $r2(self::num($m['valor']))];
            }
        }
        uasort($diasAcc, fn (array $a, array $b): int => $b['total'] <=> $a['total']);
        $picos = [];
        foreach (array_slice($diasAcc, 0, 5, true) as $data => $d) {
            $itens = $d['itens'];
            usort($itens, fn (array $a, array $b): int => $b['valor'] <=> $a['valor']);
            $apos = false;
            foreach ($this->entradasGrandes as $e) {
                if (strcmp($e['date'], self::somaDias((string) $data, -2)) >= 0 && strcmp($e['date'], (string) $data) <= 0) {
                    $apos = true;
                    break;
                }
            }
            $picos[] = ['data' => (string) $data, 'total' => $r2($d['total']), 'itens' => $itens, 'apos_entrada' => $apos];
        }
        $tickets = [];
        foreach ($rows as $m) {
            if (self::isSaida($m) && ! $this->ehFatura($m)) {
                $k = $this->chave($m['name']);
                $id = $k.'|'.(int) self::jsRound(self::num($m['valor']) * 100);
                $tickets[$id] ??= ['k' => $k, 'valor' => self::num($m['valor']), 'vezes' => 0];
                $tickets[$id]['vezes']++;
            }
        }
        $ticketsRepetidos = [];
        foreach ($tickets as $t) {
            if ($t['vezes'] >= self::MIN_REPETICOES_TICKET) {
                $ticketsRepetidos[] = ['nome' => $this->nomeDe($t['k']), 'valor' => $r2($t['valor']), 'vezes' => $t['vezes'], 'total' => $r2($t['valor'] * $t['vezes'])];
            }
        }
        usort($ticketsRepetidos, fn (array $a, array $b): int => ($b['vezes'] <=> $a['vezes']) ?: ($b['total'] <=> $a['total']));
        $pontuais = array_map(fn (array $m): array => ['data' => $m['date'], 'nome' => self::jsTrim((string) $m['name']), 'valor' => $r2(self::num($m['valor']))], $this->pontuaisDe($ym));

        // Ritmo (só no mês corrente).
        $ritmo = null;
        if ($ym === $this->mesHoje) {
            $jan = $this->janela($ym);
            $diasNoMes = self::lastDay($ym);
            $ate = $this->variavelAte($ym, $this->diaHoje, $setComp);
            $ateSem = $this->variavelAte($ym, $this->diaHoje, $setComp, true);
            $mediaDiaria = $ateSem / $this->diaHoje;
            $mesmoDia = $jan !== []
                ? self::soma(array_map(fn (string $j): float => $this->variavelAte($j, min($this->diaHoje, self::lastDay($j)), $setComp, true), $jan)) / count($jan)
                : null;
            $ritmo = [
                'dias_decorridos' => $this->diaHoje,
                'dias_no_mes' => $diasNoMes,
                'variavel_ate_hoje' => $r2($ate),
                'variavel_ate_hoje_sem_pontuais' => $r2($ateSem),
                'media_mesmo_dia' => $mesmoDia !== null ? $r2($mesmoDia) : null,
                'desvio_pct' => $mesmoDia ? self::jsRound(($ateSem - $mesmoDia) / $mesmoDia * 1000) / 10 : null,
                'media_diaria' => $r2($mediaDiaria),
                'media_diaria_janela' => $jan !== [] ? $r2($this->mediaDiariaJanela($jan, $setComp)) : null,
                'media_mes_janela_sem_pontuais' => $jan !== []
                    ? $r2(self::soma(array_map(fn (string $j): float => $this->variavelAte($j, 31, $setComp, true), $jan)) / count($jan))
                    : null,
                'fechamento_projetado' => $r2($ate + $mediaDiaria * ($diasNoMes - $this->diaHoje)),
                'fechamento_sem_pontuais' => $r2($mediaDiaria * $diasNoMes),
            ];
        }

        // Consumo e variável de cada dia.
        $ultimoDia = $ym === $this->mesHoje ? $this->diaHoje : self::lastDay($ym);
        $acc = array_fill(0, $ultimoDia, ['consumo' => 0.0, 'variavel' => 0.0]);
        foreach ($rows as $m) {
            $idx = self::dia($m) - 1;
            if (! isset($acc[$idx])) {
                continue;
            }
            $v = self::num($m['valor']);
            if (self::isSaida($m) && ! $this->ehFatura($m)) {
                $acc[$idx]['consumo'] += $v;
            } elseif (self::isEntrada($m) && $this->ehRateio($m)) {
                $acc[$idx]['consumo'] -= $v;
            }
            if (self::isSaida($m) && ! $this->ehFatura($m) && ! isset($setComp[$this->chave($m['name'])])) {
                $acc[$idx]['variavel'] += $v;
            } elseif (self::isEntrada($m) && $this->ehRateio($m)) {
                $acc[$idx]['variavel'] -= $v;
            }
        }
        $porDia = [];
        foreach ($acc as $i => $d) {
            $porDia[] = ['data' => $ym.'-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT), 'consumo' => $r2($d['consumo']), 'variavel' => $r2($d['variavel'])];
        }

        $lista = null;
        if ($opts['lista']) {
            $ordenadas = $rows;
            usort($ordenadas, fn (array $a, array $b): int => self::cmpByDateAsc($a, $b));
            $lista = [
                'colunas' => ['id', 'data', 'nome', 'valor', 'direcao', 'meio', 'marca'],
                'linhas' => array_map(fn (array $m): array => [
                    $m['id'] ?? null, $m['date'], $m['name'], $r2(self::num($m['valor'])),
                    self::isEntrada($m) ? 'Entrada' : (self::isSaida($m) ? 'Saida' : null),
                    $this->meioStr($m),
                    $this->ehFatura($m) ? 'fatura'
                        : ($this->ehRateio($m) ? 'rateio'
                        : (self::isSaida($m) && isset($setComp[$this->chave($m['name'])]) ? 'compromisso'
                        : (! $this->isRealizado($m) ? 'futuro' : null))),
                ], $ordenadas),
            ];
        }

        return [
            'parcial' => $ym === $this->mesHoje,
            'futuro' => strcmp($ym, $this->mesHoje) > 0,
            'consumo_mes' => $r2($consumo),
            'variavel_mes' => $r2($this->variavelAte($ym, 31, $setComp)),
            'entradas_proprias' => $r2($entradas - $entradasRateio),
            'entradas_por_nome' => (object) $entradasPorNome,
            'rateios' => (object) $rateios,
            ...($porNome !== null ? ['por_nome' => $porNome] : []),
            'compromissos' => [
                'fonte' => $comp['fonte'],
                'total' => $comp['total'],
                ...(isset($comp['total_min']) ? ['total_min' => $comp['total_min'], 'total_max' => $comp['total_max']] : []),
                'pct_renda' => $renda['total'] > 0 ? $r2($comp['total'] / $renda['total'] * 100) : null,
                'janela' => $comp['janela'],
                'janela_incompleta' => $comp['janela_incompleta'],
                'janela_emprestada_de' => $comp['janela_emprestada_de'],
                'itens' => array_map(fn (array $i): array => self::semChave($i), $comp['itens']),
                ...($comp['sugestoes'] !== null ? ['sugestoes' => $comp['sugestoes']] : []),
            ],
            'renda_recorrente' => [
                'fonte' => $renda['fonte'],
                'total' => $renda['total'],
                ...(isset($renda['total_min']) ? ['total_min' => $renda['total_min'], 'total_max' => $renda['total_max']] : []),
                'itens' => array_map(fn (array $i): array => self::semChave($i), $renda['itens']),
                ...($renda['sugestoes'] !== null ? ['sugestoes' => $renda['sugestoes']] : []),
            ],
            'sinais' => [
                'picos' => $picos,
                'tickets_repetidos' => $ticketsRepetidos,
                'pontuais' => $pontuais,
                'consumo_base' => $r2($consumo - self::soma(array_column($pontuais, 'valor'))),
            ],
            ...($ritmo !== null ? ['ritmo' => $ritmo] : []),
            'por_dia' => $porDia,
            ...($lista !== null ? ['lista_movimentacoes' => $lista] : []),
        ];
    }

    /**
     * Uma linha por (nome, direção); média só dos meses completos.
     *
     * @param  list<string>  $meses
     * @return list<array<string, mixed>>
     */
    private function comparativoPorNome(array $meses): array
    {
        $acc = [];
        foreach ($meses as $ym) {
            foreach ($this->rowsDoMes($ym) as $m) {
                if ($this->ehFatura($m)) {
                    continue;
                }
                $dir = self::isEntrada($m) ? 'Entrada' : (self::isSaida($m) ? 'Saida' : null);
                if ($dir === null) {
                    continue;
                }
                $k = $this->chave($m['name']);
                $acc[$k.'|'.$dir] ??= ['k' => $k, 'dir' => $dir, 'v' => []];
                $acc[$k.'|'.$dir]['v'][$ym] = ($acc[$k.'|'.$dir]['v'][$ym] ?? 0) + self::num($m['valor']);
            }
        }
        $comps = array_values(array_filter($meses, fn (string $ym): bool => $this->completo($ym)));
        $linhas = [];
        foreach ($acc as $a) {
            $valores = [];
            foreach ($meses as $ym) {
                $valores[$ym] = self::round2($a['v'][$ym] ?? 0);
            }
            $linhas[] = [
                'nome' => $this->nomeDe($a['k']), 'direcao' => $a['dir'], 'valores' => (object) $valores,
                'media' => $comps !== [] ? self::round2(self::soma(array_map(fn (string $ym): float => $a['v'][$ym] ?? 0, $comps)) / count($comps)) : null,
            ];
        }
        usort($linhas, fn (array $a, array $b): int => ($b['media'] ?? -1) <=> ($a['media'] ?? -1));

        return $linhas;
    }

    /**
     * Os 2 meses seguintes ao corrente. Lançado (inclusive com data futura)
     * substitui o típico, nunca soma.
     *
     * @return array<string, mixed>
     */
    private function projecao(float $aberturaHoje): array
    {
        $r2 = fn (float|int $v): float => self::round2($v);
        $mesHoje = $this->mesHoje;
        $comp = $this->recorrentes($mesHoje, false);
        $renda = $this->recorrentes($mesHoje, true);
        $setComp = array_fill_keys(array_column($comp['itens'], 'chave'), true);

        $lancadoEm = function (string $ym, array $i, bool $entrada): ?float {
            $soma = 0.0;
            $achou = false;
            foreach ($this->rowsDoMes($ym) as $m) {
                if (! ($entrada ? self::isEntrada($m) : self::isSaida($m)) || $this->ehFatura($m) || $this->chave($m['name']) !== $i['chave']) {
                    continue;
                }
                $soma += self::num($m['valor']);
                $achou = true;
            }

            return $achou ? $soma : null;
        };

        $rHoje = $this->calcularResumoMes($mesHoje, $aberturaHoje);
        $futuros = 0.0;
        foreach ($this->rowsDoMes($mesHoje) as $m) {
            if ($this->isRealizado($m)) {
                continue;
            }
            if (self::isEntrada($m)) {
                $futuros += self::num($m['valor']);
            }
            if ($this->isSaidaCaixa($m)) {
                $futuros -= self::num($m['valor']);
            }
        }

        // Cenários: com faixa, o pessimista usa a saída no máximo e a entrada no
        // mínimo; o otimista, o contrário; o provável, o meio da faixa.
        $val = fn (array $i, bool $entrada, string $cen): float => ($cen === 'provavel' || ! array_key_exists('valor_min', $i))
            ? $i['valor_tipico']
            : ((($cen === 'pessimista') === $entrada) ? $i['valor_min'] : $i['valor_max']);
        $temFaixa = false;
        foreach ([...$comp['itens'], ...$renda['itens']] as $i) {
            if (array_key_exists('valor_min', $i) && $i['valor_min'] !== $i['valor_max']) {
                $temFaixa = true;
            }
        }

        $faturaRestante = $rHoje['fatura_deste_mes']['restante'];
        $jan = $this->janela($mesHoje);
        $mediaDiaria = $this->mediaDiariaJanela($jan, $setComp);
        $diasRestantes = self::lastDay($mesHoje) - $this->diaHoje;
        $variavelEstimado = $mediaDiaria * $diasRestantes;
        $saldoFimDe = function (string $cen) use ($renda, $comp, $val, $rHoje, $futuros, $faturaRestante, $variavelEstimado): array {
            $rendaPendente = self::soma(array_map(fn (array $i): float => $val($i, true, $cen), array_filter($renda['itens'], fn (array $i): bool => ! $i['ja_lancado'])));
            $compPendentes = self::soma(array_map(fn (array $i): float => $val($i, false, $cen), array_filter($comp['itens'], fn (array $i): bool => ! self::ehCredito($i) && ! $i['ja_lancado'])));

            return [
                'rendaPendente' => $rendaPendente, 'compPendentes' => $compPendentes,
                'saldoFim' => $rHoje['saldo'] + $futuros + $rendaPendente - $compPendentes - $faturaRestante - $variavelEstimado,
            ];
        };

        $restanteEm = function (string $ym): float {
            return $this->faturaRestanteEm[$ym] ??= $this->calcularResumoMes($ym, 0)['fatura_deste_mes']['restante'];
        };

        $mesProj = function (string $ym, float $saldoInicial, string $cen) use ($renda, $comp, $val, $lancadoEm, $restanteEm, $r2): array {
            $detalhe = [];
            $add = function (string $grupo, string $nome, ?float $lancado, float $tipico, ?array $i = null) use (&$detalhe, $r2): float {
                $valor = $r2($lancado ?? $tipico);
                $linha = ['grupo' => $grupo, 'nome' => $nome, 'valor' => $valor, 'origem' => $lancado !== null ? 'lancado' : 'tipico'];
                if ($lancado === null && $i !== null && array_key_exists('valor_min', $i) && $i['valor_min'] !== $i['valor_max']) {
                    $linha['valor_min'] = $i['valor_min'];
                    $linha['valor_max'] = $i['valor_max'];
                }
                $detalhe[] = $linha;

                return $valor;
            };
            $rendaPrev = $compCaixa = 0.0;
            foreach ($renda['itens'] as $i) {
                $rendaPrev += $add('renda', $i['nome'], $lancadoEm($ym, $i, true), $val($i, true, $cen), $i);
            }
            foreach ($comp['itens'] as $i) {
                if (! self::ehCredito($i)) {
                    $compCaixa += $add('compromisso_caixa', $i['nome'], $lancadoEm($ym, $i, false), $val($i, false, $cen), $i);
                }
            }
            $pm = self::prevMonth($ym);
            $fatura = $add('fatura', "Fatura (compras de {$pm})", $restanteEm($ym), 0.0);
            foreach ($comp['itens'] as $i) {
                if (self::ehCredito($i) && $lancadoEm($pm, $i, false) === null) {
                    $fatura += $add('fatura', $i['nome'], null, $val($i, false, $cen), $i);
                }
            }
            $livre = $saldoInicial + $rendaPrev - $compCaixa - $fatura;
            $diasNoMes = self::lastDay($ym);

            return [
                'mes' => $ym, 'dias_no_mes' => $diasNoMes, 'saldo_inicial' => $r2($saldoInicial),
                'renda_prevista' => $r2($rendaPrev), 'compromissos_caixa' => $r2($compCaixa), 'fatura_a_pagar' => $r2($fatura),
                'livre_para_variavel' => $r2($livre), 'livre_por_dia' => $r2($livre / $diasNoMes), 'detalhe' => $detalhe,
            ];
        };

        $p1 = self::nextMonth($mesHoje);
        $p2 = self::nextMonth($p1);
        $prov = $saldoFimDe('provavel');
        $cenario = function (string $cen) use ($saldoFimDe, $mesProj, $p1, $p2, $r2): array {
            $s = $saldoFimDe($cen);

            return [
                'saldo_fim_do_mes' => $r2($s['saldoFim']),
                'meses' => array_map(function (array $m): array {
                    unset($m['detalhe']);

                    return $m;
                }, [$mesProj($p1, $s['saldoFim'], $cen), $mesProj($p2, 0.0, $cen)]),
            ];
        };
        $fonte = $comp['fonte'] === $renda['fonte'] ? $comp['fonte'] : "compromissos: {$comp['fonte']}, renda: {$renda['fonte']}";

        return [
            'base' => $mesHoje,
            'fonte' => $fonte,
            'janela' => $jan,
            'janela_incompleta' => $comp['janela_incompleta'],
            'composicao_saldo_inicial' => [
                'saldo_hoje' => $r2($rHoje['saldo']),
                'lancamentos_futuros_do_mes' => $r2($futuros),
                'renda_pendente' => $r2($prov['rendaPendente']),
                'compromissos_pendentes' => $r2($prov['compPendentes']),
                'fatura_restante' => $r2($faturaRestante),
                'variavel_estimado' => $r2($variavelEstimado),
                'saldo_fim_do_mes' => $r2($prov['saldoFim']),
            ],
            'meses' => [$mesProj($p1, $prov['saldoFim'], 'provavel'), $mesProj($p2, 0.0, 'provavel')],
            ...($temFaixa ? ['cenarios' => ['pessimista' => $cenario('pessimista'), 'otimista' => $cenario('otimista')]] : []),
            'premissas' => [
                $comp['fonte'] === 'cadastro' || $renda['fonte'] === 'cadastro'
                    ? 'Recorrências do cadastro (Finanças, Recorrências previstas) onde houver, por direção; o que a heurística acha e não está cadastrado vem em sugestoes, fora das contas.'
                    : 'Sem recorrências cadastradas: compromissos e renda vêm da janela '.implode(', ', $jan).', nomes presentes em todos esses meses, no máximo '.self::MAX_OCORRENCIAS_COMPROMISSO.' vezes por mês.',
                'Valor típico: no cadastro, o meio da faixa; na heurística, a renda do mês completo mais recente (reajuste é degrau) e a mediana da janela pros compromissos.',
                'O que já está lançado no mês (inclusive com data futura) substitui o valor típico, nunca soma os dois.',
                "saldo_inicial de {$p1} = saldo de hoje + lançamentos futuros de {$mesHoje} + renda recorrente ainda não lançada - compromissos de caixa ainda não lançados - restante da fatura de {$mesHoje} - gasto variável médio da janela sem pontuais (".\Modules\Core\Support\Mcp\Args::str($r2($mediaDiaria))."/dia) nos {$diasRestantes} dias que faltam.",
                "saldo_inicial de {$p2} = 0: supõe que o livre de {$p1} é gasto.",
                'Compromissos no Crédito não saem do caixa no mês: entram na fatura do mês seguinte.',
                ...($temFaixa ? ['cenarios: pessimista = saídas no máximo e entradas no mínimo da faixa; otimista = o contrário. Os campos principais são o provável.'] : []),
                'Lançamentos avulsos já agendados nos meses projetados não entram, exceto compras no Crédito, que já estão na fatura.',
            ],
        ];
    }
}
