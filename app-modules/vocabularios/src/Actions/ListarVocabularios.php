<?php

declare(strict_types=1);

namespace Modules\Vocabularios\Actions;

use Modules\Core\Actions\RpcAction;
use Modules\Vocabularios\Models\Vocabulario;
use Modules\Vocabularios\Services\VocabularioService;
use Modules\Vocabularios\Support\Dominios;

/** Tudo agrupado por domínio, com a contagem de uso de cada valor. */
final class ListarVocabularios extends RpcAction
{
    public function __construct(private readonly VocabularioService $vocabularios) {}

    protected function rules(): array
    {
        return [];
    }

    /** @return array<string, list<array{vocabulario: Vocabulario, uso: ?int}>> */
    protected function handle(array $data): mixed
    {
        $rows = Vocabulario::query()
            ->orderBy('voc_dominio')->orderBy('voc_ordem')->orderBy('voc_valor')
            ->get();

        $agrupado = [];
        foreach ($rows as $row) {
            $agrupado[$row->voc_dominio][] = [
                'vocabulario' => $row,
                'uso' => $this->vocabularios->uso($row->voc_dominio, $row->voc_valor),
            ];
        }

        // Domínios sem linha aparecem vazios: a tela não esconde uma seção inteira.
        foreach (array_keys(Dominios::LISTA) as $dominio) {
            $agrupado[$dominio] ??= [];
        }

        return $agrupado;
    }
}
