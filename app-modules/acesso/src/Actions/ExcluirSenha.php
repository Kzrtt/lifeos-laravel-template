<?php

declare(strict_types=1);

namespace Modules\Acesso\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Acesso\Models\AccessToken;
use Modules\Core\Actions\RpcAction;
use Modules\Core\Exceptions\LifeosException;

final class ExcluirSenha extends RpcAction
{
    protected function sanitize(array $input): array
    {
        $input['id'] = trim((string) ($input['id'] ?? ''));

        return $input;
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
        $atual = Str::isUuid($data['id']) ? AccessToken::query()->find($data['id']) : null;
        if ($atual === null) {
            throw LifeosException::notFound();
        }

        // Apagar a senha da requisição atual derrubaria a própria sessão; se
        // fosse a última mestre, trancaria o sistema para sempre.
        if (($data['_caller_id'] ?? null) === $atual->tok_id) {
            throw LifeosException::conflict('self_delete');
        }
        if ($atual->tok_is_master && AccessToken::query()->where('tok_is_master', true)->count() <= 1) {
            throw LifeosException::conflict('last_master');
        }

        DB::transaction(function () use ($atual): void {
            // Uma a uma: cada concessão passa pelo observer e vai para a auditoria.
            $atual->pages->each->delete();
            $atual->delete();
        });

        return $data['id'];
    }
}
