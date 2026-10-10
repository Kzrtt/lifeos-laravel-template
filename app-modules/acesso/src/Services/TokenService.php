<?php

declare(strict_types=1);

namespace Modules\Acesso\Services;

use Illuminate\Support\Facades\Hash;
use Modules\Acesso\Models\AccessToken;
use Modules\Core\Tenancy\TenantContext;

/**
 * Tudo que envolve o valor em claro de uma senha. É o único lugar que sabe
 * transformar senha em (hash, hint, len) e achar a senha dona de um valor.
 *
 * Hash não se busca por igualdade, então `findByPlain()` filtra candidatos pelo
 * tamanho e pela máscara guardados e confirma com Hash::check — são poucas
 * linhas por instância.
 */
final class TokenService
{
    /** Abaixo disto a senha entra, mas com aviso (entropia baixa). */
    public const MIN_LEN = 12;

    /**
     * A máscara que a tela de senhas mostra: identifica a senha sem revelá-la.
     * O mesmo valor serve de hint para a busca.
     */
    public static function mask(string $token): string
    {
        $len = mb_strlen($token);

        return match (true) {
            $len === 0 => '?',
            $len <= 4 => str_repeat('•', $len),
            $len <= 10 => mb_substr($token, 0, 2).str_repeat('•', $len - 2),
            default => mb_substr($token, 0, 4).'…'.mb_substr($token, -4),
        };
    }

    /**
     * Grava uma senha nova na entidade do contexto.
     */
    public function create(string $plain, ?string $label, bool $isMaster): AccessToken
    {
        return AccessToken::query()->create([
            'tok_hash' => Hash::make($plain),
            'tok_hint' => self::mask($plain),
            'tok_len' => mb_strlen($plain),
            'tok_is_master' => $isMaster,
            'tok_label' => $label,
        ]);
    }

    /** Troca o valor de uma senha existente. */
    public function replaceValue(AccessToken $token, string $plain): void
    {
        $token->tok_hash = Hash::make($plain);
        $token->tok_hint = self::mask($plain);
        $token->tok_len = mb_strlen($plain);
    }

    /**
     * A senha dona do valor, em QUALQUER entidade (é assim que a entidade é
     * descoberta no login). Null se nenhuma bate.
     */
    public function findByPlain(string $plain): ?AccessToken
    {
        if ($plain === '') {
            return null;
        }

        $candidates = TenantContext::withoutScope(
            fn () => AccessToken::query()
                ->where('tok_len', mb_strlen($plain))
                ->where('tok_hint', self::mask($plain))
                ->get(),
        );

        foreach ($candidates as $candidate) {
            if (Hash::check($plain, $candidate->tok_hash)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Já existe uma senha com este valor na entidade do contexto? `$exceptId`
     * ignora a própria senha numa edição.
     */
    public function existsInEntity(string $plain, ?string $exceptId = null): bool
    {
        $candidates = AccessToken::query()
            ->where('tok_len', mb_strlen($plain))
            ->where('tok_hint', self::mask($plain))
            ->when($exceptId !== null, fn ($q) => $q->where('tok_id', '!=', $exceptId))
            ->get();

        foreach ($candidates as $candidate) {
            if (Hash::check($plain, $candidate->tok_hash)) {
                return true;
            }
        }

        return false;
    }
}
