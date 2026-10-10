<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Pest — configuração da suíte
|--------------------------------------------------------------------------
|
| Todo teste em tests/Feature usa o TestCase do Laravel (container + HTTP).
| Testes de Unit que precisem do container declaram `uses(Tests\TestCase::class)`
| no próprio arquivo. O banco de teste é sqlite :memory: (phpunit.xml) e cada
| arquivo que toca o banco usa RefreshDatabase.
|
| Helpers globais de contexto de teste (o análogo do "apiContext" de um sistema
| real) moram aqui embaixo — mantenha-os pequenos e sem regra de negócio.
|
*/

pest()->extend(Tests\TestCase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Monta o contexto mínimo multi-tenant dos testes: cria um tenant e o define
 * como contexto atual. Devolve a Entity criada.
 */
function tenantContext(): Modules\Tenancy\Models\Entity
{
    $entity = Modules\Tenancy\Models\Entity::factory()->create();

    Modules\Core\Tenancy\TenantContext::set($entity->ent_id);

    return $entity;
}

/**
 * Cria uma instância do LifeOS pronta para falar com o contrato: tenant + senha
 * mestre. Devolve [Entity, senha em claro]. O contexto de tenant fica definido.
 *
 * @return array{0: Modules\Tenancy\Models\Entity, 1: string}
 */
function lifeosInstance(string $senha = 'senha-mestre-de-teste'): array
{
    $entity = tenantContext();
    app(Modules\Acesso\Services\TokenService::class)->create($senha, 'mestre', true);

    return [$entity, $senha];
}

/**
 * POST no contrato `{token, action, ...}` de uma function (`lifeos-notas`...).
 *
 * @param  array<string, mixed>  $body
 */
function rpc(string $function, array $body): Illuminate\Testing\TestResponse
{
    return test()->postJson('/functions/v1/'.$function, $body);
}

/**
 * Token do conector MCP da instância atual (o que vai no path da URL).
 */
function lifeosMcpToken(string $token = 'mcp-token-de-teste-0123456789abcdef'): string
{
    app(Modules\Acesso\Services\SegredoService::class)->set('mcp_token', $token);

    return $token;
}

/**
 * Uma chamada JSON-RPC ao servidor MCP (`/functions/v1/lifeos-mcp/<token>`).
 *
 * @param  array<string, mixed>  $params
 */
function mcp(string $token, string $method, array $params = [], int|string|null $id = 1): Illuminate\Testing\TestResponse
{
    return test()->postJson('/functions/v1/lifeos-mcp/'.$token, [
        'jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => (object) $params,
    ]);
}

/**
 * Chama uma tool e devolve [texto, isError]; o texto das tools de consulta é JSON.
 *
 * @param  array<string, mixed>  $args
 * @return array{0: string, 1: bool}
 */
function mcpTool(string $token, string $nome, array $args = []): array
{
    $r = mcp($token, 'tools/call', ['name' => $nome, 'arguments' => (object) $args])->assertOk();

    return [$r->json('result.content.0.text'), $r->json('result.isError')];
}
