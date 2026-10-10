<?php

declare(strict_types=1);

namespace Modules\Memoria\Mcp\Tools;

use Modules\Core\Exceptions\LifeosException;
use Modules\Core\Support\Mcp\Args;
use Modules\Core\Support\Mcp\Resultado;
use Modules\Memoria\Actions\AtualizarRegistro;

final class UpdateRegistroTool extends AbstractMemoriaTool
{
    public function ordem(): int
    {
        return 20;
    }

    protected function arquivo(): string
    {
        return dirname(__DIR__, 3).'/resources/mcp/update_registro.json';
    }

    public function __construct(\Modules\Core\Contracts\VocabularioReadModel $vocabulario, private readonly AtualizarRegistro $atualizar)
    {
        parent::__construct($vocabulario);
    }

    /** Substituição do texto INTEIRO: um envio parcial apagaria o resto do fato. */
    public function chamar(array $args): array
    {
        $id = Args::trim($args['id'] ?? null);
        if ($id === '') {
            return Resultado::erro('O parâmetro id (id do registro, retornado por get_memoria) é obrigatório.');
        }
        $texto = Args::trim($args['texto'] ?? null);
        if ($texto === '') {
            return Resultado::erro('O parâmetro texto é obrigatório e precisa ser o TEXTO INTEIRO e final do registro.');
        }
        if (mb_strlen($texto) > self::MAX_TEXTO) {
            return Resultado::erro('O texto passa de '.self::MAX_TEXTO.' caracteres.');
        }

        try {
            $r = $this->atualizar->execute(['id' => $id, 'patch' => ['texto' => $texto]]);
        } catch (LifeosException $e) {
            return $e->errorCode === 'not_found'
                ? Resultado::erro("Nenhum registro encontrado com id {$id}.")
                : Resultado::erro('Erro ao atualizar o registro: '.$e->errorCode);
        }

        return Resultado::json(['ok' => true, 'registro' => [
            'id' => $r->mrg_id, 'memoria_id' => $r->memoria_mem_id, 'texto' => $r->mrg_texto, 'origem' => $r->mrg_origem,
            'data' => $r->mrg_created_at?->toIso8601String(), 'editado_em' => $r->mrg_updated_at?->toIso8601String(),
        ]]);
    }
}
