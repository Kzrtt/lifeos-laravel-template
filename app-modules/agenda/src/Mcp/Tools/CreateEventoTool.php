<?php

declare(strict_types=1);

namespace Modules\Agenda\Mcp\Tools;

use Modules\Agenda\Actions\CriarEvento;
use Modules\Core\Contracts\ProjetoReadModel;
use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Core\Exceptions\LifeosException;
use Modules\Core\Support\Mcp\Args;
use Modules\Core\Support\Mcp\ResolveProjetos;
use Modules\Core\Support\Mcp\Resultado;
use Modules\Core\Support\Mcp\ToolPorArquivo;

final class CreateEventoTool extends ToolPorArquivo
{
    private const FORMATO = '/^\d{4}-\d{2}-\d{2}$/';

    public function __construct(VocabularioReadModel $vocabulario, private readonly ProjetoReadModel $projetos, private readonly CriarEvento $criar)
    {
        parent::__construct($vocabulario);
    }

    public function ordem(): int
    {
        return 9;
    }

    protected function arquivo(): string
    {
        return dirname(__DIR__, 3).'/resources/mcp/create_evento.json';
    }

    public function chamar(array $args): array
    {
        $name = Args::trim($args['name'] ?? null);
        if ($name === '') {
            return Resultado::erro('O parâmetro name (nome do evento) é obrigatório e não pode ser vazio.');
        }

        $date = Args::str($args['date'] ?? null);
        if (preg_match(self::FORMATO, $date) !== 1) {
            return Resultado::erro('O parâmetro date é obrigatório e precisa estar no formato YYYY-MM-DD.');
        }

        $dateFim = null;
        $bruto = $args['date_fim'] ?? null;
        if ($bruto !== null && $bruto !== '') {
            $dateFim = Args::str($bruto);
            if (preg_match(self::FORMATO, $dateFim) !== 1) {
                return Resultado::erro('O parâmetro date_fim, quando enviado, precisa estar no formato YYYY-MM-DD.');
            }
            if ($dateFim < $date) {
                return Resultado::erro('date_fim não pode ser anterior a date.');
            }
        }

        $tipo = Args::str($args['tipo'] ?? null);
        $validos = $this->vocabulario->valores('evento_tipo');
        if (! in_array($tipo, $validos, true)) {
            return Resultado::erro("Tipo inválido: {$tipo}. Valores aceitos: ".Args::juntar($validos).'.');
        }

        $projeto = null;
        $projetoNome = ! empty($args['projeto']) ? Args::trim($args['projeto']) : '';
        if ($projetoNome !== '') {
            $catalogo = $this->projetos->catalogo();
            ['resolved' => $resolved, 'naoEncontrados' => $nao] = ResolveProjetos::nomes($catalogo, [$projetoNome]);
            if ($nao !== []) {
                return Resultado::erro("Projeto não encontrado ou ambíguo: {$projetoNome}. Projetos existentes: ".ResolveProjetos::existentes($catalogo).'.');
            }
            $projeto = $resolved[0];
        }

        try {
            $evento = $this->criar->execute(['evento' => [
                'name' => $name, 'date' => $date, 'date_fim' => $dateFim, 'tipo' => $tipo, 'projeto_id' => $projeto['id'] ?? null,
            ]]);
        } catch (LifeosException $e) {
            return Resultado::erro('Erro ao criar o evento: '.$e->errorCode);
        }

        return Resultado::json(['ok' => true, 'evento' => [
            'id' => $evento->evt_id, 'name' => $evento->evt_name, 'date' => $evento->evt_date->format('Y-m-d'),
            'date_fim' => $evento->evt_date_fim?->format('Y-m-d'), 'tipo' => $evento->evt_tipo, 'projeto' => $projeto,
        ]]);
    }
}
