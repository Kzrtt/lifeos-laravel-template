<?php

declare(strict_types=1);

namespace Modules\Notas\Mcp\Tools;

use Modules\Core\Contracts\ProjetoReadModel;
use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Core\Exceptions\LifeosException;
use Modules\Core\Support\Mcp\Args;
use Modules\Core\Support\Mcp\ResolveProjetos;
use Modules\Core\Support\Mcp\Resultado;
use Modules\Core\Support\Mcp\ToolPorArquivo;
use Modules\Notas\Actions\CriarNota;

final class CreateNotaTool extends ToolPorArquivo
{
    public function __construct(VocabularioReadModel $vocabulario, private readonly ProjetoReadModel $projetos, private readonly CriarNota $criar)
    {
        parent::__construct($vocabulario);
    }

    public function ordem(): int
    {
        return 2;
    }

    protected function arquivo(): string
    {
        return dirname(__DIR__, 3).'/resources/mcp/create_nota.json';
    }

    public function chamar(array $args): array
    {
        $name = Args::trim($args['name'] ?? null);
        if ($name === '') {
            return Resultado::erro('O parâmetro name (nome da nota) é obrigatório e não pode ser vazio.');
        }

        $tipo = Args::lista($args['tipo'] ?? null);
        if ($tipo === []) {
            return Resultado::erro('O parâmetro tipo é obrigatório e precisa ter ao menos um valor.');
        }
        $validos = $this->vocabulario->valores('nota_tipo');
        $invalidos = array_values(array_filter($tipo, fn (string $t): bool => ! in_array($t, $validos, true)));
        if ($invalidos !== []) {
            return Resultado::erro('Tipo(s) inválido(s): '.Args::juntar($invalidos).'. Valores aceitos: '.Args::juntar($validos).'.');
        }

        $nomes = Args::lista($args['projetos'] ?? null);
        if ($nomes === []) {
            return Resultado::erro('O parâmetro projetos é obrigatório e precisa ter ao menos um nome de projeto.');
        }

        $conteudo = is_string($args['conteudo_md'] ?? null) ? trim($args['conteudo_md']) : '';
        if ($conteudo === '') {
            return Resultado::erro('O parâmetro conteudo_md é obrigatório e não pode ser vazio.');
        }

        $catalogo = $this->projetos->catalogo();
        ['resolved' => $resolved, 'naoEncontrados' => $nao] = ResolveProjetos::nomes($catalogo, $nomes);
        if ($nao !== []) {
            return Resultado::erro('Projeto(s) não encontrado(s) ou ambíguo(s): '.Args::juntar($nao).'. Projetos existentes: '.ResolveProjetos::existentes($catalogo).'.');
        }

        try {
            $nota = $this->criar->execute(['nota' => [
                'name' => $name, 'tipo' => $tipo, 'data' => Args::hojeSaoPaulo(), 'conteudo_md' => $conteudo,
                'projeto_ids' => array_column($resolved, 'id'),
            ]]);
        } catch (LifeosException $e) {
            return Resultado::erro('Erro ao salvar a nota: '.$e->errorCode);
        }

        return Resultado::json(['ok' => true, 'nota' => [
            'id' => $nota->not_id, 'name' => $nota->not_name, 'tipo' => $nota->not_tipo, 'data' => $nota->not_data?->format('Y-m-d'),
            'projetos' => $resolved, 'conteudo_md' => $nota->not_conteudo_md, 'created_at' => $nota->not_created_at?->toIso8601String(),
        ]]);
    }
}
