<?php

declare(strict_types=1);

namespace Modules\Financas\Http\Controllers;

use Modules\Core\Http\RpcController;
use Modules\Financas\Actions\AtualizarMovimentacao;
use Modules\Financas\Actions\ConsultarMes;
use Modules\Financas\Actions\CriarMovimentacao;
use Modules\Financas\Actions\ExcluirMovimentacao;
use Modules\Financas\Http\Resources\MovimentacaoResource;
use Modules\Financas\Models\Movimentacao;

/** POST /functions/v1/lifeos-movimentacoes — query (por mês), create, update, delete. */
final class MovimentacoesRpcController extends RpcController
{
    protected function actions(): array
    {
        $uma = fn (Movimentacao $m): array => ['movimentacao' => MovimentacaoResource::make($m)];
        $lista = fn ($rows): array => $rows->map(fn (Movimentacao $m): array => MovimentacaoResource::make($m))->all();

        return [
            'query' => [ConsultarMes::class, fn (array $r): array => [
                'ym' => $r['ym'],
                ...($r['ym_prev'] !== '' ? ['ym_prev' => $r['ym_prev']] : []),
                'range' => $r['range'],
                'saldo_abertura' => $r['saldo_abertura'],
                'count' => $r['movimentacoes']->count(),
                'fetched_at' => now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
                'movimentacoes' => $lista($r['movimentacoes']),
                ...($r['movimentacoes_prev'] !== null ? ['movimentacoes_prev' => $lista($r['movimentacoes_prev'])] : []),
            ]],
            'create' => [CriarMovimentacao::class, $uma],
            'update' => [AtualizarMovimentacao::class, $uma],
            'delete' => [ExcluirMovimentacao::class, fn (string $id): array => ['id' => $id]],
        ];
    }
}
