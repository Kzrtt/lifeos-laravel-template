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
use Modules\Projetos\Actions\CriarTarefa;

final class CreateTarefaTool extends ToolPorArquivo
{
    public function __construct(VocabularioReadModel $vocabulario, private readonly ProjetoReadModel $projetos, private readonly CriarTarefa $criar)
    {
        parent::__construct($vocabulario);
    }

    public function ordem(): int
    {
        return 5;
    }

    protected function arquivo(): string
    {
        return dirname(__DIR__, 3).'/resources/mcp/create_tarefa.json';
    }

    public function chamar(array $args): array
    {
        $name = Args::trim($args['name'] ?? null);
        if ($name === '') {
            return Resultado::erro('O parâmetro name (nome da tarefa) é obrigatório e não pode ser vazio.');
        }

        $status = ! empty($args['status']) ? Args::str($args['status']) : 'Não Iniciado';
        $statusValidos = $this->vocabulario->valores('tarefa_status');
        if (! in_array($status, $statusValidos, true)) {
            return Resultado::erro("Status inválido: {$status}. Valores aceitos: ".Args::juntar($statusValidos).'.');
        }

        $tipo = Args::lista($args['tipo'] ?? null);
        $tiposValidos = $this->vocabulario->valores('tarefa_tipo');
        $invalidos = array_values(array_filter($tipo, fn (string $t): bool => ! in_array($t, $tiposValidos, true)));
        if ($invalidos !== []) {
            return Resultado::erro('Tipo(s) inválido(s): '.Args::juntar($invalidos).'. Valores aceitos: '.Args::juntar($tiposValidos).'.');
        }

        $projetoNome = Args::trim($args['projeto'] ?? null);
        if ($projetoNome === '') {
            return Resultado::erro('O parâmetro projeto é obrigatório -- toda tarefa do LifeOS pertence a um projeto.');
        }
        $catalogo = $this->projetos->catalogo();
        ['resolved' => $resolved, 'naoEncontrados' => $nao] = ResolveProjetos::nomes($catalogo, [$projetoNome]);
        if ($nao !== []) {
            return Resultado::erro("Projeto não encontrado ou ambíguo: {$projetoNome}. Projetos existentes: ".ResolveProjetos::existentes($catalogo).'.');
        }
        $projeto = $resolved[0];

        $entrega = $args['data_entrega'] ?? null;
        $dataEntrega = (is_string($entrega) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $entrega) === 1) ? $entrega : null;

        try {
            $tarefa = $this->criar->execute(['tarefa' => [
                'name' => $name, 'status' => $status, 'tipo' => $tipo, 'projeto_id' => $projeto['id'], 'data_entrega' => $dataEntrega,
            ]]);
        } catch (LifeosException $e) {
            return Resultado::erro('Erro ao criar a tarefa: '.$e->errorCode);
        }

        return Resultado::json(['ok' => true, 'tarefa' => [
            'id' => $tarefa->tar_id, 'name' => $tarefa->tar_name, 'status' => $tarefa->tar_status, 'tipo' => $tarefa->tar_tipo,
            'projeto' => $projeto, 'data_entrega' => $tarefa->tar_data_entrega?->format('Y-m-d'),
        ]]);
    }
}
