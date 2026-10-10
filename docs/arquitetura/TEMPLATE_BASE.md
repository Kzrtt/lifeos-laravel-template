# 🏗️ Template Laravel Modular — desenvolvimento orientado a IA

> **Um template de sistema Laravel em que a arquitetura é uma jaula executável:
> multi-tenancy automático, auditoria imutável, filas resilientes, fronteiras de
> módulo testadas pelo CI e uma disciplina de documentação com versionamento de
> contexto — para que uma IA produza código correto por
> construção, restando ao arquiteto apenas o que só ele pode fazer: traduzir o
> negócio em modelos de dados e contratos.**

Este repositório **funciona**: `composer install`, `php artisan test` e você tem
**44 testes / 113 assertions verdes** exercitando cada padrão descrito abaixo,
incluindo um fluxo de exemplo completo (pedido → cobrança assíncrona →
notificação). Ele nasceu da generalização de um sistema real de emissão de
NFS-e construído sob esta disciplina — cada decisão aqui foi paga e provada lá.

---

## Índice

1. [A tese: por que este template existe](#1-a-tese-por-que-este-template-existe)
2. [Quickstart (5 minutos)](#2-quickstart-5-minutos)
3. [O tour de 15 minutos (exercitando o exemplo)](#3-o-tour-de-15-minutos-exercitando-o-exemplo)
4. [Anatomia do repositório](#4-anatomia-do-repositório)
5. [A arquitetura em 4 decisões](#5-a-arquitetura-em-4-decisões)
6. [O shared kernel, peça a peça](#6-o-shared-kernel-peça-a-peça)
7. [Multi-tenancy: como funciona (e como remover)](#7-multi-tenancy-como-funciona-e-como-remover)
8. [Auditoria: a observabilidade dos dados](#8-auditoria-a-observabilidade-dos-dados)
9. [Filas e resiliência](#9-filas-e-resiliência)
10. [As três camadas de teste (nada vaza)](#10-as-três-camadas-de-teste-nada-vaza)
11. [O exemplo executável, arquivo por arquivo](#11-o-exemplo-executável-arquivo-por-arquivo)
12. [Desenvolvimento orientado a IA](#12-desenvolvimento-orientado-a-ia)
13. [Dependências (todas, e por quê)](#13-dependências-todas-e-por-quê)
14. [Como nasce um sistema real a partir daqui](#14-como-nasce-um-sistema-real-a-partir-daqui)
15. [FAQ de decisões](#15-faq-de-decisões)

---

## 1. A tese: por que este template existe

### 1.1 O problema

Modelos de IA escrevem código excelente e em volume — e é exatamente isso que os
torna perigosos num codebase sem estrutura. Num monólito tradicional, "o que é
permitido" é implícito: vive na cabeça de quem revisou os últimos 200 PRs. Uma
IA (ou um dev novo) não tem acesso a esse implícito. Ela fará o que o contexto
permitir: importará a classe que não devia, filtrará o tenant à mão (ou
esquecerá), capturará a exceção que precisava subir, devolverá o model cru com a
coluna sensível dentro. Cada um desses erros é *localmente razoável* — e
sistemicamente fatal.

A resposta usual é "revise melhor". Não escala: revisão humana atenta é
exatamente o recurso que a IA deveria economizar.

### 1.2 A resposta: tornar o erro impossível, não improvável

A aposta deste template é outra: **transformar cada regra implícita em
mecanismo executável**. Não "combine de não acessar a tabela do outro módulo" —
um teste de arquitetura que **quebra o CI** se o import existir. Não "lembre de
filtrar por tenant" — um global scope que filtra **toda** query
automaticamente. Não "não esqueça o audit log" — um observer que grava **toda**
mutação sem que o código de negócio saiba. Não "cuidado com retry" — um job em
que capturar a exceção errada faz um teste falhar.

Quando as regras são mecanismos, o espaço de erro da IA colapsa. O que sobra
para errar é a **lógica de negócio** — e essa é exatamente a parte que o
profissional responsável define, documenta (`docs/dominio/`) e valida. A divisão
de trabalho fica honesta:

| Quem | Faz o quê |
| --- | --- |
| **O arquiteto (humano)** | decide o negócio, modela dados e contratos, particiona módulos, escreve/valida os docs de domínio, revisa o que é decisão |
| **A IA** | implementa dentro das grades, escreve os testes, mantém docs sincronizadas, executa o ciclo de feature |
| **Os mecanismos** | garantem que a implementação não pode violar tenancy, fronteira, auditoria, resiliência, estilo |

### 1.3 As grades deste template

1. **Fronteira modular executável** — `internachi/modular` + ArchTest: um módulo
   nunca importa o interno de outro; o CI quebra.
2. **Multi-tenancy por construção** — `Entityable`/`EntityScope`/`TenantContext`:
   vazamento entre tenants deixa de ser "bug possível" e vira "código que não
   compila com os testes".
3. **Auditoria universal e imutável** — `AuditObserver` + `audit_logs`
   append-only: toda mutação tem trilha, com colunas sensíveis excluídas por
   declaração.
4. **Resiliência assíncrona disciplinada** — 202 + Job + filas nomeadas +
   idempotência + a lei "transporte lança, negócio retorna".
5. **Canal-agnosticismo** — Actions com template method + input adapters: REST,
   MCP e CLI terminam na MESMA regra, escrita uma vez.
6. **Contexto versionado** — reports imutáveis + `SISTEMA.md` evergreen +
   `sync-state.json`: a IA de amanhã sabe exatamente o que a IA de hoje entregou
   e o que ainda não foi absorvido pela documentação.
7. **Regras da casa carregadas por padrão** — `CLAUDE.md` (10 regras de ouro) +
   5 skills operacionais em `.claude/skills/`.

O resto deste README destrincha cada grade — o que é, por que assim, e onde
está o código.

---

## 2. Quickstart (5 minutos)

Pré-requisitos: PHP 8.2+ (com `pdo_sqlite`), Composer 2. Docker opcional.

```bash
git clone <este-repo> meu-sistema && cd meu-sistema

composer install
cp .env.example .env
php artisan key:generate
php artisan migrate            # sqlite: zero infra

composer test                  # 44 testes verdes — se não, algo está errado
composer lint:check            # Pint verde

php artisan serve              # http://localhost:8000
```

Produção-like (PostgreSQL 16 + Redis 7):

```bash
docker compose up -d
# no .env: descomente os blocos pgsql e redis (estão comentados lado a lado)
php artisan migrate
php artisan queue:work         # Windows dev; Linux/produção: php artisan horizon
```

> **Para iniciar um sistema REAL** (não só rodar o template): abra o projeto no
> Claude Code e rode **`/prontuario`** — a entrevista guiada que gera os docs de
> domínio, renomeia o projeto/namespace e remove os exemplos. Ver
> [§14](#14-como-nasce-um-sistema-real-a-partir-daqui).

---

## 3. O tour de 15 minutos (exercitando o exemplo)

O template traz um fluxo de negócio de exemplo — pedidos que cobram pagamento
assincronamente — cuja única função é **demonstrar cada mecanismo com código
executável**. Rode-o:

```bash
# 1. crie um tenant e pegue o id
php artisan tinker --execute="echo Modules\Tenancy\Models\Entity::factory()->create()->ent_id;"

# 2. crie um pedido (repare no 202 — o processamento é assíncrono)
curl -X POST http://localhost:8000/v1/orders \
  -H "X-Tenant-Id: <ent_id>" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"ref":"demo-1","customer_name":"Maria Silva","amount":100}'

# 3. (se estiver usando fila de verdade, rode o worker; com QUEUE_CONNECTION=database)
php artisan queue:work --once

# 4. consulte — status "paid"
curl http://localhost:8000/v1/orders/<id> -H "X-Tenant-Id: <ent_id>" -H "Accept: application/json"

# 5. a trilha de auditoria conta a história (created -> updated paid)
php artisan audit:query orders <id>

# 6. repita o passo 2 com a MESMA ref — nenhum pedido novo (idempotência)
# 7. tente com amount 999.99 — recusa de negócio (payment_failed, sem retry)
# 8. tente com amount 666.66 — erro de transporte (job re-tenta com backoff)
# 9. tente com o X-Tenant-Id de OUTRO tenant no passo 4 — 404 (isolamento)
```

Cada um desses comportamentos tem um teste correspondente em
`tests/Feature/Orders/` — o exemplo é o gabarito vivo do sistema que você vai
construir.

---

## 4. Anatomia do repositório

```
├── CLAUDE.md                 # ⭐ o contrato da IA: 10 regras de ouro, mapa, comandos
├── README.md                 # este arquivo — o PORQUÊ de tudo
├── .claude/skills/           # ⭐ os "programas" da IA
│   ├── prontuario/           #   nascimento de um sistema real (entrevista → docs)
│   ├── dominio/              #   carrega contexto de negócio para a sessão
│   ├── nova-feature/         #   o ciclo de feature completo
│   ├── sync-docs/            #   propaga reports → docs evergreen (sync-state)
│   └── novo-modulo/          #   scaffold de módulo com TODAS as convenções
├── app/                      # host ENXUTA — só borda
│   └── Http/Middleware/ResolveTenantFromHeader.php   # ⚠️ placeholder de auth
├── app-modules/              # ⭐ TODO o domínio vive aqui
│   ├── core/                 #   shared kernel (permanente) — ver §6
│   ├── tenancy/              #   o tenant (permanente) — ver §7
│   ├── orders/               #   [EXEMPLO] o molde de módulo de domínio — ver §11
│   ├── payments/             #   [EXEMPLO] fornecedor de capacidade (contrato)
│   └── notifications/        #   [EXEMPLO] reator a eventos
├── config/
│   ├── app-modules.php       # namespace dos módulos (Modules\ → renomeado no prontuário)
│   └── models.php            # relações Eloquent cross-módulo (sem furar fronteira)
├── docs/
│   ├── README.md             # índice e mapa de leitura
│   ├── arquitetura/          # COMO: 8 documentos (ver §5)
│   ├── dominio/              # O QUE: regras de negócio (nasce do /prontuario)
│   └── reports/              # ⭐ entregas + SISTEMA.md evergreen + sync-state.json
├── tests/
│   ├── Arch/ArchTest.php     # ⭐ a fronteira como regra executável
│   ├── Unit/                 # kernel puro (Action, TenantContext, adapters)
│   └── Feature/              # HTTP, tenancy, auditoria, jobs — pelo lado de fora
├── compose.yaml              # PostgreSQL 16 + Redis 7 locais
├── phpunit.xml               # suítes Unit/Feature/Modules/Arch; sqlite :memory:
└── pint.json                 # preset laravel + declare_strict_types
```

---

## 5. A arquitetura em 4 decisões

Resumo executivo — cada decisão tem um documento inteiro em `docs/arquitetura/`.

### Decisão 1 — Modular monolith (não camadas, não microsserviços)

O código se organiza por **capacidade de negócio** (`app-modules/orders`), não
por tipo técnico (`app/Services`). Um único deploy, um único banco — as
fronteiras são internas, no código. Cada módulo é um pacote Composer real
(autoload PSR-4 próprio, provider próprio, migrations/rotas/testes próprios), o
que torna a fronteira *natural* em vez de burocrática — e a extração futura para
pacote independente, trivial.
→ [`docs/arquitetura/ARQUITETURA.md`](docs/arquitetura/ARQUITETURA.md) ·
[`MODULOS.md`](docs/arquitetura/MODULOS.md)

### Decisão 2 — Shared kernel mínimo (`core`)

Tudo que é transversal E sem regra de negócio (tenancy, auditoria, contratos,
DTOs, eventos, a base das Actions) vive num módulo `core` do qual todos dependem
e que **não depende de ninguém**. O default para "isso vai pro core?" é **não**.
→ [`docs/arquitetura/CORE.md`](docs/arquitetura/CORE.md)

### Decisão 3 — Três mecanismos de comunicação, e só três

| Precisa de... | Use | Exemplo no template |
| --- | --- | --- |
| capacidade de outro módulo, com retorno | **contrato** (interface no core + bind no provider do dono) | `orders` → `PaymentGateway` ← `payments` |
| avisar que algo aconteceu | **evento de domínio** (classe no core; emissor não conhece ouvintes) | `OrderPaid` → `notifications` |
| ler dados de outro módulo | **read model → DTO** (o model nunca cruza a fronteira) | `OrderReadModel` → `OrderSummaryDTO` |

Relações Eloquent cross-módulo passam por `config/models.php`. Todo o resto é
import proibido.
→ [`docs/arquitetura/COMUNICACAO.md`](docs/arquitetura/COMUNICACAO.md)

### Decisão 4 — A fronteira é um teste, não uma promessa

`tests/Arch/ArchTest.php` (Pest arch): strict_types em todo módulo; o core não
importa domínio; nenhum módulo importa o interno de outro. Módulo novo entra na
constante `DOMAIN_MODULES` e ganha as regras de graça. **É isso que muda o jogo
com IA**: a regra não precisa ser lembrada — ela é descoberta no vermelho do
teste e corrigida na mesma sessão, sem revisor humano de plantão.
→ [`docs/arquitetura/FRONTEIRAS.md`](docs/arquitetura/FRONTEIRAS.md)

---

## 6. O shared kernel, peça a peça

Tudo em `app-modules/core/src/`. Cada peça existe para eliminar uma classe
inteira de bugs:

| Peça | Elimina o bug | Como |
| --- | --- | --- |
| `Tenancy/TenantContext` | "cada lugar resolve o tenant de um jeito" | ponto ÚNICO: `id()`, `set()`, `runAs()` (jobs), `withoutScope()` (admin) — com restauração garantida por `finally` |
| `Scopes/EntityScope` | "esqueci o where do tenant" | global scope injeta `WHERE entity_ent_id = ?` em TODA query do model |
| `Models/Concerns/Entityable` | "criei com o tenant errado" | preenche `entity_ent_id` no `creating` a partir do contexto; coluna NOT NULL barra criação órfã |
| `Observers/AuditObserver` | "ninguém sabe quem mudou o quê" | base abstrata: grava diff before/after em `audit_logs` em created/updated/deleted/restored; `$hidden` exclui sensíveis; best-effort (nunca derruba a operação) |
| `Models/AuditLog` | "a trilha foi adulterada" | append-only: sem `updated_at`, nunca sofre update/delete, sem FKs (sobrevive aos registros), sem escopo de tenant (visão administrativa) |
| `Actions/AbstractAction` | "cada endpoint valida de um jeito; regra duplicada entre canais" | template method `final`: `sanitize → validate → authorize → handle`, a MESMA sequência para todo canal |
| `Actions/Input/HttpInputAdapter` · `McpInputAdapter` | "a Action conhece o Request/a Tool" | cada canal vira o MESMO array canônico; canal novo = adapter novo, zero regra tocada |
| `Console/Commands/AuditQueryCommand` | "auditoria que ninguém consegue ler" | `php artisan audit:query <tabela> <id>` — a história do registro no terminal |

E os artefatos de fronteira do exemplo (marcados `[EXEMPLO — REMOVÍVEL]` no
docblock): `Contracts/PaymentGateway`, `Contracts/OrderReadModel`,
`DTOs/PaymentResult`, `DTOs/OrderSummaryDTO`, `Events/OrderPaid`,
`Exceptions/PaymentGatewayUnavailableException`. Ao construir o sistema real,
você os remove e cria os SEUS no mesmo molde.

---

## 7. Multi-tenancy: como funciona (e como remover)

O desenho em uma frase: **a borda resolve o tenant uma vez; todo o resto do
sistema pergunta ao `TenantContext` e é filtrado automaticamente.**

- **HTTP:** o middleware de borda autentica a credencial, descobre o tenant e
  chama `TenantContext::set()`. O template traz um **placeholder didático**
  (`ResolveTenantFromHeader`, header `X-Tenant-Id` em claro) que demonstra o
  *lugar* do mecanismo — o arquivo grita em maiúsculas que deve ser substituído
  por autenticação real (token com hash + rate limit) antes de produção.
- **Leitura:** `EntityScope` (global scope) adiciona o `WHERE` do tenant em toda
  query de todo model `Entityable`. `Order::find($idDeOutroTenant)` → `null` →
  404. Sem contexto (CLI/seeder), o filtro não se aplica — e o acesso
  administrativo deliberado usa `withoutGlobalScope`/`withoutScope()`.
- **Escrita:** o trait preenche `entity_ent_id` no `creating`. Sem contexto e
  sem valor explícito → o banco barra (NOT NULL). Impossível criar registro
  órfão.
- **Workers (o ponto que todo mundo erra):** a fila não tem sessão. Por isso o
  job **carrega o `entity_id` no payload** e a PRIMEIRA instrução do `handle()`
  é `TenantContext::runAs($this->entityId, fn () => ...)`. O template tem um
  teste que simula exatamente o worker sem contexto.

**Single-tenant?** Não desmonte nada: não use `Entityable` nos models, adapte ou
remova o módulo `tenancy` e o middleware. Auditoria, Actions, contratos e filas
continuam idênticos — e o caminho de volta fica pavimentado se o produto virar
SaaS.

---

## 8. Auditoria: a observabilidade dos dados

A pergunta que um sistema sério precisa responder em segundos: **"o que
aconteceu com este registro, quando, por quem, e qual era o valor antes?"**

O mecanismo (tudo no `core`, custo por model: ~3 linhas):

```php
// 1. o model declara o observer
#[ObservedBy([OrderObserver::class])]
final class Order extends Model { ... }

// 2. o observer concreto só declara o que NÃO pode entrar no diff
final class OrderObserver extends AuditObserver
{
    protected array $hidden = ['ord_internal_notes'];
}
```

Tudo o mais é herdado: `created` (before=null), `updated` (**só o diff real** —
sem `updated_at`, sem falso-positivo quando nada mudou), `deleted` (after=null),
`restored`. Cada registro carrega tenant, usuário autenticado (se houver),
tabela, id, ação e os JSONs before/after. Invariantes:

- **Append-only.** Nenhum código atualiza ou deleta `audit_logs`. Sem
  `updated_at` no model. Sem FKs — a trilha sobrevive ao registro auditado.
- **Best-effort.** Falha ao auditar loga o erro e NUNCA aborta a operação de
  negócio (auditoria é observação, não participante da transação).
- **Sensível não entra.** O `$hidden` do observer exclui colunas do diff — e há
  teste garantindo (o análogo de senha cifrada/XML assinado num sistema real).
- **Consultável.** `php artisan audit:query orders <uuid>` → a história em
  tabela. Num sistema com painel admin, a mesma tabela vira tela read-only.

Combinada com SoftDeletes (nada de domínio some fisicamente) e com os reports
versionados (§12), a auditoria fecha o círculo de observabilidade: **dados,
código e contexto — os três têm história imutável.**

---

## 9. Filas e resiliência

Regra de ouro nº 7: **trabalho pesado é assíncrono e resiliente.**

- **O endpoint devolve 202, não o resultado.** Criação de recurso que depende de
  processamento (cobrança, emissão, integração) persiste em estado
  `processing_*`, despacha o Job e responde imediatamente. O cliente acompanha
  por GET (ou webhook, quando o sistema tiver).
- **Filas nomeadas por prioridade de negócio.** `->onQueue('orders')` — em
  produção, o Horizon dá workers dedicados por fila (o exemplo: emissões nunca
  disputam worker com e-mails). Nomes de fila são operação, não módulos.
- **As três leis do job** (todas com teste no exemplo):
  1. **Restaure o tenant primeiro** — `TenantContext::runAs()` na primeira linha.
  2. **Seja idempotente** — a fila PODE reentregar; um guard de status barato
     evita efeito duplo.
  3. **Transporte lança, negócio retorna.** Timeout/5xx/indisponibilidade
     sobem como exceção → retry automático (`tries=5`, backoff exponencial).
     Recusa de negócio (cartão recusado, documento inválido) é retorno normal →
     estado final, sem retry. Um `try/catch (Throwable)` num job é o jeito mais
     rápido de desligar a resiliência do sistema inteiro.
- **Idempotência de borda:** operações de escrita aceitam `ref` do cliente;
  índice único `(tenant, ref)` + short-circuit na Action = repetir o POST é
  seguro por construção.
- **Dev × produção:** quickstart usa `QUEUE_CONNECTION=database`; produção usa
  Redis + Horizon (Linux — no Windows dev, `queue:work`; o `composer.json` tem
  platform-fakes de `ext-pcntl`/`ext-posix` só para o Horizon INSTALAR no
  Windows).

---

## 10. As três camadas de teste (nada vaza)

O template não persegue cobertura por vaidade — cada camada guarda um tipo de
vazamento:

| Camada | Suíte | O que impede de vazar |
| --- | --- | --- |
| **Unit (domínio puro)** | `tests/Unit/` | a lógica do kernel: ordem do template method, restauração de contexto (inclusive sob exceção), precedência de input. Rápidos, sem banco. |
| **Feature (fronteira HTTP — o lado de fora)** | `tests/Feature/` | o que o CLIENTE vê: 202/404/422/401 corretos, isolamento entre tenants, colunas internas jamais no JSON, idempotência real, auditoria gravada, os 3 desfechos do job. Testam o sistema como caixa-preta pela API. |
| **Arch (fronteira modular — o lado de dentro)** | `tests/Arch/` | o que o CÓDIGO pode fazer: imports cruzados, dependência reversa do core, arquivos sem strict_types. |

Configuração deliberada: **sqlite `:memory:`** (suíte inteira em ~4s — teste
lento é teste que a IA para de rodar) e **fila `sync`** (o job roda inline; o
teste asserta o efeito final — e um teste e2e prova o fluxo POST→job→paid numa
tacada). Cobertura mínima: 70% (`composer test:coverage`).

Ao construir o sistema real, os testes do exemplo são o **gabarito**: para cada
model novo, os testes de tenancy+auditoria; para cada endpoint, os de
status/validação/vazamento; para cada job, os de desfecho triplo+reentrega.

---

## 11. O exemplo executável, arquivo por arquivo

O caminho de leitura recomendado (30 min que ensinam o template inteiro):

1. `app-modules/orders/routes/orders-routes.php` — rotas do módulo, middleware
   de borda, prefixo `/v1`.
2. `app-modules/orders/src/Http/Controllers/OrderController.php` — o adapter
   fino: HTTP ⇄ Action, e nada mais.
3. `app-modules/orders/src/Actions/PlaceOrderAction.php` — **o arquivo mais
   importante**: sanitize/rules/authorize herdados, idempotência por `ref`,
   criação em estado `processing`, dispatch com o tenant no payload, 202.
4. `app-modules/orders/src/Models/Order.php` — TODAS as convenções de model:
   prefixo `ord_`, UUID, SoftDeletes, `Entityable`, `#[ObservedBy]`, casts com
   enum.
5. `app-modules/orders/src/Jobs/ProcessOrderPaymentJob.php` — as três leis do
   job, o consumo do contrato, o evento.
6. `app-modules/core/src/Contracts/PaymentGateway.php` +
   `app-modules/payments/src/Gateways/FakePaymentGateway.php` +
   `PaymentsServiceProvider.php` — os três arquivos de uma fronteira: porta,
   implementação, bind.
7. `app-modules/notifications/src/Listeners/SendOrderPaidNotification.php` — o
   reator: queued, sem que `orders` saiba que existe.
8. `app-modules/orders/src/ReadModels/EloquentOrderReadModel.php` — leitura
   através da fronteira: DTO sai, model não.
9. `tests/Feature/Orders/` — os comportamentos acima, um a um, como caixa-preta.

Tudo marcado `[EXEMPLO — REMOVÍVEL]` sai no `/prontuario`; o que fica é o molde
mental.

---

## 12. Desenvolvimento orientado a IA

A camada que transforma "um bom projeto Laravel" em "um sistema que uma IA
desenvolve com segurança".

### 12.1 CLAUDE.md — o contrato

Carregado em toda sessão do Claude Code. Contém: a visão do sistema (preenchida
pelo prontuário), a stack, as **10 regras de ouro** (cada uma amarrada a um
mecanismo executável), o mapa de pastas, o fluxo canônico, os comandos e o ciclo
de feature. É deliberadamente denso: é o mínimo que QUALQUER sessão precisa
saber antes de tocar no código.

### 12.2 As cinco skills (`.claude/skills/`)

| Skill | O que faz | Quando roda |
| --- | --- | --- |
| **`/prontuario`** | a anamnese do sistema: entrevista extensa (identidade, tenancy, agregados, regras, integrações, operação) → gera `docs/dominio/*.md` → desenha módulos → renomeia projeto/namespace → remove exemplos | uma vez, no nascimento (ou para revisar o domínio) |
| **`/dominio`** | carrega o contexto de negócio: CLAUDE.md → sync-state (avisa se a doc está atrás do código!) → SISTEMA.md → docs de domínio → código se preciso. Responde com fontes citadas | sempre que a pergunta/tarefa envolver negócio |
| **`/nova-feature`** | o ciclo completo: contexto → branch → blocos com teste → suíte verde → report → sync-state `pending` → merge | toda feature |
| **`/sync-docs`** | o pagamento da dívida de contexto: processa reports `pending` (mais antigo primeiro), atualiza SISTEMA.md/domínio/CLAUDE.md/BANCO.md, marca `synced` | quando acumular pendências / ao fechar release |
| **`/novo-modulo`** | scaffold com TODAS as convenções + registro no ArchTest e nas docs (módulo fora do `DOMAIN_MODULES` é módulo fora da lei) | quando nasce um bounded context |

### 12.3 O versionamento de contexto (`sync-state.json`)

O problema real que ele resolve: reports de entrega preservam contexto histórico
maravilhosamente, mas ninguém (humano ou IA) relê 12 reports para saber o estado
atual — e a documentação evergreen fica silenciosamente para trás do código, até
que alguém confia nela e erra.

A solução é um índice máquina-legível com um registro por report:

```jsonc
{
  "current_release": "v0.0.1",
  "last_sync": { "at": "...", "by": "...", "reports_processed": [...] },
  "targets": [ /* os docs evergreen que o sync mantém */ ],
  "reports": [
    { "id": "v0.0.1/feat_bootstrap", "status": "synced", "synced_at": "..." },
    { "id": "v0.0.2/feat_x",         "status": "pending", "synced_at": null }
  ]
}
```

O contrato completo (quem cria a entrada, quem pode marcar `synced`, ordem de
processamento, resolução de conflito) está em
[`docs/reports/README.md`](docs/reports/README.md). O efeito prático: **a
dívida de documentação deixa de ser invisível** — ela tem nome, data e fila; a
skill `/dominio` avisa quando o contexto está defasado; e o `/sync-docs`
processa exatamente o delta, nunca "relê tudo".

### 12.4 O ciclo completo, visto de cima

```
/prontuario (1x)  →  docs/dominio nascem, módulos definidos, exemplos removidos
      │
      ▼
/nova-feature  →  branch → código+testes (verdes) → report → sync-state pending
      │                                                        │
      ▼                                                        ▼
   merge main                                            /sync-docs
      │                                                        │
      ▼                                                        ▼
/dominio (qualquer sessão futura)  ←  SISTEMA.md/dominio SEMPRE fiéis ao código
```

---

## 13. Dependências (todas, e por quê)

### 13.1 Produção (instaladas)

| Pacote | Versão | Por quê |
| --- | --- | --- |
| `laravel/framework` | ^12.0 | a base. Linha 12 por maturidade do ecossistema (Horizon/Pest/modular todos estáveis nela) |
| `internachi/modular` | ^3.0 | modularização com convenções NATIVAS do Laravel (path repositories + package discovery); leve; extração de módulo para pacote é trivial. Escolhido sobre `nwidart/laravel-modules` — ver FAQ |
| `predis/predis` | ^3.5 | client Redis em PHP puro — zero extensão nativa para instalar, mesma API; troque por `phpredis` se a extensão estiver disponível e o benchmark justificar |
| `laravel/horizon` | ^5.47 | dashboard + supervisor de workers Redis com métricas, retry e balanceamento por fila. Unix-only em runtime (`ext-pcntl`); instala no Windows via platform-fakes no composer.json |
| `laravel/tinker` | ^2.10 | REPL — indispensável para explorar o domínio |

### 13.2 Desenvolvimento (instaladas)

| Pacote | Por quê |
| --- | --- |
| `pestphp/pest` ^4 + `pest-plugin-laravel` | sintaxe expressiva, e o **plugin arch** — a fronteira executável depende dele |
| `laravel/pint` | estilo automatizado; `declare_strict_types` forçado em todo arquivo |
| `laravel/pail` | tail de logs no terminal (`composer dev` já o inclui) |
| `laravel/sail` / `mockery` / `faker` / `collision` | padrão do ecossistema |

### 13.3 Opcionais documentados (instale quando o sistema pedir)

| Pacote | Quando | Nota de integração |
| --- | --- | --- |
| **`laravel/sanctum`** | autenticação de API rápida | OU um `api-tokens` próprio (token com hash + ambiente como escopo + rate limit por token) se precisar de controle fino — o desenho está em `ResolveTenantFromHeader` (docblock) |
| **`filament/filament`** | painel admin CRUD | Resources DENTRO de cada módulo (`src/Filament/Resources/`); o painel central os descobre; cross-módulo via read model |
| **`laravel/pulse`** | observabilidade de app (slow queries, jobs, exceptions) | complementa a auditoria de DADOS (§8) com a de RUNTIME |
| **`laravel/mcp`** | expor tools para agentes de IA | Tools finas sobre as MESMAS Actions (o `McpInputAdapter` já existe no core); tenant resolvido do token ANTES da Action |
| **`inertiajs/inertia-laravel` + React/Vue** | se optar por front acoplado em vez de SPA separada | páginas consomem read models/DTOs, nunca Eloquent cru de outro módulo |
| **`barryvdh/laravel-dompdf`** | geração de PDFs (documentos, recibos) | dentro do módulo dono do documento |
| **`spatie/laravel-data`** | DTOs mais ricos (cast/validação) | os DTOs do core são classes `readonly` puras de propósito — adote spatie/data apenas se a complexidade justificar |

### 13.4 Infra

| Serviço | Papel |
| --- | --- |
| PostgreSQL 16 (próprio) | banco de produção — sem Postgres-as-a-service; `compose.yaml` sobe local |
| Redis 7 | filas + cache em produção |
| sqlite | dev rápido e testes (`:memory:`) |

---

## 14. Como nasce um sistema real a partir daqui

1. **Clone e valide**: quickstart do §2 — suíte verde é o ponto de partida
   inegociável.
2. **`/prontuario`** no Claude Code: a entrevista (identidade → tenancy →
   agregados/ciclos de vida → regras com números → integrações → operação) gera
   `docs/dominio/*.md`, o mapa de módulos, renomeia `Modules\` → `SeuSistema\`
   e remove os exemplos. Reserve 1–2 horas de atenção real: **é o investimento
   de maior retorno do projeto** — cada resposta vira contexto permanente de
   todas as sessões de IA futuras.
3. **Substitua o placeholder de auth** pela sua borda real (token com hash /
   Sanctum) — mantendo o alias `resolve.tenant`, nenhuma rota muda.
4. **`/novo-modulo` + `/nova-feature`** para cada capacidade, sempre pelo ciclo:
   contexto → branch → blocos testados → report → sync-state.
5. **`/sync-docs`** a cada leva de entregas — a documentação evergreen nunca
   fica para trás.
6. **CI** (recomendado cedo): workflow de ~20 linhas rodando `composer audit` +
   `composer lint:check` + `composer test` em push/PR — os três já são as cercas
   locais; o CI só as torna públicas.

---

## 15. FAQ de decisões

**Por que `internachi/modular` e não `nwidart/laravel-modules`?**
O nwidart brilha quando módulos são plugáveis em runtime (CMS, marketplace de
extensões). Para modularização de organização interna, o InterNACHI usa os
mecanismos que o Laravel JÁ tem (path repositories + package discovery) — menos
mágica, menos lock-in, e cada módulo já nasce pacote Composer extraível.

**Por que UUID + prefixo de 3 letras em toda coluna?**
UUID: IDs gerados na aplicação (o job pode referenciar o registro antes do
INSERT confirmar), não-enumeráveis em API pública, mescláveis entre ambientes.
Prefixo: toda coluna é rastreável à tabela em joins, logs e — decisivo — nos
diffs de `audit_logs`, que ficam legíveis sem contexto (`ord_status` não se
confunde com `ent_status` três anos depois).

**Por que Actions em vez de Controllers gordos ou FormRequests?**
Porque o Controller é UM canal. No momento em que o sistema ganha MCP (tools de
IA), CLI ou fila interna, a regra no Controller teria de ser duplicada — e
divergiria. A Action com template method é o único lugar da regra; FormRequest
validaria só o canal HTTP. Controllers e Tools ficam finos: traduzem, delegam.

**Por que 202 + Job em vez de processar no request?**
Processos que dependem de terceiros (gateway, prefeitura, ERP) têm latência e
falha fora do seu controle. Segurar o request é acoplar a UX do cliente ao pior
caso do terceiro; processar depois com retry disciplinado é a única forma
honesta de prometer resiliência — e o estado `processing_*` + GET/webhook é um
contrato de API mais sincero.

**Por que sqlite `:memory:` nos testes se produção é Postgres?**
Velocidade (suíte em ~4s) mantém o hábito de rodar a suíte a cada bloco — o
hábito vale mais que a fidelidade de dialeto. Regras que dependem de
comportamento específico do Postgres devem ganhar testes dedicados marcados
para rodar contra Postgres no CI. Trade-off consciente.

**Por que `predis` e não a extensão `phpredis`?**
Zero fricção de instalação (PHP puro) em qualquer ambiente — dev Windows, CI,
produção. A API é idêntica pelo facade do Laravel; se um benchmark real mostrar
gargalo, a troca é uma linha de `.env` (`REDIS_CLIENT=phpredis`).

**Por que o front-end fica FORA do template?**
Porque a fronteira mais barata de todas é a da API: qualquer SPA (React,
Angular, Vue) consome os mesmos endpoints que as integrações e as tools de IA.
Um front acoplado (Inertia) é legítimo — está nos opcionais — mas é uma decisão
do produto, não da fundação.

**Por que a IA precisa de "skills" se o CLAUDE.md já existe?**
O CLAUDE.md é conhecimento passivo (regras). As skills são procedimentos ativos
com gatilho: a diferença entre "saber as regras do xadrez" e "ter aberturas
estudadas". `/nova-feature` garante que NENHUM passo do ciclo (inclusive o
report e o sync-state) seja esquecido; `/sync-docs` torna a manutenção de
contexto uma operação de um comando.

---

## Origem

Este template é a generalização da arquitetura do **NFSe SaaS** — um sistema
multi-tenant de emissão de notas fiscais construído por um desenvolvedor + 
Claude (Opus), sob disciplina de documentação-primeiro, em que a razão
documentação÷código ficou em ~0,9 e cada fronteira aqui descrita foi paga e
provada em produção de verdade. As escolhas não são teóricas: são as
sobreviventes.

**Licença:** MIT (o esqueleto Laravel) — ajuste a licença do SEU sistema ao
usá-lo.
