# Fronteiras — testes de arquitetura e enforcement

Cada módulo tem sua pasta `tests/` e os testes rodam juntos: o `phpunit.xml` traz
as suítes `Unit`, `Feature`, `Modules` (testes dentro dos módulos) e `Arch`. Setup
do projeto: Pest, sqlite `:memory:`, fila `sync` — ver [`CODE_STYLE.md`](CODE_STYLE.md).

## O teste de arquitetura é o que torna a fronteira real

Além dos testes de comportamento, o template traz um **teste de arquitetura** que
proíbe imports cruzados não autorizados. Sem ele, toda a disciplina modular vira
só boa intenção — uma "convenção que prometemos respeitar" em vez de uma "regra
que o CI quebra".

Isso é especialmente decisivo no **desenvolvimento orientado a IA**: o agente não
precisa "lembrar" da regra em cada prompt — ele a descobre quando o teste quebra,
e corrige sozinho. A regra escrita em documento orienta; a regra executável
**garante**.

O Pest tem `arch()` presets para isso. O teste real vive em
`tests/Arch/ArchTest.php` e é **parametrizado sobre os módulos de domínio** —
módulo novo entra na constante `DOMAIN_MODULES` e ganha as regras de graça (a
skill `/novo-modulo` faz isso automaticamente). A forma real:

```php
// tests/Arch/ArchTest.php (forma real, resumida)
const DOMAIN_MODULES = [
    'Modules\Tenancy',
    'Modules\Orders',
    'Modules\Payments',
    'Modules\Notifications',
];

arch('todo o código dos módulos declara strict types')
    ->expect('Modules')
    ->toUseStrictTypes();

arch('o core não depende de nenhum módulo de domínio')
    ->expect('Modules\Core')
    ->not->toUse(DOMAIN_MODULES);

foreach (DOMAIN_MODULES as $module) {
    $others = array_values(array_filter(DOMAIN_MODULES, fn ($m) => $m !== $module));

    arch(class_basename($module).' não importa o interno de outro módulo de domínio')
        ->expect($module)
        ->not->toUse($others);   // travessia legítima = só core/Contracts
}
```

É isso que transforma a fronteira de promessa em regra executável. As três
invariantes do template, em forma de teste:

1. **Todo arquivo dos módulos declara `strict_types`** — tipagem estrita não é
   opcional.
2. **Nenhum módulo de domínio importa o interno de outro** — só o
   `core` (contratos, eventos, DTOs) é travessia legítima; relações Eloquent
   cross-módulo passam por `config/models.php`.
3. **O `core` não depende de nenhum módulo de domínio** — a dependência é
   estritamente unidirecional (ver [`CORE.md`](CORE.md)).

> A API de `arch()` evolui entre versões do Pest — confira a doc oficial ao
> estendê-lo (presets úteis: `arch()->preset()->php()`, `->security()`).

## O que mais é fronteira executável neste template

O ArchTest é a regra mais explícita, mas a suíte inteira funciona como cerca:

| Invariante | Quem garante |
| --- | --- |
| Imports cruzados proibidos | `tests/Arch/ArchTest.php` |
| `strict_types` em todo arquivo | ArchTest + Pint (`declare_strict_types`) |
| Isolamento entre tenants | `tests/Feature/MultiTenant/EntityScopeTest.php` |
| Auditoria em toda mutação + scrub de sensíveis | `tests/Feature/Audit/AuditObserverTest.php` |
| 202 + fila nomeada + idempotência | `tests/Feature/Orders/PlaceOrderTest.php` |
| Transporte lança / negócio retorna / reentrega idempotente | `tests/Feature/Orders/ProcessOrderPaymentJobTest.php` |
| Colunas internas nunca vazam na API | `PlaceOrderTest` ("nunca expõe colunas internas") |
| Model não cruza fronteira (DTO) | `tests/Feature/Orders/OrderReadModelTest.php` |
| Estilo | `vendor/bin/pint --test` |

Ao substituir os módulos de exemplo pelos seus, **mantenha o equivalente de cada
linha desta tabela** — os testes de exemplo são o gabarito de como escrever os
seus.

## Quando adicionar regras novas

Cedo. Quanto antes a regra existe, menos dívida de fronteira acumula. A
disciplina de fronteira sob pressão de prazo é a parte cara da arquitetura
modular; o teste de arquitetura é o que segura essa disciplina quando ninguém
está olhando o PR com cuidado — e quando quem escreve é uma IA operando em
sessões independentes, sem memória do combinado.
