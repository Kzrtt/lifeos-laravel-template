<?php

declare(strict_types=1);

namespace Modules\Financas\Support;

use Modules\Core\Support\ConsumidorPorModelo;
use Modules\Financas\Models\Recorrencia;

/**
 * Recorrências guardam direção e meio como escalares. A RPC antiga só migrava as
 * movimentações (a recorrência ficava órfã ao renomear um meio); aqui ela migra
 * junto e conta no uso, então apagar um meio em uso por uma recorrência é barrado.
 */
final class ConsumidorRecorrencias extends ConsumidorPorModelo
{
    protected function mapa(): array
    {
        return [
            'mov_direcao' => [Recorrencia::class, 'rec_direcao', false],
            'mov_meio' => [Recorrencia::class, 'rec_meio', false],
        ];
    }
}
