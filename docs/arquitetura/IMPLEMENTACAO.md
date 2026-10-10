# Implementação — FAQ de código

> Foco em *como isso vira código*, não em teoria. Todos os snippets são o código
> REAL do template (módulos de exemplo) — abra os arquivos citados e siga o molde.

## Onde vão Models, Jobs e migrations

Cada módulo é um **mini-Laravel**. O que num app normal fica em `app/Models`,
`app/Jobs`, `app/Http`, agora fica em `app-modules/<modulo>/src/Models`,
`.../src/Jobs`, `.../src/Http`. As **migrations, factories e seeders** ficam em
`app-modules/<modulo>/database/` (não em `src/`). O `internachi/modular` faz o
Laravel descobrir migrations e factories automaticamente. A regra de ownership: o
dono do model é o dono da tabela — `Order` em `orders`, `Entity` em `tenancy`.

## Jobs e filas: NÃO existe "fila do core"

A fila **não pertence a nenhum módulo**. É infraestrutura do Laravel (Redis),
configurada uma vez na app host (`config/queue.php` + `.env` + `config/horizon.php`).
Vive *abaixo* de todos os módulos, no framework.

Quando você faz `ProcessOrderPaymentJob::dispatch($id, $entityId)`: (1) o Laravel
serializa a classe + dados numa fila Redis; (2) o Worker puxa, **desserializa pelo
namespace completo** (`Modules\Orders\Jobs\ProcessOrderPaymentJob`) e executa. O
Worker não precisa saber "de qual módulo veio" — só precisa que a classe seja
autoloadable, e como cada módulo é pacote Composer com PSR-4, todas já estão no
autoload global. **Você só registra o job dentro do módulo e ele executa.** Nada a
configurar no `core`.

```php
// orders/src/Jobs/ProcessOrderPaymentJob.php (esqueleto real)
final class ProcessOrderPaymentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public readonly string $orderId,
        public readonly string $entityId,   // o tenant VIAJA com o job
    ) {}

    public function backoff(): array
    {
        return [10, 30, 60, 120];           // backoff exponencial
    }

    public function handle(PaymentGateway $gateway): void
    {
        TenantContext::runAs($this->entityId, function () use ($gateway): void {
            // ... TODO o trabalho acontece DENTRO do contexto restaurado
        });
    }
}
```

Você *pode* querer **filas nomeadas** para separar prioridades (decisão de
operação, não de arquitetura). Isso é por dispatch, com `onQueue()`:

```php
ProcessOrderPaymentJob::dispatch($id, $entityId)->onQueue('orders');
SendWebhookJob::dispatch($id)->onQueue('webhooks');
```

O Horizon (host) distribui workers entre as filas. Atenção: nomes de fila são
**operação**, não módulos — coincidência de nome não é acoplamento.

### As três leis do job resiliente (regra de ouro nº 7)

1. **Restaure o tenant primeiro.** `TenantContext::runAs()` é a primeira
   instrução do `handle()`.
2. **Seja idempotente.** A fila PODE reentregar; um guard de status barato no
   início evita reprocessar (`if ($order->ord_status !== Processing) return;`).
3. **Nunca capture erro de transporte.** Timeout/5xx/indisponibilidade LANÇAM e
   sobem — a fila reprocessa com backoff. Só recusa DE NEGÓCIO vira estado final.
   Se você escrever `try/catch (Throwable)` num job, está desligando a
   resiliência do sistema.

## O padrão Strategy atrás de contrato (integrações)

Quando uma capacidade tem (ou terá) múltiplos provedores — gateways de pagamento,
provedores de nota fiscal, serviços de mensageria — o desenho é sempre o mesmo,
em três arquivos e três lugares:

```php
// 1. A PORTA (core/src/Contracts/PaymentGateway.php)
interface PaymentGateway
{
    public function charge(string $reference, float $amount): PaymentResult;
}

// 2. A(S) IMPLEMENTAÇÃO(ÕES) (payments/src/Gateways/*.php)
final class FakePaymentGateway implements PaymentGateway { /* ... */ }
// amanhã: StripeGateway, PagarmeGateway... cada um traduz a resposta crua
// do provedor para o DTO único (PaymentResult)

// 3. O BIND (payments/src/Providers/PaymentsServiceProvider.php)
$this->app->bind(PaymentGateway::class, FakePaymentGateway::class);
```

