<?php

declare(strict_types=1);

namespace Modules\Core\Support\Mcp;

use Modules\Core\DTOs\ProjetoResumoDTO;

/**
 * Como as tools do MCP casam texto do modelo com projetos. Duas regras, as de hoje:
 *  - FILTRO (`filtro`): substring, sem caixa, de cada termo; nunca falha — só
 *    avisa quando um termo não casa com nenhum projeto.
 *  - VÍNCULO (`nomes`): nome exato (sem caixa) ou, na falta, o único projeto que
 *    contém o trecho; ambíguo ou ausente vira "não encontrado".
 */
final class ResolveProjetos
{
    /**
     * @param  list<ProjetoResumoDTO>  $projetos
     * @param  list<string>  $termosRaw
     * @return array{ids: ?list<string>, warnings: list<string>}
     */
    public static function filtro(array $projetos, array $termosRaw): array
    {
        $termos = array_values(array_filter(array_map(fn (string $s): string => mb_strtolower(trim($s)), $termosRaw), fn (string $s): bool => $s !== ''));
        if ($termos === []) {
            return ['ids' => null, 'warnings' => []];
        }

        $ids = [];
        $warnings = [];
        foreach ($termos as $termo) {
            $achados = array_filter($projetos, fn (ProjetoResumoDTO $p): bool => str_contains(mb_strtolower($p->name), $termo));
            if ($achados === []) {
                $warnings[] = "nenhum projeto encontrado com \"{$termo}\"";
            }
            foreach ($achados as $p) {
                $ids[$p->id] = true;
            }
        }

        return ['ids' => array_keys($ids), 'warnings' => $warnings];
    }

    /**
     * @param  list<ProjetoResumoDTO>  $projetos
     * @param  list<string>  $nomes
     * @return array{resolved: list<array{id: string, name: string}>, naoEncontrados: list<string>}
     */
    public static function nomes(array $projetos, array $nomes): array
    {
        $resolved = [];
        $naoEncontrados = [];

        foreach ($nomes as $bruto) {
            $nome = trim($bruto);
            $baixo = mb_strtolower($nome);

            $hit = null;
            foreach ($projetos as $p) {
                if (mb_strtolower($p->name) === $baixo) {
                    $hit = $p;
                    break;
                }
            }
            if ($hit === null) {
                $candidatos = array_values(array_filter($projetos, fn (ProjetoResumoDTO $p): bool => str_contains(mb_strtolower($p->name), $baixo)));
                $hit = count($candidatos) === 1 ? $candidatos[0] : null;
            }

            $hit !== null
                ? $resolved[] = ['id' => $hit->id, 'name' => $hit->rotulo()]
                : $naoEncontrados[] = $nome;
        }

        return ['resolved' => $resolved, 'naoEncontrados' => $naoEncontrados];
    }

    /** @param list<ProjetoResumoDTO> $projetos @return array<string, ProjetoResumoDTO> */
    public static function porId(array $projetos): array
    {
        $mapa = [];
        foreach ($projetos as $p) {
            $mapa[$p->id] = $p;
        }

        return $mapa;
    }

    /** @param list<ProjetoResumoDTO> $projetos */
    public static function existentes(array $projetos): string
    {
        return implode(', ', array_map(fn (ProjetoResumoDTO $p): string => $p->name, $projetos));
    }
}
