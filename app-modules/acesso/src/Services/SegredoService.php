<?php

declare(strict_types=1);

namespace Modules\Acesso\Services;

use Modules\Acesso\Models\AdminConfig;
use Modules\Core\Contracts\McpTokenResolver;
use Modules\Core\Tenancy\TenantContext;

/**
 * Segredos da instância (admin_configs): lê, grava e acha a entidade dona de um
 * token de conector.
 */
final class SegredoService implements McpTokenResolver
{
    /** Lista branca: sem ela isto viraria um key/value arbitrário. */
    public const CHAVES_VALIDAS = ['mcp_token'];

    /** Mostra pontas o bastante para reconhecer QUAL token, não para usá-lo. */
    public static function mascarar(string $valor): string
    {
        if ($valor === '') {
            return '';
        }

        if (mb_strlen($valor) <= 12) {
            return mb_substr($valor, 0, 2).'…';
        }

        return mb_substr($valor, 0, 7).'…'.mb_substr($valor, -4);
    }

    public function get(string $key): ?AdminConfig
    {
        return AdminConfig::query()->where('cfg_key', $key)->first();
    }

    public function set(string $key, string $value): AdminConfig
    {
        $config = AdminConfig::query()->firstOrNew(['cfg_key' => $key]);
        $config->cfg_value = $value;
        $config->save();

        return $config;
    }

    /**
     * Entidade cujo `mcp_token` é este. Cada instância tem o seu; a comparação é
     * em tempo constante sobre o valor decifrado.
     */
    public function entityIdForMcpToken(string $token): ?string
    {
        if ($token === '') {
            return null;
        }

        $rows = TenantContext::withoutScope(
            fn () => AdminConfig::query()->where('cfg_key', 'mcp_token')->get(),
        );

        foreach ($rows as $row) {
            if (hash_equals((string) $row->cfg_value, $token)) {
                return $row->entity_ent_id;
            }
        }

        return null;
    }
}