A interface devolve um **DTO do core** (`PaymentResult`), não a resposta crua —
cada provedor responde diferente, e a implementação traduz para o formato único.
Esse é o ponto do Strategy: o consumidor fala com todos do mesmo jeito.

### Quando a escolha é fixa vs. runtime (binding contextual)

- **Uma implementação só:** bind direto no provider (o caso do template).
- **Escolha por ambiente/config:**
  `$this->app->bind(PaymentGateway::class, fn () => match (config('services.payment.driver')) { ... })`.
- **Escolha por dado em runtime** (ex.: o provedor depende do registro sendo
  processado): crie um **resolver** — um contrato `GatewayResolver` no core com
  `forX($dado): PaymentGateway`, implementado no módulo dono com um `match`/config
  — e injete o resolver, não o gateway.

Regra de bolso: escolha fixa → bind no provider; escolha por dado em runtime →
resolver.

## Actions NÃO precisam de interface no core

As Actions são **internas ao módulo**. O Controller do `orders`, uma futura Tool
MCP do `orders` e a `PlaceOrderAction` vivem todos no mesmo módulo — chamada
direta, sem interface. A interface no `core` só existe quando **cruza fronteira
entre módulos**.

```
[entrada]                    [mesmo módulo orders]              [outro módulo]
Controller (orders) --> PlaceOrderAction (orders) --contrato--> PaymentGateway (payments)
Tool MCP   (orders) -->        ^ chamada direta, sem interface
```

- **Expor Action para API/MCP** = chamada direta dentro do módulo (a
  `AbstractAction` no `core` é só classe-base por herança, não contrato de
  fronteira).
- **Action falar com outro módulo** = aí sim, interface no `core`.

## A AbstractAction (template method, no core)

A `AbstractAction` é a classe-base compartilhada entre todos os canais. Define o
*template method* `execute()` que orquestra **sanitizar → validar → autorizar →
executar** — a mesma sequência sempre. É herança, não contrato de fronteira.

```php
abstract class AbstractAction
{
    final public function execute(array $input): mixed
    {
        $data = $this->sanitize($input);   // normalização de entrada
        $data = $this->validate($data);    // Validator (lança 422)
        $this->authorize($data);           // policy / tenant
        return $this->handle($data);       // o caso de uso
    }

    abstract protected function rules(): array;
    abstract protected function handle(array $data): mixed;
    // sanitize()/authorize()/messages() opcionais
}
```

`HttpInputAdapter` (de `Request`) e `McpInputAdapter` (dos argumentos da tool)
normalizam o payload para o **mesmo array canônico** antes da Action. A Action
não sabe de qual canal veio. Adicionar um canal novo (CLI, fila interna) é só
escrever mais um adapter de entrada — nenhuma regra muda.

## API: as rotas são reais e vivem no módulo

A Action não substitui a rota — substitui a **lógica** que incharia o Controller.
A cadeia continua: `rota → Controller fino → Action → (Services)/contratos`.

Cada módulo expõe suas rotas no próprio `routes/` (o `internachi/modular` carrega
todo arquivo `.php` de lá automaticamente):

```php
// orders/routes/orders-routes.php (real)
Route::middleware(['resolve.tenant', 'throttle:60,1'])
    ->prefix('v1')
    ->group(function (): void {
        Route::post('orders', [OrderController::class, 'store']);
        Route::get('orders/{id}', [OrderController::class, 'show']);
    });
```

O middleware de borda (`resolve.tenant` — placeholder; o seu `auth.token` real)
autentica e popula o `TenantContext` ANTES de qualquer código de módulo rodar.

### E MCP? (opcional)

Se o sistema expuser tools para agentes de IA, instale `laravel/mcp` e siga o
mesmo desenho: a Tool é fina e delega para a MESMA Action, com o
`McpInputAdapter` normalizando a entrada. API e MCP terminam na mesma Action —
só mudam os adapters:

```
POST /v1/orders   --> OrderController::store --+
                                               +--> PlaceOrderAction::execute()
tool place_order  --> PlaceOrderTool::handle --+     (regra escrita 1x)
```

