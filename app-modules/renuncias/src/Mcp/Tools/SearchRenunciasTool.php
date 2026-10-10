<?php

declare(strict_types=1);

namespace Modules\Renuncias\Mcp\Tools;

use Carbon\CarbonImmutable;
use DateTimeZone;
use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Core\Support\Mcp\Args;
use Modules\Core\Support\Mcp\Resultado;
use Modules\Core\Support\Mcp\ToolPorArquivo;
use Modules\Renuncias\Actions\BuscarRenuncias;
use Modules\Renuncias\Models\Renuncia;
use Modules\Renuncias\Models\Tentativa;

/**
 * Só leitura (criar, editar e recaída são da tela). A escala de marcos é CÓPIA de
 * MARCOS em assets/js/renuncias.js: mudou lá, muda aqui, senão a IA e a tela
 * discordam do "próximo marco".
 */
final class SearchRenunciasTool extends ToolPorArquivo
{
    private const MARCOS = [
        [1, '1 dia'], [3, '3 dias'], [7, '7 dias'], [14, '14 dias'], [21, '21 dias'], [30, '1 mês'],
        [60, '2 meses'], [90, '3 meses'], [180, '6 meses'], [270, '9 meses'], [365, '1 ano'], [730, '2 anos'],
        [1095, '3 anos'], [1825, '5 anos'], [3650, '10 anos'],
    ];

    private const DIA_MS = 86_400_000;

    public function __construct(VocabularioReadModel $vocabulario, private readonly BuscarRenuncias $buscar)
    {
        parent::__construct($vocabulario);
    }

    public function ordem(): int
    {
        return 14;
    }

    protected function arquivo(): string
    {
        return dirname(__DIR__, 3).'/resources/mcp/search_renuncias.json';
    }

    public function chamar(array $args): array
    {
        $r = $this->buscar->execute([
            'nome' => Args::textoOpcional($args, 'nome'),
            'incluir_arquivadas' => ($args['incluir_arquivadas'] ?? null) === true,
            'limit' => Args::limite($args['limit'] ?? null),
        ]);
        $comHistorico = ($args['historico'] ?? null) === true;
        $agora = (int) (CarbonImmutable::now()->getTimestampMs());

        $devolvidas = $r['renuncias']->map(fn (Renuncia $ren): array => $this->resumo($ren, $agora, $comHistorico))->all();

        return Resultado::json([
            'consultado_em' => $this->hora($agora).' (America/Sao_Paulo)',
            'total_matches' => $r['total'], 'returned' => count($devolvidas), 'truncated' => $r['total'] > count($devolvidas), 'renuncias' => $devolvidas,
        ]);
    }

    /** @return array<string, mixed> */
    private function resumo(Renuncia $ren, int $agora, bool $comHistorico): array
    {
        $desde = $ren->ren_desde->getTimestampMs();
        $ms = max(0, $agora - $desde);
        $tents = $ren->tentativas->map(fn (Tentativa $t): array => ['inicio' => $t->rtn_inicio->getTimestampMs(), 'fim' => $t->rtn_fim->getTimestampMs()])
            ->sortByDesc('fim')->values()->all();
        $melhorAnterior = array_reduce($tents, fn (int $m, array $t): int => max($m, $t['fim'] - $t['inicio']), 0);
        $historico = fn (): array => array_map(fn (array $t): array => [
            'inicio' => $this->hora($t['inicio']), 'fim' => $this->hora($t['fim']), 'duracao' => $this->duracao($t['fim'] - $t['inicio']),
        ], $tents);

        // Arquivada encerra a contagem: o `desde` dela não é abstinência.
        if ($ren->ren_arquivada) {
            return [
                'nome' => $ren->ren_nome, 'emoji' => $ren->ren_emoji, 'arquivada' => true,
                'contagem' => 'encerrada — arquivada, não conta tempo nem marcos',
                'recaidas' => count($tents),
                'recorde' => $melhorAnterior > 0 ? $this->duracao($melhorAnterior) : null,
                ...($comHistorico ? ['historico' => $historico()] : []),
            ];
        }

        $conquistados = array_values(array_filter(self::MARCOS, fn (array $m): bool => $ms >= $m[0] * self::DIA_MS));
        $proximo = null;
        foreach (self::MARCOS as $m) {
            if ($ms < $m[0] * self::DIA_MS) {
                $proximo = $m;
                break;
            }
        }
        $recorde = max($ms, $melhorAnterior);

        return [
            'nome' => $ren->ren_nome,
            'emoji' => $ren->ren_emoji,
            'arquivada' => $ren->ren_arquivada,
            'desde' => $this->hora($desde),
            'tempo_sem' => $this->duracao($ms),
            'dias_sem' => intdiv($ms, self::DIA_MS),
            'ultimo_marco' => $conquistados !== [] ? $conquistados[count($conquistados) - 1][1] : null,
            'marcos_conquistados' => count($conquistados).'/'.count(self::MARCOS),
            'proximo_marco' => $proximo === null ? null : [
                'marco' => $proximo[1],
                'faltam' => $this->duracao($proximo[0] * self::DIA_MS - $ms),
                'chega_em' => $this->hora($desde + $proximo[0] * self::DIA_MS),
                'progresso_pct' => (int) round(($ms / ($proximo[0] * self::DIA_MS)) * 100),
            ],
            'recaidas' => count($tents),
            'recorde' => $this->duracao($recorde),
            'recorde_e_a_atual' => $ms >= $melhorAnterior,
            ...($comHistorico ? ['historico' => $historico()] : []),
        ];
    }

    /** "47d 5h", "3h 12m", "1a 20d" — o formato curto da tela. */
    private function duracao(int $ms): string
    {
        $s = max(0, intdiv($ms, 1000));
        $d = intdiv($s, 86400);
        $h = intdiv($s % 86400, 3600);
        $m = intdiv($s % 3600, 60);

        return match (true) {
            $d >= 365 => intdiv($d, 365).'a '.($d % 365).'d',
            $d > 0 => "{$d}d {$h}h",
            $h > 0 => "{$h}h {$m}m",
            $m > 0 => "{$m}m",
            default => "{$s}s",
        };
    }

    /** Instante em hora de Brasília, "AAAA-MM-DD HH:MM". */
    private function hora(int $ms): string
    {
        return CarbonImmutable::createFromTimestampMs($ms, new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d H:i');
    }
}
