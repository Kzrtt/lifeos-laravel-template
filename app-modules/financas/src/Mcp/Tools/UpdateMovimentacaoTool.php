<?php

declare(strict_types=1);

namespace Modules\Financas\Mcp\Tools;

use Illuminate\Support\Str;
use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Core\Exceptions\LifeosException;
use Modules\Core\Support\Mcp\Args;
use Modules\Core\Support\Mcp\Resultado;
use Modules\Core\Support\Mcp\ToolPorArquivo;
use Modules\Financas\Actions\AtualizarMovimentacao;
use Modules\Financas\Models\Movimentacao;

/**
 * PATCH parcial. `direcao`/`meio` recompõem o `tipo` final a partir do valor ATUAL
 * quando só um dos dois é enviado, para não perder a outra metade do array.
 */
final class UpdateMovimentacaoTool extends ToolPorArquivo
{
    public function __construct(VocabularioReadModel $vocabulario, private readonly AtualizarMovimentacao $atualizar)
    {
        parent::__construct($vocabulario);
    }

    public function ordem(): int
    {
        return 24;
    }

    protected function arquivo(): string
    {
        return dirname(__DIR__, 3).'/resources/mcp/update_movimentacao.json';
    }

    public function chamar(array $args): array
    {
        $id = Args::trim($args['id'] ?? null);
        if ($id === '') {
            return Resultado::erro('O parâmetro id (id da movimentação a editar, retornado por search_movimentacoes) é obrigatório.');
        }

        $patch = [];

        if (Args::tem($args, 'name')) {
            $name = Args::trim($args['name']);
            if ($name === '') {
                return Resultado::erro('O parâmetro name, quando enviado, não pode ser vazio.');
            }
            $patch['name'] = $name;
        }
        if (Args::tem($args, 'valor')) {
            $valor = $args['valor'];
            if (! (is_int($valor) || is_float($valor) || (is_string($valor) && is_numeric(trim($valor)))) || (float) $valor < 0 || ! is_finite((float) $valor)) {
                return Resultado::erro('O parâmetro valor, quando enviado, precisa ser um número não-negativo.');
            }
            $patch['valor'] = (float) $valor;
        }
        if (Args::tem($args, 'date')) {
            $date = Args::str($args['date']);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
                return Resultado::erro('O parâmetro date, quando enviado, precisa estar no formato YYYY-MM-DD.');
            }
            $patch['date'] = $date;
        }

        $direcoes = $this->vocabulario->valores('mov_direcao');
        $meiosValidos = $this->vocabulario->valores('mov_meio');

        $direcao = null;
        if (Args::tem($args, 'direcao')) {
            $direcao = Args::str($args['direcao']);
            if (! in_array($direcao, $direcoes, true)) {
                return Resultado::erro("Direção inválida: {$direcao}. Valores aceitos: ".Args::juntar($direcoes).'.');
            }
        }
        $meio = null;
        if (Args::tem($args, 'meio')) {
            $meio = Args::lista($args['meio']);
            $invalidos = array_values(array_filter($meio, fn (string $m): bool => ! in_array($m, $meiosValidos, true)));
            if ($invalidos !== []) {
                return Resultado::erro('Meio(s) inválido(s): '.Args::juntar($invalidos).'. Valores aceitos: '.Args::juntar($meiosValidos).'.');
            }
        }

        if ($direcao !== null || $meio !== null) {
            $atual = Str::isUuid($id) ? Movimentacao::query()->find($id) : null;
            if ($atual === null) {
                return Resultado::erro("Nenhuma movimentação encontrada com id {$id}.");
            }
            $direcaoAtual = collect($atual->mov_tipo)->first(fn (string $t): bool => in_array($t, $direcoes, true));
            $meioAtual = array_values(array_filter($atual->mov_tipo, fn (string $t): bool => in_array($t, $meiosValidos, true)));
            $patch['tipo'] = [$direcao ?? $direcaoAtual, ...($meio ?? $meioAtual)];
        }

        if ($patch === []) {
            return Resultado::erro('Nenhum campo pra atualizar foi enviado -- envie ao menos um de: name, valor, date, direcao, meio.');
        }

        try {
            $mov = $this->atualizar->execute(['id' => $id, 'patch' => $patch]);
        } catch (LifeosException $e) {
            return $e->errorCode === 'not_found'
                ? Resultado::erro("Nenhuma movimentação encontrada com id {$id}.")
                : Resultado::erro('Erro ao atualizar a movimentação: '.$e->errorCode);
        }

        return Resultado::json(['ok' => true, 'movimentacao' => [
            'id' => $mov->mov_id, 'name' => $mov->mov_name, 'valor' => (float) $mov->mov_valor,
            'date' => $mov->mov_date->format('Y-m-d'), 'tipo' => $mov->mov_tipo,
            'updated_at' => $mov->mov_updated_at?->toIso8601String(),
        ]]);
    }
}
