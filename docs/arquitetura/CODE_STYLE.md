# Estilo de código

Convenções obrigatórias. O que dá para automatizar é garantido pelo **Pint** e
pelo **ArchTest**; o resto é revisão. Em dúvida sobre domínio/banco,
[`ARQUITETURA.md`](ARQUITETURA.md) e [`BANCO.md`](BANCO.md).

## PHP

- **`declare(strict_types=1)` em todo arquivo PHP** (garantido pelo Pint e
  verificado pelo ArchTest).
- Tipos explícitos em parâmetros, retornos e propriedades. Use `enum` para
  conjuntos fechados (ex.: `OrderStatus`).
- Pint, preset `laravel`, com as regras de `pint.json`:
  - `declare_strict_types`
  - `ordered_imports` (alfabético)
  - `no_unused_imports`
  - `fully_qualified_strict_types`
- Classes finais por padrão (`final class ...`) quando não há intenção de
  herança; `AbstractAction` e `AuditObserver` são as exceções planejadas.
- **Código em inglês** (classes, métodos, variáveis, colunas); **comentários,
  docs e commits em português**. O código fala a língua do ecossistema; a
  documentação fala a língua do time.

## Namespaces de módulo

Cada módulo é um pacote Composer com namespace próprio sob `Modules\` (definido
em `config/app-modules.php`; o `/prontuario` renomeia para o nome do projeto). O
nome do módulo vira o segundo segmento:

| Módulo          | Namespace raiz            | Exemplo de classe                              |
| --------------- | ------------------------- | ---------------------------------------------- |
| `core`          | `Modules\Core\`           | `Modules\Core\Contracts\PaymentGateway`        |
| `tenancy`       | `Modules\Tenancy\`        | `Modules\Tenancy\Models\Entity`                |
| `orders`        | `Modules\Orders\`         | `Modules\Orders\Actions\PlaceOrderAction`      |
| `payments`      | `Modules\Payments\`       | `Modules\Payments\Gateways\FakePaymentGateway` |
| `notifications` | `Modules\Notifications\`  | `Modules\Notifications\Listeners\...`          |

> **Regra de import entre módulos:** um módulo de domínio só pode importar de
> `Modules\Core\` (contratos, eventos, DTOs, value objects). Importar o interno
> de outro módulo de domínio é proibido e o teste de arquitetura quebra o CI
> ([`FRONTEIRAS.md`](FRONTEIRAS.md)).

## Nomenclatura

| Elemento            | Convenção                 | Exemplo                          |
| ------------------- | ------------------------- | -------------------------------- |
| Classe / Enum       | PascalCase                | `OrderStatus`, `PaymentResult`   |
| Método / variável   | camelCase                 | `charge()`, `$validatedData`     |
| Action              | `VerbNounAction`          | `PlaceOrderAction`               |
| Service             | `NounService`             | `PricingService`                 |
| Job                 | `VerbNounJob`             | `ProcessOrderPaymentJob`         |
| Gateway/Integração  | `ProviderGateway`         | `FakePaymentGateway`             |
| Observer            | `ModelObserver`           | `OrderObserver`                  |
| Contrato            | substantivo de capacidade | `PaymentGateway`, `OrderReadModel` |
| DTO                 | `NounDTO` / `NounResult`  | `OrderSummaryDTO`, `PaymentResult` |
| Evento              | fato no passado           | `OrderPaid`                      |
| Tabela / coluna     | snake_case + prefixo      | `orders`, `ord_status`           |

## Models (Eloquent)

- **Eloquent é a única camada de dados** — sem query crua fora de
  Models/Services do módulo dono.
- PK UUID: `$incrementing = false`, `$keyType = 'string'`, `$primaryKey` e
  `$table` explícitos, `use HasUuids`; SoftDeletes com constantes de timestamp
  prefixadas. Molde completo em [`BANCO.md`](BANCO.md).
- Todo model com escopo de tenant usa `use Entityable` (importado de
  `Modules\Core`) — nunca filtre tenant à mão.
- Todo model transacional tem observer de auditoria (`#[ObservedBy]` + classe
  que estende `AuditObserver`), com colunas sensíveis em `$hidden`.
