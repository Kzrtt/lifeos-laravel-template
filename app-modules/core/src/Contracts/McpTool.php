<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Uma tool do servidor MCP. Cada módulo DONO do domínio declara as suas (em
 * `src/Mcp/Tools`) e as registra com `$this->app->tag(X::class, McpTool::TAG)`;
 * o módulo `mcp` só hospeda — não importa nenhum módulo de domínio. A tool é um
 * adaptador FINO: valida os argumentos em texto amigável e termina na mesma
 * Action do domínio que a tela usa.
 */
interface McpTool
{
    public const TAG = 'mcp.tools';

    /** Posição em tools/list (a ordem de hoje é parte do contrato). */
    public function ordem(): int;

    public function nome(): string;

    /**
     * Definição para tools/list: {name, description, inputSchema}. Os `enum`
     * saem do vocabulário vigente a cada chamada.
     */
    public function definicao(): object;

    /**
     * Executa a tool. Devolve o resultado MCP ({content:[{type:text,text}], isError}).
     * Erro de DOMÍNIO volta como `isError:true` com texto — o modelo lê o motivo
     * e se corrige; só falha de infraestrutura lança.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function chamar(array $args): array;
}
