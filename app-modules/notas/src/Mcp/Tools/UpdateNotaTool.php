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
use Modules\Notas\Actions\AtualizarNota;

/**
 * update_nota é SUBSTITUIÇÃO COMPLETA, nunca patch parcial: name, tipo, projetos
 * e conteudo_md são obrigatórios em toda chamada. Exigir o texto inteiro é o que
 * garante que o modelo nunca envie só um trecho — o que apagaria o resto da nota.
 */
final class UpdateNotaTool extends ToolPorArquivo
{
    public function __construct(VocabularioReadModel $vocabulario, private readonly ProjetoReadModel $projetos, private readonly AtualizarNota $atualizar)
    {
        parent::__construct($vocabulario);
    }

    public function ordem(): int
    {
        return 3;
    }

    protected function arquivo(): string
    {
        return dirname(__DIR__, 3).'/resources/mcp/update_nota.json';
    }

    public function chamar(array $args): array
    {
        $id = Args::trim($args['id'] ?? null);
        if ($id === '') {
            return Resultado::erro('O parâmetro id (id da nota a editar, retornado por search_notas) é obrigatório.');
        }

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

        if (! is_array($args['projetos'] ?? null)) {
            return Resultado::erro('O parâmetro projetos é obrigatório -- envie a lista completa de projetos vinculados (pode ser [] se a nota não deve ter nenhum).');
        }
        $nomes = Args::lista($args['projetos']);

        $conteudo = is_string($args['conteudo_md'] ?? null) ? trim($args['conteudo_md']) : '';
        if ($conteudo === '') {
            return Resultado::erro('O parâmetro conteudo_md é obrigatório e precisa ser o TEXTO INTEIRO e final da nota (nunca um trecho, resumo ou diff).');
        }

        $catalogo = $this->projetos->catalogo();
        ['resolved' => $resolved, 'naoEncontrados' => $nao] = ResolveProjetos::nomes($catalogo, $nomes);
        if ($nao !== []) {
            return Resultado::erro('Projeto(s) não encontrado(s) ou ambíguo(s): '.Args::juntar($nao).'. Projetos existentes: '.ResolveProjetos::existentes($catalogo).'.');
        }

        try {
            $nota = $this->atualizar->execute(['id' => $id, 'patch' => [
                'name' => $name, 'tipo' => $tipo, 'conteudo_md' => $conteudo, 'projeto_ids' => array_column($resolved, 'id'),
            ]]);
        } catch (LifeosException $e) {
            return $e->errorCode === 'not_found'
                ? Resultado::erro("Nenhuma nota encontrada com id {$id}.")
                : Resultado::erro('Erro ao atualizar a nota: '.$e->errorCode);
        }

        return Resultado::json(['ok' => true, 'nota' => [
            'id' => $nota->not_id, 'name' => $nota->not_name, 'tipo' => $nota->not_tipo, 'data' => $nota->not_data?->format('Y-m-d'),
            'projetos' => $resolved, 'conteudo_md' => $nota->not_conteudo_md, 'updated_at' => $nota->not_updated_at?->toIso8601String(),
        ]]);
    }
}
