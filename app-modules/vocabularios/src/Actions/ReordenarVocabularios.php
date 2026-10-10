<?php

declare(strict_types=1);

namespace Modules\Vocabularios\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Actions\RpcAction;
use Modules\Core\Exceptions\LifeosException;
use Modules\Vocabularios\Models\Vocabulario;

/** Recebe [{id, ordem}, ...]. Só mexe em `ordem`, nunca no valor. */
final class ReordenarVocabularios extends RpcAction
{
    protected function sanitize(array $input): array
    {
        return ['ordens' => $input['ordens'] ?? null];
    }

    protected function rules(): array
    {
        return ['ordens' => ['required', 'array', 'min:1']];
    }

    protected function codes(): array
    {
        return ['ordens' => 'ordens_vazio'];
    }

    protected function handle(array $data): mixed
    {
        $itens = [];
        foreach ($data['ordens'] as $item) {
            $id = trim((string) (is_array($item) ? ($item['id'] ?? '') : ''));
            $ordem = is_array($item) ? ($item['ordem'] ?? null) : null;
            if ($id === '' || ! is_numeric($ordem)) {
                throw new LifeosException('ordens_invalido');
            }
            $itens[] = [$id, (int) $ordem];
        }

        DB::transaction(function () use ($itens): void {
            foreach ($itens as [$id, $ordem]) {
                Vocabulario::query()->whereKey($id)->get()->each->update(['voc_ordem' => $ordem]);
            }
        });

        return count($itens);
    }
}
