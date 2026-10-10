<?php

declare(strict_types=1);

namespace Modules\Vocabularios\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Actions\RpcAction;
use Modules\Core\Exceptions\LifeosException;
use Modules\Vocabularios\Models\Vocabulario;
use Modules\Vocabularios\Services\VocabularioService;
use Modules\Vocabularios\Support\Dominios;

/**
 * Renomear é a operação delicada: o valor novo precisa chegar também nas linhas
 * de dado que usam o antigo — os consumidores de cada módulo, numa transação
 * só. Cor e ordem são cosméticas.
 */
final class AtualizarVocabulario extends RpcAction
{
    public function __construct(private readonly VocabularioService $vocabularios) {}

    protected function sanitize(array $input): array
    {
        $ordem = $input['ordem'] ?? null;

        return [
            'id' => trim((string) ($input['id'] ?? '')),
            'novo_valor' => trim((string) ($input['novo_valor'] ?? '')),
            'cor' => trim((string) ($input['cor'] ?? '')),
            'ordem' => is_numeric($ordem) ? (int) $ordem : null,
        ];
    }

    protected function rules(): array
    {
        return [
            'id' => ['required', 'string'],
            'cor' => ['nullable', 'regex:'.CriarVocabulario::HEX],
        ];
    }

    protected function codes(): array
    {
        return ['id' => 'missing_id', 'cor' => 'cor_invalida'];
    }

    /** @return array{vocabulario: Vocabulario, linhas_migradas: ?int} */
    protected function handle(array $data): mixed
    {
        $atual = Str::isUuid($data['id']) ? Vocabulario::query()->find($data['id']) : null;
        if ($atual === null) {
            throw LifeosException::notFound();
        }

        return DB::transaction(function () use ($data, $atual): array {
            $migradas = null;

            if ($data['novo_valor'] !== '' && $data['novo_valor'] !== $atual->voc_valor) {
                $duplicado = Vocabulario::query()
                    ->where('voc_dominio', $atual->voc_dominio)->where('voc_valor', $data['novo_valor'])->exists();
                if ($duplicado) {
                    throw LifeosException::conflict('valor_duplicado');
                }

                if ($this->vocabularios->consumidores($atual->voc_dominio) === []) {
                    throw new LifeosException('dominio_invalido');
                }

                // Primeiro os DADOS, depois o vocabulário: se algo falhar, tudo volta.
                $migradas = $this->vocabularios->renomearNosDados($atual->voc_dominio, $atual->voc_valor, $data['novo_valor']);
                $atual->voc_valor = $data['novo_valor'];
            }

            if (Dominios::LISTA[$atual->voc_dominio]['cor'] ?? false) {
                if ($data['cor'] !== '') {
                    $atual->voc_cor = $data['cor'];
                }
            }
            if ($data['ordem'] !== null) {
                $atual->voc_ordem = $data['ordem'];
            }

            $atual->save();

            return ['vocabulario' => $atual, 'linhas_migradas' => $migradas];
        });
    }
}
