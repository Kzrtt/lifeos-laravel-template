<?php

declare(strict_types=1);

namespace Modules\Financas\Support;

use Modules\Core\Support\ConsumidorPorModelo;
use Modules\Financas\Models\Movimentacao;

/** Direção e meio gravam no MESMO array `mov_tipo`. */
final class ConsumidorMovimentacoes extends ConsumidorPorModelo
{
    protected function mapa(): array
    {
        return [
            'mov_direcao' => [Movimentacao::class, 'mov_tipo', true],
            'mov_meio' => [Movimentacao::class, 'mov_tipo', true],
        ];
    }
}
