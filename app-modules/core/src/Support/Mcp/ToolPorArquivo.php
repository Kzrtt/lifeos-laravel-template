<?php

declare(strict_types=1);

namespace Modules\Core\Support\Mcp;

use Modules\Core\Contracts\McpTool;
use Modules\Core\Contracts\VocabularioReadModel;
use RuntimeException;
use stdClass;

/**
 * Base de tool cuja definição (nome, descrição e JSON Schema) mora num arquivo
 * `resources/mcp/<nome>.json` do módulo dono. As descrições são texto longo que
 * o modelo lê; em JSON ficam byte a byte como no servidor de hoje (e o teste de
 * contrato as compara com o `tools/list` de referência).
 *
 * Os `enum` dos schemas vêm marcados como `["@@dominio@@"]` e são substituídos,
 * a cada chamada, pelo vocabulário vigente — como o servidor atual faz.
 */
abstract class ToolPorArquivo implements McpTool
{
    public function __construct(protected readonly VocabularioReadModel $vocabulario) {}

    /** Caminho absoluto do JSON da definição. */
    abstract protected function arquivo(): string;

    public function definicao(): object
    {
        $json = file_get_contents($this->arquivo());
        if ($json === false) {
            throw new RuntimeException('Definição de tool ausente: '.$this->arquivo());
        }

        return $this->preencher(json_decode($json, false, 512, JSON_THROW_ON_ERROR));
    }

    private function preencher(mixed $no): mixed
    {
        if (is_array($no)) {
            if (count($no) === 1 && is_string($no[0]) && preg_match('/^@@(\w+)@@$/', $no[0], $m) === 1) {
                return $this->vocabulario->valores($m[1]);
            }

            return array_map(fn ($i): mixed => $this->preencher($i), $no);
        }

        if ($no instanceof stdClass) {
            foreach (get_object_vars($no) as $chave => $valor) {
                $no->{$chave} = $this->preencher($valor);
            }
        }

        return $no;
    }

    public function nome(): string
    {
        return $this->definicao()->name;
    }
}
