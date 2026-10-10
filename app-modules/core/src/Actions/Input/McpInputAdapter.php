<?php

declare(strict_types=1);

namespace Modules\Core\Actions\Input;

/**
 * Adapta os argumentos de uma Tool MCP para o **array canônico** que a Action
 * espera — o adaptador irmão do HttpInputAdapter.
 *
 * Uma Tool MCP (pacote `laravel/mcp`, opcional — ver README §Dependências) recebe
 * os argumentos do modelo de IA como um array associativo; este adapter os
 * normaliza para o mesmo formato que a Action consome via REST. Assim a mesma
 * Action é idêntica nos dois canais — só muda o tradutor de entrada.
 *
 * O template não instala o servidor MCP; este adapter existe para manter a Action
 * canal-agnóstica desde o dia 1 e provar a simetria. Se o projeto nunca expuser
 * MCP, pode removê-lo sem efeito colateral.
 */
final class McpInputAdapter
{
    /**
     * @param  array<string, mixed>  $arguments  argumentos brutos da tool
     * @return array<string, mixed>
     */
    public static function from(array $arguments): array
    {
        return $arguments;
    }
}
