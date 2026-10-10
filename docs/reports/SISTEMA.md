# 📚 SISTEMA — Template Laravel Modular (Relatório Geral Consolidado)

> **Documento *evergreen*:** este é o retrato completo e SEMPRE-ATUAL do sistema.
> É atualizado pela skill `/sync-docs` a cada leva de reports pendentes
> (`sync-state.json`) — os relatórios de entrega (`vX.Y.Z/`) são as fotografias
> históricas; este é o filme montado.
>
> **Última sincronização:** 2026-07-07 · reports até `v0.0.1/feat_bootstrap` ·
> por claude-opus-4-8

## Índice

- [Parte I — Visão geral](#parte-i--visão-geral)
- [Parte II — Stack e ambiente](#parte-ii--stack-e-ambiente)
- [Parte III — A arquitetura modular](#parte-iii--a-arquitetura-modular)
- [Parte IV — Passeio guiado pelos módulos](#parte-iv--passeio-guiado-pelos-módulos)
- [Parte V — A camada de dados](#parte-v--a-camada-de-dados)
- [Parte VI — Multi-tenancy](#parte-vi--multi-tenancy)
- [Parte VII — Auditoria](#parte-vii--auditoria)
- [Parte VIII — O fluxo canônico (exemplo executável)](#parte-viii--o-fluxo-canônico-exemplo-executável)
- [Parte IX — Testes e qualidade](#parte-ix--testes-e-qualidade)
- [Parte X — Desenvolvimento orientado a IA](#parte-x--desenvolvimento-orientado-a-ia)
- [Parte XI — Como rodar](#parte-xi--como-rodar)
- [Parte XII — Próximos passos](#parte-xii--próximos-passos)

---

# Parte I — Visão geral

Este repositório é um **template de sistema Laravel** com arquitetura modular
orientada a desenvolvimento com IA. Ainda **não é um produto**: é a fundação
sobre a qual produtos nascem (via skill `/prontuario`). O que ele já contém, de
verdade e testado:

- **Shared kernel** (`core`): multi-tenancy automático, auditoria append-only,
  Actions com template method, adapters de canal, contratos de fronteira.
- **Tenancy mínimo**: o model `Entity` (o tenant) e sua fábrica.
- **Exemplo executável** (`orders`/`payments`/`notifications`): um fluxo
  completo de pedido→cobrança→notificação demonstrando TODOS os padrões do
  template com testes.
- **Fronteiras executáveis**: ArchTest quebra o CI se um módulo importar o
  interno de outro.
- **Disciplina de contexto**: reports versionados + `sync-state.json` + skills.

# Parte II — Stack e ambiente

| Camada | Escolha | Nota |
| --- | --- | --- |
| Linguagem | PHP 8.2+ (`strict_types` em tudo) | ArchTest verifica |
| Framework | Laravel 12 | esqueleto oficial `laravel/laravel` |
| Modularização | `internachi/modular` ^3.0 | módulos = pacotes Composer em `app-modules/` |
| Banco (produção) | PostgreSQL 16 próprio | `compose.yaml`; dev rápido: sqlite |
| Filas/Cache (produção) | Redis 7 via `predis` | filas nomeadas; dev rápido: database |
| Workers | Laravel Horizon ^5 | Linux/produção (`ext-pcntl`); Windows dev: `queue:work` |
| Testes | Pest ^4 (+ plugin laravel) | sqlite `:memory:`, fila `sync` |
| Estilo | Pint (preset laravel + `declare_strict_types`) | `composer lint` |
| Front-end | Qualquer SPA consumindo a API | o backend não renderiza telas |

> O `composer.json` tem **platform-fakes** de `ext-pcntl`/`ext-posix` (8.2) para
> o Horizon instalar no Windows; ele só RODA em Unix — no Windows dev use
> `php artisan queue:work`.

# Parte III — A arquitetura modular

Modular monolith: um único deploy, fronteiras internas. Cada módulo é um pacote
Composer com namespace `Modules\<Modulo>\`. Um módulo **nunca** importa a classe
interna de outro; a travessia legítima é só via `core` (contratos, eventos,
DTOs) ou `config/models.php` (relações Eloquent). A regra é executável:
`tests/Arch/ArchTest.php`.

Detalhes: [`../arquitetura/ARQUITETURA.md`](../arquitetura/ARQUITETURA.md) e
irmãos.

# Parte IV — Passeio guiado pelos módulos

## IV.1 `core` — o shared kernel (permanente)

| Peça | Arquivo | O que faz |
| --- | --- | --- |
| `TenantContext` | `src/Tenancy/` | ponto único do tenant atual; `set/runAs/withoutScope` |
| `EntityScope` | `src/Scopes/` | global scope `WHERE entity_ent_id = tenant atual` |
| `Entityable` | `src/Models/Concerns/` | trait: aplica o scope + preenche o tenant no create |
| `AuditLog` | `src/Models/` | model da trilha append-only (sem Entityable, sem update) |
| `AuditObserver` | `src/Observers/` | base abstrata: diff before/after, scrub `$hidden`, best-effort |
| `AbstractAction` | `src/Actions/` | template method `sanitize→validate→authorize→handle` |
| `HttpInputAdapter` / `McpInputAdapter` | `src/Actions/Input/` | canal → array canônico |
| `audit:query` | `src/Console/Commands/` | trilha de um registro via CLI |
| Contratos/DTOs/Eventos de exemplo | `src/{Contracts,DTOs,Events,Exceptions}/` | `PaymentGateway`, `OrderReadModel`, `PaymentResult`, `OrderSummaryDTO`, `OrderPaid` — [EXEMPLO, removíveis] |

## IV.2 `tenancy` — o tenant (permanente)

`Entity` (`entities`, prefixo `ent_`): o dono de tudo que tem escopo. Cresce com
User/papéis/onboarding quando o sistema real precisar.

## IV.3 `orders` — [EXEMPLO] o molde de módulo de domínio

`PlaceOrderAction` (idempotência por `ref` + 202 + Job), `GetOrderAction`,
`ProcessOrderPaymentJob` (runAs + contrato + evento + retry), `Order`
(convenções completas), `OrderStatus`, `OrderController` fino, `OrderResource`,
`EloquentOrderReadModel`, `OrderObserver` (`$hidden=[ord_internal_notes]`),
rotas `/v1/orders`.

## IV.4 `payments` — [EXEMPLO] o fornecedor de capacidade

`FakePaymentGateway` implementa o contrato: aprova tudo, exceto `999.99` (recusa
de negócio) e `666.66` (lança erro de transporte). Bind no provider.

## IV.5 `notifications` — [EXEMPLO] o reator a eventos

`SendOrderPaidNotification` (queued) ouve `OrderPaid` e loga (placeholder de
e-mail/webhook). Registro explícito no provider.

## IV.6 App host (`app/`) — só borda

`ResolveTenantFromHeader` (**placeholder de auth**: lê `X-Tenant-Id`, valida o
tenant, popula o `TenantContext`; substituir por token com hash antes de
produção). Alias `resolve.tenant` em `bootstrap/app.php`.

# Parte V — A camada de dados

Tabelas de domínio: `entities` (tenancy), `audit_logs` (core), `orders`
(exemplo). Convenções: prefixo de 3 letras em toda coluna, PK UUID, FKs
`{tabela_singular}_{pk}`, timestamps prefixados, SoftDeletes em transacionais,
índice único de idempotência `(entity_ent_id, ord_ref)`. ER completo:
[`../arquitetura/BANCO.md`](../arquitetura/BANCO.md).

# Parte VI — Multi-tenancy

O tenant é resolvido na **borda** (middleware) e vive no `TenantContext`
(session-backed, funciona por-request mesmo sem cookie). Leitura: `EntityScope`
injeta o filtro em toda query de model `Entityable`. Escrita: o trait preenche
`entity_ent_id` no `creating`. Workers: o job carrega o `entity_id` e restaura
com `TenantContext::runAs()` como primeira instrução. Acesso administrativo
deliberado: `withoutGlobalScope` / `TenantContext::withoutScope()`. Tudo isso
está coberto por testes (`tests/Feature/MultiTenant/`).

# Parte VII — Auditoria

Toda mutação de model transacional gera um registro em `audit_logs`
(append-only): `created` (before=null), `updated` (só o diff real, sem
`updated_at`), `deleted` (after=null), `restored`. Colunas sensíveis ficam fora
do diff via `$hidden` do observer concreto. Falha de auditoria NUNCA derruba a
operação (best-effort + log de erro). Consulta: `php artisan audit:query
<tabela> <id>`. Sem FKs: a trilha sobrevive aos registros. Coberto por
`tests/Feature/Audit/`.

# Parte VIII — O fluxo canônico (exemplo executável)

```
POST /v1/orders (X-Tenant-Id)                          [borda resolve tenant]
  → PlaceOrderAction: sanitize→validate→authorize      [template method]
  → idempotência por ref (índice único + short-circuit)
  → Order::create (processing_payment)                  [Entityable + auditoria]
  → ProcessOrderPaymentJob → fila "orders"              [202 Accepted]
Worker:
  → TenantContext::runAs(entity_id)                     [restaura o tenant]
  → guard de idempotência (status)
  → PaymentGateway::charge (contrato; impl. payments)
      aprovado        → paid + event(OrderPaid) → notifications (queued)
      recusa negócio  → payment_failed + motivo (estado final)
      erro transporte → exceção sobe → retry (tries=5, backoff 10/30/60/120s)
```

Este fluxo é o molde de toda operação de escrita relevante de um sistema real.

# Parte IX — Testes e qualidade

**44 testes / 113 assertions verdes · Pint verde · ArchTest verde.**

| Suíte | O que garante |
| --- | --- |
| `Arch` (6) | strict_types em todo módulo; core independente; nenhum import cruzado |
| `Unit/Core` (10) | template method na ordem; validação barra handle; TenantContext runAs/withoutScope com restauração (inclusive sob exceção); precedência do input adapter |
| `Feature/MultiTenant` (5) | isolamento de leitura; preenchimento no create; find cross-tenant = null; escape administrativo |
| `Feature/Audit` (7) | created/updated/deleted/restored; diff real; scrub de sensíveis; sem-mudança não audita; trilha cronológica; sem escopo de tenant |
| `Feature/Orders` (15) | 202+fila nomeada; idempotência; 422 sem efeito; 401 sem tenant; ponta a ponta sync; 404 cross-tenant; colunas internas nunca vazam; job: 3 desfechos + reentrega idempotente + runAs |
| `Feature/HealthCheck` (1) | `/up` 200 |

Comandos: `composer test` · `composer test:coverage` (mín. 70%) ·
`composer lint:check`.

# Parte X — Desenvolvimento orientado a IA

- **CLAUDE.md** — as 10 regras de ouro; o contrato da IA com o repositório.
- **Skills** (`.claude/skills/`): `/prontuario` (nascimento do sistema),
  `/dominio` (carregar contexto de negócio), `/nova-feature` (ciclo completo),
  `/sync-docs` (propagar reports → docs evergreen), `/novo-modulo` (scaffold
  com convenções).
- **Versionamento de contexto**: `sync-state.json` rastreia quais reports já
  foram absorvidos por este documento e pelos docs de domínio. Contrato em
  [`README.md`](README.md).

# Parte XI — Como rodar

```bash
# 1. dependências
composer install
cp .env.example .env && php artisan key:generate

# 2. banco (quickstart: sqlite, zero infra)
php artisan migrate

# 2b. (opcional, produção-like) Postgres 16 + Redis 7
docker compose up -d      # e troque os blocos comentados do .env

# 3. qualidade (deve estar TUDO verde)
composer test && composer lint:check

# 4. subir
composer dev              # serve + queue + logs + vite
# ou: php artisan serve  +  php artisan queue:work

# 5. exercitar o exemplo
# tinker: Modules\Tenancy\Models\Entity::factory()->create()->ent_id
curl -X POST http://localhost:8000/v1/orders \
  -H "X-Tenant-Id: <ent_id>" -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"ref":"demo-1","customer_name":"Maria Silva","amount":100}'
# resposta 202; consulta:
curl http://localhost:8000/v1/orders/<id> -H "X-Tenant-Id: <ent_id>" -H "Accept: application/json"
# auditoria:
php artisan audit:query orders <id>
```

# Parte XII — Próximos passos

O template está completo como fundação. O que ele deliberadamente NÃO traz (vira
trabalho do sistema real):

- **Autenticação real de API** — substituir `ResolveTenantFromHeader` por token
  com hash + rate limit por credencial (ou Sanctum) — ver README §Dependências.
- **CI (GitHub Actions)** — `composer audit` + `lint:check` + `test` em todo
  push/PR (o template roda tudo local; o workflow é ~20 linhas).
- **Os SEUS módulos** — via `/prontuario` + `/novo-modulo`, removendo os
  exemplos.
- **Opcionais por demanda** — Filament (admin), Pulse (observabilidade),
  laravel/mcp (canal IA), dompdf (PDFs)... — documentados no README.

---

### Changelog deste documento

| Data | Mudança | Fonte |
| --- | --- | --- |
| 2026-07-07 | Criação — retrato inicial do template | `v0.0.1/feat_bootstrap` |
