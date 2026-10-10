<?php

declare(strict_types=1);

namespace Modules\Financas\Support;

use Illuminate\Http\Request;

/**
 * Traduz o payload "create page" do Notion — que o atalho do iPhone envia sem
 * mudança — para o array canônico de CriarMovimentacao. `parent` e `icon` são
 * aceitos e ignorados. Mais um adaptador de canal; a regra continua na Action.
 */
final class NotionPayloadAdapter
{
    /** @return array<string, mixed> */
    public static function from(Request $request): array
    {
        $props = $request->json('properties');
        $props = is_array($props) ? $props : [];

        $titulo = is_array($props['Name']['title'] ?? null) ? $props['Name']['title'] : [];
        $nome = trim(implode('', array_map(
            fn ($t): string => is_array($t) ? (string) ($t['text']['content'] ?? '') : '',
            $titulo,
        )));

        $valor = null;
        if (is_array($props['Valor'] ?? null) && array_key_exists('number', $props['Valor'])) {
            $valor = $props['Valor']['number'] ?? 0; // `number: null` vira 0, como hoje
        }

        $selecionados = is_array($props['Tipo']['multi_select'] ?? null) ? $props['Tipo']['multi_select'] : [];
        $tipo = array_map(fn ($t): string => is_array($t) ? (string) ($t['name'] ?? '') : '', $selecionados);

        return ['movimentacao' => [
            'name' => $nome,
            'valor' => $valor,
            'date' => $props['Date']['date']['start'] ?? null,
            'tipo' => $tipo,
        ]];
    }
}
