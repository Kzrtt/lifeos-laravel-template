<?php

declare(strict_types=1);

namespace Modules\Mcp\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Contracts\InstrucoesDoServidor;
use Modules\Core\Contracts\McpTool;
use Throwable;

/**
 * Servidor MCP remoto (Streamable HTTP, SEM estado): cada POST é um JSON-RPC 2.0
 * completo e independente. JSON-RPC escrito à mão — o protocolo são poucas
 * centenas de linhas e o `instructions` precisa ser dinâmico por instância, o
 * que o pacote laravel/mcp não deixa sobrescrever de forma limpa.
 *
 * Este servidor não importa nenhum módulo de domínio: as tools chegam pelo
 * registro (tag `mcp.tools`) que cada módulo dono alimenta no seu provider.
 */
final class McpController
{
    public const VERSAO = '2.7.2';

    public function __invoke(Request $request): JsonResponse|Response
    {
        $msg = json_decode($request->getContent(), true);
        if (! is_array($msg)) {
            return $this->erro(null, -32700, 'Parse error');
        }

        // Notificação (sem "id"): por spec o servidor não responde.
        if (! array_key_exists('id', $msg) && array_key_exists('method', $msg)) {
            return response('', 202);
        }

        $id = $msg['id'] ?? null;
        $metodo = $msg['method'] ?? null;
        $params = is_array($msg['params'] ?? null) ? $msg['params'] : [];

        try {
            return match ($metodo) {
                'initialize' => $this->resultado($id, [
                    'protocolVersion' => $params['protocolVersion'] ?? '2025-06-18',
                    'capabilities' => ['tools' => new \stdClass],
                    'serverInfo' => ['name' => 'lifeos-mcp', 'version' => self::VERSAO],
                    'instructions' => $this->instrucoes(),
                ]),
                'ping' => $this->resultado($id, new \stdClass),
                'tools/list' => $this->resultado($id, ['tools' => array_map(fn (McpTool $t): object => $t->definicao(), $this->tools())]),
                'tools/call' => $this->chamar($id, $params),
                default => $this->erro($id, -32601, 'Method not found: '.(is_scalar($metodo) ? (string) $metodo : '')),
            };
        } catch (Throwable $e) {
            report($e);

            return $this->erro($id, -32603, $e->getMessage());
        }
    }

    /** @param array<string, mixed> $params */
    private function chamar(mixed $id, array $params): JsonResponse
    {
        $nome = $params['name'] ?? null;
        $args = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        foreach ($this->tools() as $tool) {
            if ($tool->nome() === $nome) {
                return $this->resultado($id, $tool->chamar($args));
            }
        }

        return $this->erro($id, -32602, 'Unknown tool: '.(is_scalar($nome) ? (string) $nome : 'null'));
    }

    /** @return list<McpTool> */
    private function tools(): array
    {
        $tools = iterator_to_array(app()->tagged(McpTool::TAG), false);
        usort($tools, fn (McpTool $a, McpTool $b): int => $a->ordem() <=> $b->ordem());

        return $tools;
    }

    /** O texto do módulo que o implementa (memória); sem ele, só o servidor sem instruções. */
    private function instrucoes(): string
    {
        return app()->bound(InstrucoesDoServidor::class) ? app(InstrucoesDoServidor::class)->texto() : '';
    }

    private function resultado(mixed $id, mixed $resultado): JsonResponse
    {
        return $this->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $resultado]);
    }

    private function erro(mixed $id, int $codigo, string $mensagem): JsonResponse
    {
        return $this->json(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $codigo, 'message' => $mensagem]]);
    }

    private function json(mixed $corpo): JsonResponse
    {
        return response()->json($corpo, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
