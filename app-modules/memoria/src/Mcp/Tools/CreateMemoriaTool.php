<?php

declare(strict_types=1);

namespace Modules\Memoria\Mcp\Tools;

use Modules\Core\Exceptions\LifeosException;
use Modules\Core\Support\Mcp\Args;
use Modules\Core\Support\Mcp\Resultado;
use Modules\Memoria\Actions\CriarMemoria;
use Modules\Memoria\Models\Memoria;

final class CreateMemoriaTool extends AbstractMemoriaTool
{
    public function ordem(): int
    {
        return 17;
    }

    protected function arquivo(): string
    {
        return dirname(__DIR__, 3).'/resources/mcp/create_memoria.json';
    }

    public function __construct(\Modules\Core\Contracts\VocabularioReadModel $vocabulario, private readonly CriarMemoria $criar)
    {
        parent::__construct($vocabulario);
    }

    public function chamar(array $args): array
    {
        $titulo = Args::trim($args['titulo'] ?? null);
        if ($titulo === '') {
            return Resultado::erro('O parâmetro titulo é obrigatório e não pode ser vazio.');
        }
        if (mb_strlen($titulo) > self::MAX_TITULO) {
            return Resultado::erro('O título passa de '.self::MAX_TITULO.' caracteres.');
        }

        $descricao = Args::trim($args['descricao'] ?? null);
        if ($descricao === '') {
            return Resultado::erro('O parâmetro descricao é obrigatório -- é o que aparece no índice.');
        }
        if (mb_strlen($descricao) > self::MAX_DESCRICAO) {
            return Resultado::erro('A descrição passa de '.self::MAX_DESCRICAO.' caracteres -- ela é um resumo de índice; o conteúdo vai nos registros.');
        }

        $categoria = Args::str($args['categoria'] ?? null);
        $validas = $this->vocabulario->valores('memoria_categoria');
        if (! in_array($categoria, $validas, true)) {
            return Resultado::erro("Categoria inválida: {$categoria}. Valores aceitos: ".Args::juntar($validas).'.');
        }

        $textos = array_values(array_filter(array_map('trim', Args::lista($args['registros'] ?? null)), fn (string $s): bool => $s !== ''));
        foreach ($textos as $texto) {
            if (mb_strlen($texto) > self::MAX_TEXTO) {
                return Resultado::erro('Um dos registros passa de '.self::MAX_TEXTO.' caracteres -- divida em fatos menores.');
            }
        }
        $origem = $this->origem($args['origem'] ?? null);

        // Checagem amigável antes do insert: dá para apontar a memória que já existe.
        $existente = $this->indice()->first(fn (Memoria $m): bool => mb_strtolower(trim($m->mem_titulo)) === mb_strtolower($titulo));
        if ($existente !== null) {
            return Resultado::erro("Já existe a memória \"{$existente->mem_titulo}\" (id {$existente->mem_id}). Use add_registro para acrescentar a ela.");
        }

        try {
            $memoria = $this->criar->execute(['memoria' => [
                'titulo' => $titulo, 'descricao' => $descricao, 'categoria' => $categoria, 'registros' => $textos, 'origem' => $origem,
            ]]);
        } catch (LifeosException $e) {
            return Resultado::erro('Erro ao criar a memória: '.$e->errorCode);
        }

        return Resultado::json(['ok' => true, 'memoria' => [
            'id' => $memoria->mem_id, 'titulo' => $memoria->mem_titulo, 'categoria' => $memoria->mem_categoria,
            'descricao' => $memoria->mem_descricao, 'registros' => count($textos),
        ]]);
    }
}
