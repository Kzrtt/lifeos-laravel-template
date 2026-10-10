<?php

declare(strict_types=1);

namespace Modules\Vocabularios\Actions;

use Illuminate\Support\Str;
use Modules\Core\Actions\RpcAction;
use Modules\Core\Exceptions\LifeosException;
use Modules\Vocabularios\Models\Vocabulario;
use Modules\Vocabularios\Services\VocabularioService;

final class ExcluirVocabulario extends RpcAction
{
    public function __construct(private readonly VocabularioService $vocabularios) {}

    protected function sanitize(array $input): array
    {
        return ['id' => trim((string) ($input['id'] ?? ''))];
    }

    protected function rules(): array
    {
        return ['id' => ['required', 'string']];
    }

    protected function codes(): array
    {
        return ['id' => 'missing_id'];
    }

    protected function handle(array $data): mixed
    {
        $atual = Str::isUuid($data['id']) ? Vocabulario::query()->find($data['id']) : null;
        if ($atual === null) {
            throw LifeosException::notFound();
        }

        // Valores que a LÓGICA lê por nome: renomear pode, apagar quebraria o
        // progresso ou o saldo em silêncio.
        if ($atual->voc_protegido) {
            throw LifeosException::conflict('protegido');
        }

        $uso = $this->vocabularios->uso($atual->voc_dominio, $atual->voc_valor) ?? 0;
        if ($uso > 0) {
            throw LifeosException::conflict('em_uso', ['uso' => $uso]);
        }

        $atual->delete();

        return $data['id'];
    }
}
