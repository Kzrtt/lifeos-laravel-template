<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use Illuminate\Support\Facades\Validator;

/**
 * Classe-base de toda Action de domínio. É o coração da regra de ouro nº 3: a
 * lógica de negócio vive numa Action, e os canais de entrada (API REST, MCP, CLI)
 * são adaptadores FINOS que terminam na MESMA Action — zero regra duplicada.
 *
 * Implementa o padrão *template method*: `execute()` é `final` e define a sequência
 * imutável que todo caso de uso segue, nesta ordem:
 *
 *   sanitize  → normaliza/limpa a entrada bruta
 *   validate  → aplica as regras (Validator do Laravel; lança 422 se falhar)
 *   authorize → checa permissão/tenant
 *   handle    → executa o caso de uso (delega aos Services do módulo)
 *
 * Cada Action concreta só preenche o que é seu: obrigatoriamente `rules()` e
 * `handle()`; opcionalmente sobrescreve `sanitize()`/`authorize()`/`messages()`.
 *
 * IMPORTANTE: esta classe é *herança* (uma base compartilhada), NÃO um contrato de
 * fronteira. Por isso vive no `core` mas não é uma interface em Contracts/ — ver
 * docs/arquitetura/IMPLEMENTACAO.md ("Actions NÃO precisam de interface no core").
 */
abstract class AbstractAction
{
    /**
     * Template method: a mesma sequência para todos os canais. É `final` de
     * propósito — nenhuma Action pode alterar a ordem sanitize→validate→authorize→handle.
     *
     * @param  array<string, mixed>  $input  payload já normalizado por um Input adapter
     */
    final public function execute(array $input): mixed
    {
        $data = $this->sanitize($input);
        $data = $this->validate($data);
        $this->authorize($data);

        return $this->handle($data);
    }

    /**
     * Regras de validação (sintaxe do Validator do Laravel). Único método que toda
     * Action é obrigada a definir junto de handle().
     *
     * @return array<string, mixed>
     */
    abstract protected function rules(): array;

    /**
     * O caso de uso em si. Recebe os dados já sanitizados, validados e autorizados.
     *
     * @param  array<string, mixed>  $data
     */
    abstract protected function handle(array $data): mixed;

    /**
     * Normalização da entrada bruta (trim, casts, defaults). Default: identidade.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function sanitize(array $input): array
    {
        return $input;
    }

    /**
     * Valida `$data` contra `rules()` e devolve apenas os dados validados — o mesmo
     * comportamento para todos os canais. Lança ValidationException (HTTP 422) se falhar.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function validate(array $data): array
    {
        return Validator::make($data, $this->rules(), $this->messages())->validate();
    }

    /**
     * Mensagens de validação customizadas. Default: nenhuma.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [];
    }

    /**
     * Autorização (policy/tenant). Default: no-op — a Action concreta sobrescreve
     * quando precisa (ex.: garantir que há tenant no contexto).
     *
     * @param  array<string, mixed>  $data
     */
    protected function authorize(array $data): void {}
}
