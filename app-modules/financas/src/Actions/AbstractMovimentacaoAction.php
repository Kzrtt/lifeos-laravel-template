<?php

declare(strict_types=1);

namespace Modules\Financas\Actions;

use Modules\Core\Actions\RpcAction;
use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Core\Exceptions\LifeosException;

/**
 * Validação única da movimentação, usada pela tela (create/update) e pelo
 * webhook do celular. Mesma ordem de checagem e mesmos códigos de hoje.
 */
abstract class AbstractMovimentacaoAction extends RpcAction
{
    public function __construct(protected readonly VocabularioReadModel $vocabulario) {}

    /**
     * Campos válidos já nas colunas do model. `$completo` exige todos (create);
     * sem ele só valida os presentes (update).
     *
     * @param  array<string, mixed>  $in
     * @return array<string, mixed>
     */
    protected function campos(array $in, bool $completo): array
    {
        $out = [];

        if (array_key_exists('name', $in) && $in['name'] !== null) {
            $name = $this->texto($in['name']);
            $name !== '' || throw new LifeosException('invalid_name');
            $out['mov_name'] = $name;
        } elseif ($completo) {
            throw new LifeosException('invalid_name');
        }

        if (array_key_exists('valor', $in) && $in['valor'] !== null) {
            $valor = $in['valor'];
            if (! (is_int($valor) || is_float($valor) || (is_string($valor) && is_numeric(trim($valor)))) || (float) $valor < 0 || ! is_finite((float) $valor)) {
                throw new LifeosException('invalid_valor');
            }
            $out['mov_valor'] = round((float) $valor, 2);
        } elseif ($completo) {
            throw new LifeosException('invalid_valor');
        }

        if (array_key_exists('date', $in) && $in['date'] !== null) {
            $this->dataReal($in['date']) || throw new LifeosException('invalid_date');
            $out['mov_date'] = $in['date'];
        } elseif ($completo) {
            throw new LifeosException('invalid_date');
        }

        if (array_key_exists('tipo', $in) && $in['tipo'] !== null) {
            $out['mov_tipo'] = $this->tipoValido($in['tipo']);
        } elseif ($completo) {
            throw new LifeosException('invalid_tipo');
        }

        return $out;
    }

    /**
     * Exatamente UMA direção; o resto são meios válidos. Direção e meio convivem
     * no mesmo array (herança do Notion).
     *
     * @return list<string>
     */
    private function tipoValido(mixed $tipo): array
    {
        if (! is_array($tipo) || $tipo === []) {
            throw new LifeosException('invalid_tipo');
        }
        $tipo = array_values(array_map(fn ($t): string => (string) (is_scalar($t) ? $t : ''), $tipo));

        $direcoes = $this->vocabulario->valores('mov_direcao');
        $meios = $this->vocabulario->valores('mov_meio');

        $nDirecoes = count(array_filter($tipo, fn (string $t): bool => in_array($t, $direcoes, true)));
        $meiosOk = count(array_filter($tipo, fn (string $t): bool => in_array($t, $direcoes, true) || in_array($t, $meios, true))) === count($tipo);

        ($nDirecoes === 1 && $meiosOk) || throw new LifeosException('invalid_tipo');

        return $tipo;
    }

    protected function dataReal(mixed $v): bool
    {
        return is_string($v)
            && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) === 1
            && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }
}
