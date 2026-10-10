<?php

declare(strict_types=1);

namespace Modules\Memoria\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Memoria\Models\Memoria;

final class ExcluirMemoria extends AbstractMemoriaAction
{
    protected function sanitize(array $input): array
    {
        return ['id' => $this->texto($input['id'] ?? null)];
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
        $memoria = $this->achar(Memoria::class, $data['id']);

        // O "cascade" do banco antigo: os registros vão junto (um a um, auditados).
        DB::transaction(function () use ($memoria): void {
            $memoria->registros()->get()->each->delete();
            $memoria->delete();
        });

        return $data['id'];
    }
}
