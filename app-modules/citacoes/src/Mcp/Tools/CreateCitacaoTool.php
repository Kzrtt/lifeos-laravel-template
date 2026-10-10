<?php

declare(strict_types=1);

namespace Modules\Citacoes\Mcp\Tools;

use Modules\Citacoes\Actions\CriarCitacao;
use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Core\Exceptions\LifeosException;
use Modules\Core\Support\Mcp\Args;
use Modules\Core\Support\Mcp\Resultado;
use Modules\Core\Support\Mcp\ToolPorArquivo;

/** Só criar (sem update/delete por MCP). */
final class CreateCitacaoTool extends ToolPorArquivo
{
    public function __construct(VocabularioReadModel $vocabulario, private readonly CriarCitacao $criar)
    {
        parent::__construct($vocabulario);
    }

    public function ordem(): int
    {
        return 13;
    }

    protected function arquivo(): string
    {
        return dirname(__DIR__, 3).'/resources/mcp/create_citacao.json';
    }

    public function chamar(array $args): array
    {
        $texto = Args::trim($args['texto'] ?? null);
        if ($texto === '') {
            return Resultado::erro('O parâmetro texto é obrigatório e não pode ser vazio.');
        }
        if (mb_strlen($texto) > CriarCitacao::MAX_TEXTO) {
            return Resultado::erro('O texto passa de 2000 caracteres -- uma citação precisa ser mais curta.');
        }
        $autor = Args::trim($args['autor'] ?? null);
        if ($autor === '') {
            return Resultado::erro('O parâmetro autor (quem disse) é obrigatório e não pode ser vazio.');
        }
        if (mb_strlen($autor) > CriarCitacao::MAX_AUTOR) {
            return Resultado::erro('O autor passa de 200 caracteres.');
        }

        try {
            $c = $this->criar->execute(['citacao' => ['texto' => $texto, 'autor' => $autor]]);
        } catch (LifeosException $e) {
            return Resultado::erro('Erro ao criar a citação: '.$e->errorCode);
        }

        return Resultado::json(['ok' => true, 'citacao' => ['id' => $c->cit_id, 'texto' => $c->cit_texto, 'autor' => $c->cit_autor]]);
    }
}
