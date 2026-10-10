<?php

declare(strict_types=1);

namespace Modules\Projetos\Mcp\Tools;

use Modules\Core\Contracts\ProjetoReadModel;
use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Core\Exceptions\LifeosException;
use Modules\Core\Support\Mcp\Args;
use Modules\Core\Support\Mcp\ResolveProjetos;
use Modules\Core\Support\Mcp\Resultado;
use Modules\Core\Support\Mcp\ToolPorArquivo;
use Modules\Projetos\Actions\AtualizarTarefa;

/** PATCH parcial: só os campos enviados mudam; `projeto` troca o projeto vinculado. */
final class UpdateTarefaTool extends ToolPorArquivo
{
    public function __construct(VocabularioReadModel $vocabulario, private readonly ProjetoReadModel $projetos, private readonly AtualizarTarefa $atualizar)
    {
        parent::__construct($vocabulario);
    }

    public function ordem(): int
    {
        return 6;
    }

    protected function arquivo(): string
    {
        return dirname(__DIR__, 3).'/resources/mcp/update_tarefa.json';
    }

    public function chamar(array $args): array
    {
        $id = Args::trim($args['id'] ?? null);
        if ($id === '') {
            return Resultado::erro('O parâmetro id (id da tarefa a editar, retornado por search_tarefas) é obrigatório.');
        }

        $patch = [];

        if (Args::tem($args, 'name')) {
            $name = Args::trim($args['name']);
            if ($name === '') {
                return Resultado::erro('O parâmetro name, quando enviado, não pode ser vazio.');
            }
            $patch['name'] = $name;
        }
        if (Args::tem($args, 'status')) {
            $status = Args::str($args['status']);
            $validos = $this->vocabulario->valores('tarefa_status');
            if (! in_array($status, $validos, true)) {
                return Resultado::erro("Status inválido: {$status}. Valores aceitos: ".Args::juntar($validos).'.');
            }
            $patch['status'] = $status;
        }
        if (Args::tem($args, 'tipo')) {
            $tipo = Args::lista($args['tipo']);
            $validos = $this->vocabulario->valores('tarefa_tipo');
            $invalidos = array_values(array_filter($tipo, fn (string $t): bool => ! in_array($t, $validos, true)));
            if ($invalidos !== []) {
                return Resultado::erro('Tipo(s) inválido(s): '.Args::juntar($invalidos).'. Valores aceitos: '.Args::juntar($validos).'.');
            }
            $patch['tipo'] = $tipo;
        }
        if (Args::tem($args, 'data_entrega')) {
            $v = $args['data_entrega'];
            $patch['data_entrega'] = (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1) ? $v : null;
        }
        if (Args::tem($args, 'projeto')) {
            $nome = Args::trim($args['projeto']);
            if ($nome === '') {
                return Resultado::erro('O parâmetro projeto, quando enviado, não pode ser vazio -- toda tarefa precisa de um projeto vinculado.');
            }
            $catalogo = $this->projetos->catalogo();
            ['resolved' => $resolved, 'naoEncontrados' => $nao] = ResolveProjetos::nomes($catalogo, [$nome]);
            if ($nao !== []) {
                return Resultado::erro("Projeto não encontrado ou ambíguo: {$nome}. Projetos existentes: ".ResolveProjetos::existentes($catalogo).'.');
            }
            $patch['projeto_id'] = $resolved[0]['id'];
        }
        if ($patch === []) {
            return Resultado::erro('Nenhum campo pra atualizar foi enviado -- envie ao menos um de: name, status, tipo, data_entrega, projeto.');
        }

        try {
            $tarefa = $this->atualizar->execute(['id' => $id, 'patch' => $patch]);
        } catch (LifeosException $e) {
            return $e->errorCode === 'not_found'
                ? Resultado::erro("Nenhuma tarefa encontrada com id {$id}.")
                : Resultado::erro('Erro ao atualizar a tarefa: '.$e->errorCode);
        }

        return Resultado::json(['ok' => true, 'tarefa' => [
            'id' => $tarefa->tar_id, 'name' => $tarefa->tar_name, 'status' => $tarefa->tar_status, 'tipo' => $tarefa->tar_tipo,
            'projeto_id' => $tarefa->projeto_prj_id, 'data_entrega' => $tarefa->tar_data_entrega?->format('Y-m-d'),
            'updated_at' => $tarefa->tar_updated_at?->toIso8601String(),
        ]]);
    }
}
