<?php

declare(strict_types=1);

namespace Modules\Manifestacoes\Support;

use Modules\Core\Support\ConsumidorPorModelo;
use Modules\Manifestacoes\Models\Manifestacao;

final class ConsumidorManifestacoes extends ConsumidorPorModelo
{
    protected function mapa(): array
    {
        return [
            'manifestacao_status' => [Manifestacao::class, 'man_status', false],
            'manifestacao_tag' => [Manifestacao::class, 'man_tags', true],
        ];
    }
}
