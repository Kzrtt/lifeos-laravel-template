# Módulos — o pacote `internachi/modular`

Este documento cobre a **mecânica**: o que é o pacote, por que ele, como instalar
e qual é a árvore de arquivos de cada módulo. O *desenho* (quais módulos e por
quê) está em [`ARQUITETURA.md`](ARQUITETURA.md).

## O que é

`internachi/modular` é, nas palavras do autor (Chris Morrell / InterNACHI), *"tanto
um conjunto de convenções quanto um pacote"*. A ideia central: você cria módulos
num diretório `app-modules/`, e cada módulo usa o **sistema de pacotes nativo do
Laravel** — ou seja, cada módulo é, literalmente, um pacote Composer. Ele se apoia
em duas coisas que o Laravel já tem:

- **Composer path repositories:** cada módulo é registrado no `composer.json` da
  app como um repositório local (`./app-modules/*`) e exigido como dependência
  (`modules/nome:*`). É isso que dá o autoload PSR-4 isolado por módulo.
- **Laravel package discovery:** a inicialização de cada módulo (service
  providers, etc.) usa o mesmo mecanismo de auto-descoberta que o Laravel usa
  para pacotes de terceiros.

O pacote adiciona só "tooling mínimo para preencher as lacunas". A linha 3.x usa
um sistema de plugins com atributos PHP (`#[OnBoot]`, `#[AfterResolving(...)]`)
para customizar o ciclo de vida do boot dos módulos, caso precise.

## Por que ele e não o `nwidart/laravel-modules`

Esses são os dois grandes pacotes de modularização do Laravel. O próprio README
do InterNACHI explica honestamente quando usar cada um:

| Critério                            | nwidart/laravel-modules                       | internachi/modular                        |
| ----------------------------------- | --------------------------------------------- | ----------------------------------------- |
| Filosofia                           | Estrutura de diretórios própria               | Convenções nativas do Laravel             |
| Peso                                | Mais pesado, mais features                    | Mais leve, mínimo                         |
| Caso ideal                          | CMS com módulos de terceiros liga/desliga em runtime | Modularização para organização interna |
| Ligar/desligar módulo dinamicamente | Sim (ponto forte)                             | Não é o foco                              |
| Extrair módulo p/ pacote depois     | Mais trabalhoso                               | Trivial (já é um pacote Composer)         |

Veredito: *se você constrói um CMS que precisa de módulos de terceiros ativáveis
em runtime, use o nwidart; se quer modularização para organização e ficar perto
das convenções do Laravel, use o InterNACHI.* Sistemas construídos com este
template são claramente o segundo caso — organização limpa e a opção de um dia
extrair um módulo para pacote reutilizável (que o InterNACHI dá de graça).

## Instalação (JÁ FEITA no template)

O template nasce com tudo configurado. Para referência, o que foi feito:

```bash
# 1. Instala o pacote (auto-discovery configura tudo)
composer require internachi/modular

# 2. Publica o config para customizar o namespace organizacional
php artisan vendor:publish --tag=modular-config
#    -> config/app-modules.php (namespace `Modules\` no template;
#       o /prontuario renomeia para o nome do SEU projeto)

# 3. Cria um módulo
php artisan make:module meu-modulo

# 4. Atualiza o Composer (make:module adiciona 2 entradas no composer.json)
composer update modules/meu-modulo

# 5. (Feito no template) Suíte "Modules" no phpunit.xml
php artisan modules:sync
```

