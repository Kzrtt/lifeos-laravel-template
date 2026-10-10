<?php

declare(strict_types=1);

namespace Modules\Memoria\Mcp\Tools;

use Modules\Core\Exceptions\LifeosException;
use Modules\Core\Support\Mcp\Args;
use Modules\Core\Support\Mcp\Resultado;
use Modules\Memoria\Actions\AtualizarMemoria;
use Modules\Memoria\Models\Memoria;

final class UpdateMemoriaTool extends AbstractMemoriaTool
{
    public function ordem(): int
    {
        return 19;
    }

    protected function arquivo(): string
    {
        return dirname(__DIR__, 3).'/resources/mcp/update_memoria.json';
    }

    public function __construct(\Modules\Core\Contracts\VocabularioReadModel $vocabulario, private readonly AtualizarMemoria $atualizar)
    {
        parent::__construct($vocabulario);
    }

    public function chamar(array $args): array
    {
        $ref = Args::trim($args['memoria'] ?? null);
        if ($ref === '') {
            return Resultado::erro('O parâmetro memoria (título atual ou id) é obrigatório.');
        }

        $indice = $this->indice();
        ['hit' => $hit, 'erro' => $erro] = $this->resolver($indice, $ref);
        if ($hit === null) {
            return Resultado::erro((string) $erro);
        }

        $patch = [];
        if (Args::tem($args, 'titulo')) {
            $titulo = Args::trim($args['titulo']);
            if ($titulo === '') {
                return Resultado::erro('O parâmetro titulo, quando enviado, não pode ser vazio.');
            }
            if (mb_strlen($titulo) > self::MAX_TITULO) {
                return Resultado::erro('O título passa de '.self::MAX_TITULO.' caracteres.');
            }
            $outra = $indice->first(fn (Memoria $m): bool => $m->mem_id !== $hit->mem_id && mb_strtolower(trim($m->mem_titulo)) === mb_strtolower($titulo));
            if ($outra !== null) {
                return Resultado::erro("Já existe outra memória chamada \"{$outra->mem_titulo}\".");
            }
            $patch['titulo'] = $titulo;
        }
        if (Args::tem($args, 'descricao')) {
            $descricao = Args::trim($args['descricao']);
            if (mb_strlen($descricao) > self::MAX_DESCRICAO) {
                return Resultado::erro('A descrição passa de '.self::MAX_DESCRICAO.' caracteres.');
            }
            $patch['descricao'] = $descricao;
        }
        if (Args::tem($args, 'categoria')) {
            $categoria = Args::str($args['categoria']);
            $validas = $this->vocabulario->valores('memoria_categoria');
            if (! in_array($categoria, $validas, true)) {
                return Resultado::erro("Categoria inválida: {$categoria}. Valores aceitos: ".Args::juntar($validas).'.');
            }
            $patch['categoria'] = $categoria;
        }
        if ($patch === []) {
            return Resultado::erro('Nenhum campo pra atualizar foi enviado -- envie ao menos um de: titulo, descricao, categoria.');
        }

        try {
            $m = $this->atualizar->execute(['id' => $hit->mem_id, 'patch' => $patch]);
        } catch (LifeosException $e) {
            return Resultado::erro('Erro ao atualizar a memória: '.$e->errorCode);
        }

        return Resultado::json(['ok' => true, 'memoria' => [
            'id' => $m->mem_id, 'titulo' => $m->mem_titulo, 'categoria' => $m->mem_categoria,
            'descricao' => $m->mem_descricao, 'atualizada_em' => $m->mem_updated_at?->toIso8601String(),
        ]]);
    }
}
