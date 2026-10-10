<?php

declare(strict_types=1);

namespace Modules\Agenda\Mcp\Tools;

use Illuminate\Support\Str;
use Modules\Agenda\Actions\AtualizarEvento;
use Modules\Agenda\Models\Evento;
use Modules\Core\Contracts\ProjetoReadModel;
use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Core\Exceptions\LifeosException;
use Modules\Core\Support\Mcp\Args;
use Modules\Core\Support\Mcp\ResolveProjetos;
use Modules\Core\Support\Mcp\Resultado;
use Modules\Core\Support\Mcp\ToolPorArquivo;

/** PATCH parcial; date_fim >= date vale para o PAR final, mesmo quando só um vem. */
final class UpdateEventoTool extends ToolPorArquivo
{
    private const FORMATO = '/^\d{4}-\d{2}-\d{2}$/';

    public function __construct(VocabularioReadModel $vocabulario, private readonly ProjetoReadModel $projetos, private readonly AtualizarEvento $atualizar)
    {
        parent::__construct($vocabulario);
    }

    public function ordem(): int
    {
        return 10;
    }

    protected function arquivo(): string
    {
        return dirname(__DIR__, 3).'/resources/mcp/update_evento.json';
    }

    public function chamar(array $args): array
    {
        $id = Args::trim($args['id'] ?? null);
        if ($id === '') {
            return Resultado::erro('O parâmetro id (id do evento a editar, retornado por search_eventos) é obrigatório.');
        }

        $atual = Str::isUuid($id) ? Evento::query()->find($id) : null;
        if ($atual === null) {
            return Resultado::erro("Nenhum evento encontrado com id {$id}.");
        }

        $patch = [];

        if (Args::tem($args, 'name')) {
            $name = Args::trim($args['name']);
            if ($name === '') {
                return Resultado::erro('O parâmetro name, quando enviado, não pode ser vazio.');
            }
            $patch['name'] = $name;
        }

        $dataFinal = $atual->evt_date->format('Y-m-d');
        if (Args::tem($args, 'date')) {
            $date = Args::str($args['date']);
            if (preg_match(self::FORMATO, $date) !== 1) {
                return Resultado::erro('O parâmetro date, quando enviado, precisa estar no formato YYYY-MM-DD.');
            }
            $patch['date'] = $date;
            $dataFinal = $date;
        }

        $fimFinal = $atual->evt_date_fim?->format('Y-m-d');
        if (Args::tem($args, 'date_fim')) {
            $v = $args['date_fim'];
            if ($v === null || $v === '') {
                $patch['date_fim'] = null;
                $fimFinal = null;
            } else {
                $fim = Args::str($v);
                if (preg_match(self::FORMATO, $fim) !== 1) {
                    return Resultado::erro('O parâmetro date_fim, quando enviado, precisa estar no formato YYYY-MM-DD (ou ""/null pra remover).');
                }
                $patch['date_fim'] = $fim;
                $fimFinal = $fim;
            }
        }
        if ($fimFinal !== null && $fimFinal < $dataFinal) {
            return Resultado::erro('date_fim não pode ser anterior a date.');
        }

        if (Args::tem($args, 'tipo')) {
            $tipo = Args::str($args['tipo']);
            $validos = $this->vocabulario->valores('evento_tipo');
            if (! in_array($tipo, $validos, true)) {
                return Resultado::erro("Tipo inválido: {$tipo}. Valores aceitos: ".Args::juntar($validos).'.');
            }
            $patch['tipo'] = $tipo;
        }

        if (Args::tem($args, 'projeto')) {
            $nome = $args['projeto'] === null ? '' : Args::trim($args['projeto']);
            if ($nome === '') {
                $patch['projeto_id'] = null;
            } else {
                $catalogo = $this->projetos->catalogo();
                ['resolved' => $resolved, 'naoEncontrados' => $nao] = ResolveProjetos::nomes($catalogo, [$nome]);
                if ($nao !== []) {
                    return Resultado::erro("Projeto não encontrado ou ambíguo: {$nome}. Projetos existentes: ".ResolveProjetos::existentes($catalogo).'.');
                }
                $patch['projeto_id'] = $resolved[0]['id'];
            }
        }

        if ($patch === []) {
            return Resultado::erro('Nenhum campo pra atualizar foi enviado -- envie ao menos um de: name, date, date_fim, tipo, projeto.');
        }

        try {
            $evento = $this->atualizar->execute(['id' => $id, 'patch' => $patch]);
        } catch (LifeosException $e) {
            return $e->errorCode === 'not_found'
                ? Resultado::erro("Nenhum evento encontrado com id {$id}.")
                : Resultado::erro('Erro ao atualizar o evento: '.$e->errorCode);
        }

        return Resultado::json(['ok' => true, 'evento' => [
            'id' => $evento->evt_id, 'name' => $evento->evt_name, 'date' => $evento->evt_date->format('Y-m-d'),
            'date_fim' => $evento->evt_date_fim?->format('Y-m-d'), 'tipo' => $evento->evt_tipo,
            'projeto_id' => $evento->projeto_prj_id, 'updated_at' => $evento->evt_updated_at?->toIso8601String(),
        ]]);
    }
}