> **Sobre o namespace:** trocar o default `Modules\` pelo nome do projeto em
> PascalCase (`MeuSistema\`) deixa os imports legíveis
> (`MeuSistema\Orders\Models\Order`) e facilita extrair o módulo para pacote
> separado no futuro. A skill `/prontuario` faz essa renomeação global com
> segurança (namespace + vendor + composer.json de cada módulo + ArchTest).

## O que funciona de graça (auto-discovery por módulo)

Como os módulos seguem convenções nativas, a auto-descoberta do Laravel funciona
dentro de cada `app-modules/*` sem configuração extra:

- Commands auto-registrados no Artisan (ex.: `audit:query` vem do `core`)
- Migrations rodadas pelo Migrator (de todos os módulos, ordenadas por nome)
- Factories auto-carregadas para `factory()` (com `newFactory()` explícito no model)
- Policies auto-descobertas para os Models
- Rotas: todo arquivo em `app-modules/<modulo>/routes/` é carregado
- Componentes Blade com namespace do módulo: `<x-orders::status-badge />`
- Event listeners auto-descobertos (o template prefere registro explícito no provider)
- Traduções com namespace: `__('orders::messages.paid')`

E os comandos `make:` do Laravel ganham a flag `--module=`:

```bash
php artisan make:model Order --module=orders
php artisan make:job ProcessOrderPaymentJob --module=orders
php artisan make:observer OrderObserver --module=orders
php artisan make:test PlaceOrderTest --module=orders
php artisan db:seed --module=orders
```

Comandos próprios do pacote: `make:module`, `modules:list`, `modules:cache`
(cacheia para auto-discovery mais rápida — use em produção), `modules:clear`,
`modules:sync`.

> **Pegadinha:** se você já tiver instalado o `nwidart/laravel-modules` antes no
> mesmo projeto, o `ModuleRegistry` do InterNACHI pode falhar lendo
> `bootstrap/cache/modules.php` (formato diferente). Fix: `php artisan
> optimize:clear` antes de instalar.

## Árvores de arquivos

### Visão geral do projeto

```
projeto/
├── app/                       # app host ENXUTA
│   ├── Http/Middleware/       # middlewares de BORDA (resolução de tenant/auth)
│   └── Providers/             # providers globais
├── app-modules/               # <- todo o domínio vive aqui
│   ├── core/                  # shared kernel (permanente)
│   ├── tenancy/               # o tenant (permanente)
│   ├── orders/                # [EXEMPLO — removível]
│   ├── payments/              # [EXEMPLO — removível]
│   └── notifications/         # [EXEMPLO — removível]
├── bootstrap/app.php          # aliases de middleware, exceções
├── config/
│   ├── app-modules.php        # config do internachi/modular (namespace)
│   └── models.php             # bindings de model cross-módulo
├── database/                  # migrations globais (só as que não são de módulo)
├── docs/                      # índice em docs/README.md
├── routes/web.php             # rotas raiz; módulos trazem as suas
├── tests/                     # suítes root: Unit, Feature, Arch (+ Modules via módulos)
├── composer.json              # path repos + require dos módulos
└── phpunit.xml                # suítes Unit/Feature/Modules/Arch
```

### Módulo `core` (shared kernel — permanente)

```
app-modules/core/
├── composer.json
├── src/
│   ├── Actions/
│   │   ├── AbstractAction.php          # template method execute()
│   │   └── Input/
│   │       ├── HttpInputAdapter.php    # Request -> array canônico
│   │       └── McpInputAdapter.php     # args de tool MCP -> array canônico
│   ├── Contracts/                      # AS PORTAS entre módulos
│   │   ├── PaymentGateway.php          # [EXEMPLO]
│   │   └── OrderReadModel.php          # [EXEMPLO]
│   ├── DTOs/
│   │   ├── PaymentResult.php           # [EXEMPLO]
│   │   └── OrderSummaryDTO.php         # [EXEMPLO]
│   ├── Events/
│   │   └── OrderPaid.php               # [EXEMPLO]
│   ├── Exceptions/
│   │   └── PaymentGatewayUnavailableException.php  # [EXEMPLO]
│   ├── Models/
│   │   ├── Concerns/Entityable.php     # trait multi-tenant
│   │   └── AuditLog.php                # trilha append-only
│   ├── Scopes/EntityScope.php
│   ├── Observers/AuditObserver.php     # observer base que grava o diff
│   ├── Tenancy/TenantContext.php       # ponto único do tenant atual
│   ├── Console/Commands/AuditQueryCommand.php   # audit:query via CLI
│   └── Providers/CoreServiceProvider.php
├── database/migrations/                # audit_logs
└── tests/
```

### Módulo `orders` (o exemplo executável — o molde de um módulo de domínio)

```
app-modules/orders/
├── composer.json
├── src/
│   ├── Actions/
│   │   ├── PlaceOrderAction.php        # escrita: idempotência + 202 + Job
│   │   └── GetOrderAction.php          # leitura: EntityScope faz o isolamento
│   ├── Models/Order.php                # prefixo ord_, UUID, SoftDeletes, Entityable
│   ├── Enums/OrderStatus.php           # máquina de estados
│   ├── Jobs/ProcessOrderPaymentJob.php # runAs + contrato + evento + retry
│   ├── Http/
│   │   ├── Controllers/OrderController.php   # adapter REST fino
│   │   └── Resources/OrderResource.php       # tradutor de saída JSON
│   ├── ReadModels/EloquentOrderReadModel.php # implementa a porta de leitura
│   ├── Observers/OrderObserver.php     # auditoria (oculta ord_internal_notes)
│   └── Providers/OrdersServiceProvider.php   # bind do read model
├── routes/orders-routes.php            # rotas /v1 do módulo
├── database/
│   ├── migrations/
│   └── factories/OrderFactory.php
└── tests/
```

> Num sistema real, adicione conforme o domínio pedir: `Services/` (lógica que a
> Action orquestra), `Gateways/` (integrações), `Builders/`, `Mail/`,
> `Mcp/Tools/`, `Filament/Resources/`... Cada módulo é um **mini-Laravel**: o que
> num app normal fica em `app/X`, aqui fica em `app-modules/<modulo>/src/X`. As
> **migrations, factories e seeders** ficam em `app-modules/<modulo>/database/`
> (não em `src/`). A regra de ownership: o dono do model é o dono da tabela.
> Ver [`BANCO.md`](BANCO.md).

### Módulos `payments` e `notifications` (exemplos mínimos)

```
app-modules/payments/          # FakePaymentGateway (implements PaymentGateway),
                               #   provider com o bind — o lado "fornecedor" da fronteira
app-modules/notifications/     # SendOrderPaidNotification (listener queued de OrderPaid),
                               #   provider com Event::listen — o lado "reativo"
```

## Referências

- `internachi/modular` — README oficial: github.com/InterNACHI/modular
- Packagist: packagist.org/packages/internachi/modular
- Princípios de modular monolith: Kamil Grzybek, Milan Jovanović (2020–2026)
