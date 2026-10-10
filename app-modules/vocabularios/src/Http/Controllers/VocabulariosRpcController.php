<?php

declare(strict_types=1);

namespace Modules\Vocabularios\Http\Controllers;

use Modules\Core\Http\RpcController;
use Modules\Vocabularios\Actions\AtualizarVocabulario;
use Modules\Vocabularios\Actions\CriarVocabulario;
use Modules\Vocabularios\Actions\ExcluirVocabulario;
use Modules\Vocabularios\Actions\ListarVocabularios;
use Modules\Vocabularios\Actions\ReordenarVocabularios;
use Modules\Vocabularios\Http\Resources\VocabularioResource;
use Modules\Vocabularios\Models\Vocabulario;
use Modules\Vocabularios\Support\Dominios;

/** POST /functions/v1/lifeos-vocabularios — query, create, update, delete, reorder. */
final class VocabulariosRpcController extends RpcController
{
    protected function actions(): array
    {
        return [
            'query' => [ListarVocabularios::class, function (array $agrupado): array {
                $vocabularios = [];
                foreach ($agrupado as $dominio => $itens) {
                    $vocabularios[$dominio] = array_map(
                        fn (array $i): array => [
                            'id' => $i['vocabulario']->voc_id,
                            'valor' => $i['vocabulario']->voc_valor,
                            'cor' => $i['vocabulario']->voc_cor,
                            'ordem' => $i['vocabulario']->voc_ordem,
                            'protegido' => $i['vocabulario']->voc_protegido,
                            'uso' => $i['uso'],
                        ],
                        $itens,
                    );
                }

                return ['dominios' => Dominios::LISTA, 'vocabularios' => $vocabularios];
            }],
            'create' => [CriarVocabulario::class, fn (Vocabulario $v): array => [
                'vocabulario' => VocabularioResource::make($v, 0, true),
            ]],
            'update' => [AtualizarVocabulario::class, fn (array $r): array => [
                'vocabulario' => VocabularioResource::make($r['vocabulario']),
                'linhas_migradas' => $r['linhas_migradas'],
            ]],
            'delete' => [ExcluirVocabulario::class, fn (string $id): array => ['id' => $id]],
            'reorder' => [ReordenarVocabularios::class, fn (int $n): array => ['total' => $n]],
        ];
    }
}
