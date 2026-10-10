<?php

declare(strict_types=1);

namespace Modules\Financas\Actions;

use Modules\Core\Actions\RpcAction;
use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Core\Exceptions\LifeosException;
use Modules\Financas\Models\Recorrencia;

abstract class AbstractRecorrenciaAction extends RpcAction
{
    public const MAX_NOME = 120;

    public const MAX_VALOR = 1_000_000;

    public function __construct(protected readonly VocabularioReadModel $vocabulario) {}

    /**
     * Campos válidos nas colunas do model. Mesma ordem de checagem de hoje;
     * `valor_min`/`valor_max` são validados SEMPRE em par.
     *
     * @param  array<string, mixed>  $src
     * @return array<string, mixed>
     */
    protected function campos(array $src, bool $completo): array
    {
        $out = [];

        if ($completo || array_key_exists('nome', $src)) {
            $nome = $this->texto($src['nome'] ?? null);
            ($nome !== '' && mb_strlen($nome) <= self::MAX_NOME) || throw new LifeosException('invalid_nome');
            $out['rec_nome'] = $nome;
        }
        if ($completo || array_key_exists('direcao', $src)) {
            $direcao = $this->texto($src['direcao'] ?? null);
            in_array($direcao, $this->vocabulario->valores('mov_direcao'), true) || throw new LifeosException('invalid_direcao');
            $out['rec_direcao'] = $direcao;
        }
        if ($completo || array_key_exists('valor_min', $src) || array_key_exists('valor_max', $src)) {
            $min = $this->valor($src['valor_min'] ?? null);
            $max = $this->valor($src['valor_max'] ?? null);
            ($min !== null && $max !== null && $max >= $min) || throw new LifeosException('invalid_valor');
            $out['rec_valor_min'] = $min;
            $out['rec_valor_max'] = $max;
        }
        if (array_key_exists('meio', $src)) {
            $meio = $src['meio'] === null ? '' : $this->texto($src['meio']);
            ($meio === '' || in_array($meio, $this->vocabulario->valores('mov_meio'), true)) || throw new LifeosException('invalid_meio');
            $out['rec_meio'] = $meio === '' ? null : $meio;
        }
        if (array_key_exists('dia', $src)) {
            if ($src['dia'] === null || $src['dia'] === '') {
                $out['rec_dia'] = null;
            } else {
                $dia = $src['dia'];
                (is_numeric($dia) && (float) $dia == (int) $dia && (int) $dia >= 1 && (int) $dia <= 31) || throw new LifeosException('invalid_dia');
                $out['rec_dia'] = (int) $dia;
            }
        }
        if (array_key_exists('ativa', $src)) {
            is_bool($src['ativa']) || throw new LifeosException('invalid_ativa');
            $out['rec_ativa'] = $src['ativa'];
        }

        return $out;
    }

    private function valor(mixed $v): ?float
    {
        if ($v === null || $v === '' || ! is_numeric($v)) {
            return null;
        }
        $n = (float) $v;

        return ($n < 0 || $n > self::MAX_VALOR) ? null : round($n, 2);
    }

    /** Chave de comparação do nome: sem acento, minúsculo, espaços colapsados. */
    protected function chave(string $nome): string
    {
        $semAcento = preg_replace('/\p{M}/u', '', \Normalizer::normalize($nome, \Normalizer::FORM_D) ?: $nome) ?? $nome;

        return preg_replace('/\s+/u', ' ', trim(mb_strtolower($semAcento))) ?? '';
    }

    protected function duplicada(string $nome, string $direcao, ?string $exceto = null): bool
    {
        $k = $this->chave($nome);

        return Recorrencia::query()->where('rec_direcao', $direcao)
            ->when($exceto !== null, fn ($q) => $q->where('rec_id', '!=', $exceto))
            ->pluck('rec_nome')
            ->contains(fn (string $n): bool => $this->chave($n) === $k);
    }
}
