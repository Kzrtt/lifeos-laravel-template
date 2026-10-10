<?php

declare(strict_types=1);

namespace Modules\Acesso\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Acesso\Models\AccessToken;
use Modules\Acesso\Services\TokenService;
use Modules\Core\Actions\RpcAction;
use Modules\Core\Exceptions\LifeosException;

final class AtualizarSenha extends RpcAction
{
    public function __construct(private readonly TokenService $tokens) {}

    protected function sanitize(array $input): array
    {
        $input['id'] = trim((string) ($input['id'] ?? ''));

        return $input;
    }

    protected function rules(): array
    {
        return [
            'id' => ['required', 'string'],
            'patch' => ['required', 'array', 'min:1'],
        ];
    }

    protected function codes(): array
    {
        return ['id' => 'missing_id', 'patch' => 'empty_patch'];
    }

    /** @return array{token: AccessToken, warning: ?string, reauth: bool} */
    protected function handle(array $data): mixed
    {
        $patch = $data['patch'];

        $atual = Str::isUuid($data['id']) ? AccessToken::query()->find($data['id']) : null;
        if ($atual === null) {
            throw LifeosException::notFound();
        }

        $warning = null;
        $troca = null;

        if (array_key_exists('token', $patch)) {
            $troca = trim((string) ($patch['token'] ?? ''));
            if ($troca === '') {
                throw new LifeosException('invalid_token');
            }
            if ($this->tokens->existsInEntity($troca, $atual->tok_id)) {
                throw LifeosException::conflict('duplicate_token');
            }
            $warning = mb_strlen($troca) < TokenService::MIN_LEN ? 'token_curto' : null;
        }

        $mudou = $troca !== null;

        if (array_key_exists('label', $patch)) {
            $atual->tok_label = trim((string) ($patch['label'] ?? '')) ?: null;
            $mudou = true;
        }

        if (array_key_exists('is_master', $patch)) {
            $novo = $patch['is_master'] === true;
            // Rebaixar a última mestre deixaria o sistema inacessível.
            if ($atual->tok_is_master && ! $novo && $this->masters() <= 1) {
                throw LifeosException::conflict('last_master');
            }
            $atual->tok_is_master = $novo;
            $mudou = true;
        }

        if (! $mudou) {
            throw new LifeosException('empty_patch');
        }

        // A sessão do browser guarda o valor antigo: a tela precisa re-autenticar
        // quando a senha alterada foi a de quem chamou.
        $reauth = $troca !== null && ($data['_caller_id'] ?? null) === $atual->tok_id;

        DB::transaction(function () use ($atual, $troca): void {
            if ($troca !== null) {
                $this->tokens->replaceValue($atual, $troca);
            }
            $atual->save();
        });

        return ['token' => $atual->load('pages'), 'warning' => $warning, 'reauth' => $reauth];
    }

    private function masters(): int
    {
        return AccessToken::query()->where('tok_is_master', true)->count();
    }
}
