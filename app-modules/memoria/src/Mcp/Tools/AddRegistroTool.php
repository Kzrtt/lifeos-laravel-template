<?php

declare(strict_types=1);

namespace Modules\Memoria\Mcp\Tools;

use Modules\Core\Exceptions\LifeosException;
use Modules\Core\Support\Mcp\Args;
use Modules\Core\Support\Mcp\Resultado;
use Modules\Memoria\Actions\CriarRegistro;

final class AddRegistroTool extends AbstractMemoriaTool
{
    public function ordem(): int
    {
        return 18;
    }

    protected function arquivo(): string
    {
        return dirname(__DIR__, 3).'/resources/mcp/add_registro.json';
    }

    public function __construct(\Modules\Core\Contracts\VocabularioReadModel $vocabulario, private readonly CriarRegistro $criar)
    {
        parent::__construct($vocabulario);
    }

    public function chamar(array $args): array
    {
        $ref = Args::trim($args['memoria'] ?? null);
        if ($ref === '') {
            return Resultado::erro('O parâmetro memoria (título ou id) é obrigatório.');
        }
        $texto = Args::trim($args['texto'] ?? null);
        if ($texto === '') {
            return Resultado::erro('O parâmetro texto é obrigatório e não pode ser vazio.');
        }
        if (mb_strlen($texto) > self::MAX_TEXTO) {
            return Resultado::erro('O texto passa de '.self::MAX_TEXTO.' caracteres -- divida em registros menores.');
        }

        ['hit' => $hit, 'erro' => $erro] = $this->resolver($this->indice(), $ref);
        if ($hit === null) {
            return Resultado::erro((string) $erro);
        }

        try {
            $registro = $this->criar->execute(['registro' => ['memoria_id' => $hit->mem_id, 'texto' => $texto, 'origem' => $this->origem($args['origem'] ?? null)]]);
        } catch (LifeosException $e) {
            return Resultado::erro('Erro ao gravar o registro: '.$e->errorCode);
        }

        return Resultado::json([
            'ok' => true,
            'memoria' => ['id' => $hit->mem_id, 'titulo' => $hit->mem_titulo],
            'registro' => ['id' => $registro->mrg_id, 'texto' => $registro->mrg_texto, 'origem' => $registro->mrg_origem, 'data' => $registro->mrg_created_at?->toIso8601String()],
        ]);
    }
}
