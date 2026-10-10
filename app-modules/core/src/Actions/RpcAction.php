<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\Core\Exceptions\LifeosException;

/**
 * Action cujo contrato de erro é o do LifeOS: em vez de um 422 com a bag de
 * mensagens do Laravel, a primeira regra que falha vira o CÓDIGO CURTO que o
 * front já espera (`invalid_name`, `invalid_tipo`...), com status 400.
 *
 * `rules()` continua sendo o Validator do Laravel; `codes()` mapeia o campo ao
 * código (campo sem entrada vira `invalid_<campo>`). A ordem das regras é a
 * ordem das checagens, como nas funções de hoje.
 */
abstract class RpcAction extends AbstractAction
{
    /**
     * Campo (ou `campo.*`) => código de erro.
     *
     * @return array<string, string>
     */
    protected function codes(): array
    {
        return [];
    }

    protected function validate(array $data): array
    {
        $validator = Validator::make($data, $this->rules(), $this->messages());

        if ($validator->fails()) {
            $field = (string) array_key_first($validator->errors()->messages());
            $codes = $this->codes();

            $code = $codes[$field]
                ?? $codes[preg_replace('/\.\d+/', '.*', $field)]
                ?? $codes[explode('.', $field)[0]]
                ?? 'invalid_'.explode('.', $field)[0];

            throw new LifeosException($code, 400);
        }

        // O Validator só confere (não coage nada que o sanitize() já não tenha
        // normalizado). Devolve a entrada inteira, inclusive o que não tem regra
        // (contexto de borda como `_caller_id`): a Action escolhe o que usa e
        // nunca faz mass-assignment de $data.
        return $data;
    }

    /**
     * Acha o registro do tenant pelo id ou lança `not_found` (404). Um id que nem
     * é UUID também é `not_found`: nunca chega ao banco para virar erro de SQL.
     *
     * @template T of Model
     *
     * @param  class-string<T>  $model
     * @return T
     */
    protected function achar(string $model, mixed $id, string $code = 'not_found'): Model
    {
        $registro = is_string($id) && Str::isUuid($id) ? $model::query()->find($id) : null;

        return $registro ?? throw new LifeosException($code, 404);
    }

    /** String aparada (null/ausente vira ''). */
    protected function texto(mixed $v): string
    {
        return is_scalar($v) ? trim((string) $v) : '';
    }

    /** Texto aparado ou null quando vazio (o "limpar o campo" do front). */
    protected function textoOuNull(mixed $v): ?string
    {
        $s = $this->texto($v);

        return $s === '' ? null : $s;
    }

    /**
     * Lista de strings; qualquer coisa que não seja array vira [] (como as
     * functions de hoje).
     *
     * @return list<string>
     */
    protected function lista(mixed $v): array
    {
        return is_array($v) ? array_values(array_map(fn ($i): string => (string) (is_scalar($i) ? $i : ''), $v)) : [];
    }

    /** `YYYY-MM-DD` válido ou null (formato inválido vira "sem data", não erro). */
    protected function dataOuNull(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1 ? $v : null;
    }
}
