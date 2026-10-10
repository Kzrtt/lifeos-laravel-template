<?php

declare(strict_types=1);

namespace Modules\Financas\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Actions\RpcAction;
use Modules\Financas\Models\Movimentacao;
use Modules\Financas\Services\SaldoService;

/**
 * A query do módulo Finanças: as movimentações do mês, o saldo de abertura (tudo
 * antes do dia 1), o intervalo de meses com dados e, opcionalmente, o mês anterior.
 */
final class ConsultarMes extends RpcAction
{
    public function __construct(private readonly SaldoService $saldo) {}

    protected function sanitize(array $input): array
    {
        $ym = $this->texto($input['ym'] ?? null);
        $prev = $this->texto($input['ym_prev'] ?? null);

        return [
            'ym' => $this->mesValido($ym) ? $ym : CarbonImmutable::now('UTC')->format('Y-m'),
            'ym_prev' => $this->mesValido($prev) ? $prev : '',
        ];
    }

    private function mesValido(string $ym): bool
    {
        return preg_match('/^(\d{4})-(\d{2})$/', $ym, $m) === 1 && (int) $m[2] >= 1 && (int) $m[2] <= 12;
    }

    protected function rules(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    protected function handle(array $data): mixed
    {
        $primeiro = $data['ym'].'-01';

        return [
            'ym' => $data['ym'],
            'ym_prev' => $data['ym_prev'],
            'range' => $this->saldo->intervalo(),
            'saldo_abertura' => $this->saldo->saldoAbertura($primeiro),
            'movimentacoes' => $this->doMes($data['ym']),
            'movimentacoes_prev' => $data['ym_prev'] !== '' ? $this->doMes($data['ym_prev']) : null,
        ];
    }

    /** @return Collection<int, Movimentacao> */
    private function doMes(string $ym): Collection
    {
        $inicio = CarbonImmutable::createFromFormat('Y-m-d', $ym.'-01', 'UTC')->startOfDay();

        return Movimentacao::query()
            ->where('mov_date', '>=', $inicio->format('Y-m-d'))
            ->where('mov_date', '<', $inicio->addMonth()->format('Y-m-d'))
            ->orderBy('mov_date')->orderBy('mov_created_at')->orderBy('mov_id')
            ->get();
    }
}