Atenção ao tenant: o canal MCP não tem sessão — o adapter resolve o tenant a
partir do token e chama `TenantContext::set()` antes da Action (mesmo papel do
middleware HTTP).

## Como o Controller recebe a Action (method injection)

A rota só passa a **string com o nome do método** (`'store'`). A Action chega por
**resolução automática de dependências por type-hint** do container:

1. Laravel vê que a rota aponta para `OrderController::store`.
2. Lê os type-hints dos parâmetros via Reflection.
3. Pede ao container uma instância de cada classe tipada — `Request` e
   `PlaceOrderAction` (instanciando *as dependências dela* recursivamente,
   trocando interfaces pelas implementações que os módulos deram bind).
4. Chama `store()` com tudo preenchido.

```php
// (a) method injection — mais limpo para uma Action por método (padrão do template)
public function store(Request $request, PlaceOrderAction $action) { /* ... */ }

// (b) constructor injection — bom se várias rotas usam a mesma Action
public function __construct(private readonly PlaceOrderAction $action) {}

// (c) resolução manual — raramente necessário
$action = app(PlaceOrderAction::class);
```

Você nunca dá `new PlaceOrderAction(...)` na mão — pede a Action e o container
monta a árvore inteira. **É por isso que o binding funciona sem passar nada
manualmente.** Vale igual em Tools MCP e em `handle()` de Jobs.

## OrderResource: o tradutor de saída HTTP

`OrderResource` é um **API Resource** do Laravel — transforma um Model em JSON,
decidindo *quais* campos saem e com que nome/formato:

```php
// orders/src/Http/Resources/OrderResource.php (real)
final class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ord_id,
            'ref' => $this->ord_ref,
            'status' => $this->ord_status->value,
            'customer_name' => $this->ord_customer_name,
            'amount' => (float) $this->ord_amount,
            'failure_reason' => $this->when(
                $this->ord_status === OrderStatus::PaymentFailed,
                $this->ord_failure_reason,
            ),
            'paid_at' => $this->ord_paid_at?->toIso8601String(),
            'created_at' => $this->ord_created_at?->toIso8601String(),
        ];
    }
}
```

- `$this->ord_ref` funciona porque o `JsonResource` faz proxy para o objeto
  passado em `new OrderResource($order)`.
- `$this->when(cond, valor)` só inclui o campo se a condição for verdadeira.
- Retornar `new OrderResource($order)` do Controller serializa para JSON e
  envolve em `{ "data": { ... } }`.

Por que não retornar o Model cru? (1) Controle do que vaza — o Model tem colunas
internas (`ord_internal_notes`) que não devem ir para o cliente; o Resource só
expõe o que você listar (há um teste garantindo isso). (2) Formatação consistente
(datas ISO, casts). (3) Desacopla o contrato da API da estrutura da tabela.

### Resource vs DTO (não confunda)

|                    | OrderResource                         | OrderSummaryDTO                      |
| ------------------ | ------------------------------------- | ------------------------------------ |
| Para quê           | Saída HTTP (JSON) da API REST         | Travessia de fronteira entre módulos |
| Quem consome       | Cliente HTTP externo                  | Outro módulo / host (dashboards)     |
| Usa o Model cru?   | Sim — vive dentro do dono dos dados   | Não — o model nunca cruza a fronteira |
| Camada             | Apresentação HTTP                     | Comunicação inter-módulo             |

Resource = saída HTTP dentro do dono dos dados. DTO = travessia de fronteira
entre módulos. Servem a camadas diferentes. O DTO e o read model estão em
[`COMUNICACAO.md`](COMUNICACAO.md#read-model--dto-leitura-através-da-fronteira).

## Idempotência pela `ref` do cliente

Toda operação de escrita exposta a clientes/integradores aceita uma chave de
idempotência (`ref`) opcional. O padrão (real, em `PlaceOrderAction`):

1. Índice único `(entity_ent_id, ord_ref)` no banco — a garantia final.
2. A Action consulta a ref antes de criar: existe? devolve o registro existente
   (mesma resposta, sem segundo efeito).
3. O cliente pode repetir o POST com segurança (rede caiu? repete).

Refs nulas não colidem (múltiplos NULL passam pelo índice único) — a
idempotência é opt-in por requisição.
