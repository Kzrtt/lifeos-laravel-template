<?php

declare(strict_types=1);

namespace Modules\Memoria\Mcp\Tools;

use Illuminate\Support\Collection;
use Modules\Core\Support\Mcp\Args;
use Modules\Core\Support\Mcp\ToolPorArquivo;
use Modules\Memoria\Models\Memoria;

/** Base das tools de memória: o índice (com contagem de registros) e a resolução por título/id. */
abstract class AbstractMemoriaTool extends ToolPorArquivo
{
    protected const MAX_TEXTO = 8000;

    protected const MAX_TITULO = 120;

    protected const MAX_DESCRICAO = 400;

    protected const MAX_ORIGEM = 60;

    /** @return Collection<int, Memoria> por título e id; `registros_count` embutido. */
    protected function indice(): Collection
    {
        return Memoria::query()->withCount('registros')->orderBy('mem_titulo')->orderBy('mem_id')->get();
    }

    /**
     * Mesmo critério de resolução de projetos: id exato, depois título exato (sem
     * caixa), depois trecho que bata com UMA memória só.
     *
     * @param  Collection<int, Memoria>  $memorias
     * @return array{hit: ?Memoria, erro: ?string}
     */
    protected function resolver(Collection $memorias, string $refRaw): array
    {
        $ref = trim($refRaw);
        $baixo = mb_strtolower($ref);

        $hit = $memorias->first(fn (Memoria $m): bool => $m->mem_id === $ref)
            ?? $memorias->first(fn (Memoria $m): bool => mb_strtolower(trim($m->mem_titulo)) === $baixo);

        if ($hit === null) {
            $candidatos = $memorias->filter(fn (Memoria $m): bool => str_contains(mb_strtolower($m->mem_titulo), $baixo))->values();
            if ($candidatos->count() === 1) {
                $hit = $candidatos->first();
            } elseif ($candidatos->count() > 1) {
                return ['hit' => null, 'erro' => "\"{$ref}\" é ambíguo -- bate com: ".$candidatos->pluck('mem_titulo')->implode(', ').'.'];
            }
        }
        if ($hit === null) {
            $todas = $memorias->pluck('mem_titulo')->implode(', ') ?: '(nenhuma ainda)';

            return ['hit' => null, 'erro' => "Nenhuma memória encontrada com \"{$ref}\". Memórias existentes: {$todas}."];
        }

        return ['hit' => $hit, 'erro' => null];
    }

    /** Quem escreveu: o cliente se identifica; vazio vira "mcp". */
    protected function origem(mixed $v): string
    {
        $s = mb_substr(Args::trim($v), 0, self::MAX_ORIGEM);

        return $s !== '' ? $s : 'mcp';
    }
}
