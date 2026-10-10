<?php

declare(strict_types=1);

namespace Modules\Financas\Mcp\Tools;

use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Core\Exceptions\LifeosException;
use Modules\Core\Support\Mcp\Args;
use Modules\Core\Support\Mcp\Resultado;
use Modules\Core\Support\Mcp\ToolPorArquivo;
use Modules\Financas\Actions\CriarMovimentacao;

/**
 * `direcao` + `meio` chegam separados (como em search_movimentacoes) e viram o
 * array `tipo` combinado que a tabela guarda: exatamente UMA direção, zero ou
 * mais meios.
 */
final class CreateMovimentacaoTool extends ToolPorArquivo
{
    public function __construct(VocabularioReadModel $vocabulario, private readonly CriarMovimentacao $criar)
    {
        parent::__construct($vocabulario);
    }

    public function ordem(): int
    {
        return 23;
    }

    protected function arquivo(): string
    {
        return dirname(__DIR__, 3).'/resources/mcp/create_movimentacao.json';
    }

    public function chamar(array $args): array
    {
        $name = Args::trim($args['name'] ?? null);
        if ($name === '') {
            return Resultado::erro('O parâmetro name (nome/descrição da movimentação) é obrigatório e não pode ser vazio.');
        }

        $valor = $args['valor'] ?? null;
        if (! (is_int($valor) || is_float($valor) || (is_string($valor) && is_numeric(trim($valor)))) || (float) $valor < 0 || ! is_finite((float) $valor)) {
            return Resultado::erro('O parâmetro valor é obrigatório e precisa ser um número não-negativo.');
        }

        $date = Args::str($args['date'] ?? null);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return Resultado::erro('O parâmetro date é obrigatório e precisa estar no formato YYYY-MM-DD.');
        }

        $direcao = Args::str($args['direcao'] ?? null);
        $direcoes = $this->vocabulario->valores('mov_direcao');
        if (! in_array($direcao, $direcoes, true)) {
            return Resultado::erro("Direção inválida: {$direcao}. Valores aceitos: ".Args::juntar($direcoes).'.');
        }

        $meio = Args::lista($args['meio'] ?? null);
        $meiosValidos = $this->vocabulario->valores('mov_meio');
        $invalidos = array_values(array_filter($meio, fn (string $m): bool => ! in_array($m, $meiosValidos, true)));
        if ($invalidos !== []) {
            return Resultado::erro('Meio(s) inválido(s): '.Args::juntar($invalidos).'. Valores aceitos: '.Args::juntar($meiosValidos).'.');
        }

        try {
            $mov = $this->criar->execute(['movimentacao' => ['name' => $name, 'valor' => (float) $valor, 'date' => $date, 'tipo' => [$direcao, ...$meio]]]);
        } catch (LifeosException $e) {
            return Resultado::erro('Erro ao criar a movimentação: '.$e->errorCode);
        }

        return Resultado::json(['ok' => true, 'movimentacao' => [
            'id' => $mov->mov_id, 'name' => $mov->mov_name, 'valor' => (float) $mov->mov_valor,
            'date' => $mov->mov_date->format('Y-m-d'), 'tipo' => $mov->mov_tipo,
        ]]);
    }
}
