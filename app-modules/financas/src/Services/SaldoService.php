<?php

declare(strict_types=1);

namespace Modules\Financas\Services;

use Modules\Financas\Models\Movimentacao;

/**
 * O saldo acumulado antes de uma data e o intervalo de meses com dados — as duas
 * RPCs de hoje (lifeos_saldo_abertura, lifeos_range) como métodos de Service.
 *
 * Soma com sinal por direção: Entrada soma; Saida subtrai, EXCETO se for
 * Crédito (a fatura é projetada à parte, não sai do caixa no mês). Em centavos
 * inteiros: nunca ponto flutuante acumulando.
 */
final class SaldoService
{
    public function saldoAbertura(string $antesDe): float
    {
        $centavos = 0;

        Movimentacao::query()->where('mov_date', '<', $antesDe)
            ->select(['mov_id', 'mov_valor', 'mov_tipo'])
            ->cursor()
            ->each(function (Movimentacao $m) use (&$centavos): void {
                $centavos += $this->sinal($m->mov_tipo) * (int) round(((float) $m->mov_valor) * 100);
            });

        return $centavos / 100;
    }

    /**
     * Mês (YYYY-MM) da primeira e da última movimentação, ou null sem dados.
     *
     * @return array{min: string, max: string}|null
     */
    public function intervalo(): ?array
    {
        $min = Movimentacao::query()->min('mov_date');
        $max = Movimentacao::query()->max('mov_date');

        return ($min === null || $max === null)
            ? null
            : ['min' => substr((string) $min, 0, 7), 'max' => substr((string) $max, 0, 7)];
    }

    /** @param list<string> $tipo */
    public function sinal(array $tipo): int
    {
        return match (true) {
            in_array('Entrada', $tipo, true) => 1,
            in_array('Saida', $tipo, true) && ! in_array('Crédito', $tipo, true) => -1,
            default => 0,
        };
    }
}
