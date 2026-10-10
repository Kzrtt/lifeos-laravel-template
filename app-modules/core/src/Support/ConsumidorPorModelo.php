<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\ConsumidorDeVocabulario;

/**
 * Implementação padrão de ConsumidorDeVocabulario: o módulo só declara, por
 * domínio, qual model e qual coluna guardam o valor (escalar ou `text[]`).
 * Renomear passa por Eloquent (cada registro é salvo, então o observer grava a
 * mudança na auditoria) e contar ignora apagados (SoftDeletes).
 */
abstract class ConsumidorPorModelo implements ConsumidorDeVocabulario
{
    /**
     * domínio => [classe do model, coluna, é array?]
     *
     * @return array<string, array{0: class-string<Model>, 1: string, 2: bool}>
     */
    abstract protected function mapa(): array;

    public function dominios(): array
    {
        return array_keys($this->mapa());
    }

    public function contarUso(string $dominio, string $valor): int
    {
        [$model, $coluna, $array] = $this->mapa()[$dominio];

        return $this->consulta($model, $coluna, $array, $valor)->count();
    }

    public function renomear(string $dominio, string $de, string $para): int
    {
        [$model, $coluna, $array] = $this->mapa()[$dominio];

        $migradas = 0;
        $this->consulta($model, $coluna, $array, $de)->get()->each(function (Model $registro) use ($coluna, $array, $de, $para, &$migradas): void {
            $registro->{$coluna} = $array
                ? array_values(array_map(fn (string $v): string => $v === $de ? $para : $v, $registro->{$coluna}))
                : $para;
            $registro->save();
            $migradas++;
        });

        return $migradas;
    }

    /**
     * @param  class-string<Model>  $model
     * @return \Illuminate\Database\Eloquent\Builder<Model>
     */
    private function consulta(string $model, string $coluna, bool $array, string $valor): \Illuminate\Database\Eloquent\Builder
    {
        $query = $model::query();

        return $array ? $query->whereArrayHas($coluna, $valor) : $query->where($coluna, $valor);
    }
}
