<?php

declare(strict_types=1);

namespace Modules\Acesso\Actions;

use Modules\Acesso\Models\AccessToken;
use Modules\Acesso\Services\TokenService;
use Modules\Core\Actions\RpcAction;
use Modules\Core\Exceptions\LifeosException;

/** @phpstan-type Result array{token: AccessToken, warning: ?string} */
final class CriarSenha extends RpcAction
{
    public function __construct(private readonly TokenService $tokens) {}

    protected function sanitize(array $input): array
    {
        $senha = $input['senha'] ?? null;

        if (is_array($senha)) {
            $senha['token'] = trim((string) ($senha['token'] ?? ''));
            $senha['label'] = trim((string) ($senha['label'] ?? '')) ?: null;
        }

        return ['senha' => is_array($senha) ? $senha : null];
    }

    protected function rules(): array
    {
        return [
            'senha' => ['required', 'array'],
            'senha.token' => ['required', 'string'],
        ];
    }

    protected function codes(): array
    {
        return ['senha' => 'missing_senha', 'senha.token' => 'invalid_token'];
    }

    /** @return Result */
    protected function handle(array $data): mixed
    {
        $senha = $data['senha'];

        if ($this->tokens->existsInEntity($senha['token'])) {
            throw LifeosException::conflict('duplicate_token');
        }

        $token = $this->tokens->create($senha['token'], $senha['label'] ?? null, ($senha['is_master'] ?? false) === true);
        $token->setRelation('pages', collect());

        return [
            'token' => $token,
            'warning' => mb_strlen($senha['token']) < TokenService::MIN_LEN ? 'token_curto' : null,
        ];
    }
}