- O model vive no módulo dono da tabela e **nunca cruza a fronteira**: para
  expor dado a outro módulo, devolva um DTO via read model
  (ver [`COMUNICACAO.md`](COMUNICACAO.md)).

## Onde mora cada coisa (modular)

Cada módulo é um mini-Laravel em `app-modules/<modulo>/`:

```
app-modules/<modulo>/
├── src/
│   ├── Actions/      Services/     Models/      Jobs/
│   ├── Http/         Mcp/          Observers/   ReadModels/
│   ├── Enums/        Providers/
│   └── (Gateways/, Builders/... conforme o domínio)
├── routes/           # rotas próprias do módulo (qualquer *.php é carregado)
├── database/         # migrations, factories, seeders do módulo
└── tests/            # Feature/ e Unit/ do módulo (suíte "Modules")
```

Contratos, eventos, DTOs, value objects e a infra multi-tenant/auditoria ficam no
módulo `core`. Detalhe das árvores em [`MODULOS.md`](MODULOS.md).

## Fluxo de uma operação

Controller magro → **Action** (sanitize→validate→authorize→handle) → Services →
(contratos para outros módulos) → Resource. A regra mora na **Action** (única
para todos os canais); Controller e Tool MCP são adapters finos. Action fala com
outro módulo só por contrato declarado no `core`. Detalhe em
[`IMPLEMENTACAO.md`](IMPLEMENTACAO.md).

## Segurança

- Nenhuma credencial real no repositório; `.env.example` só com placeholders.
- Segredos cifrados (AES-256 no módulo dono, isolado); senhas e tokens só como
  hash — nunca em claro.
- Colunas sensíveis fora do diff de auditoria (`$hidden` do observer) E fora do
  Resource da API — teste ambos.
- Jobs **não capturam** timeout/5xx: deixam relançar para o retry da fila.
- O middleware `resolve.tenant` do template é PLACEHOLDER — substitua por
  autenticação real (token com hash + rate limit) antes de produção.

## Frontend

- Qualquer SPA (React, Angular, Vue) **consumindo a API** — o backend não
  renderiza telas. UI fina: validação e regra de negócio são responsabilidade do
  backend (Actions).
- Se optar por painéis server-side (Filament/Livewire/Inertia — ver README
  §Dependências), eles consomem **read models / DTOs**, nunca Eloquent cru de
  outro módulo.

## Testes

- **Pest.** Suítes `Unit`, `Feature`, `Modules` (dentro de cada módulo) e
  `Arch`. DB de teste = `sqlite :memory:`; fila `sync`.
- Toda regra de negócio nasce com teste. Cobertura mínima alvo: 70%
  (`composer test:coverage`).
- **Teste de arquitetura obrigatório** (`tests/Arch/`): proíbe imports cruzados
  não autorizados e a dependência reversa do `core`. Módulo novo entra em
  `DOMAIN_MODULES`. Ver [`FRONTEIRAS.md`](FRONTEIRAS.md).
- Use os testes do exemplo como gabarito: isolamento de tenant, auditoria,
  202+fila, idempotência, transporte×negócio, vazamento de colunas.

## Comandos

```bash
composer lint            # pint (corrige)
composer lint:check      # pint --test (CI / antes de commitar)
composer test            # pest (todas as suítes)
composer test:coverage   # pest --coverage --min=70
php artisan modules:list # lista os módulos registrados
php artisan make:model Order --module=orders   # gera dentro do módulo
```

## Commits

- Mensagens em português, no imperativo, com prefixo de tipo (`feat:`, `fix:`,
  `chore:`, `docs:`, `refactor:`, `test:`).
- Rode `composer lint:check` e `composer test` antes de commitar.
- Uma feature = uma branch `feat/...` = um relatório em `docs/reports/` (regra
  de ouro nº 10).
