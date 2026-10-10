---
name: prontuario
description: Prontuário inicial do sistema — transforma o template genérico num sistema real. Entrevista extensa com o dono do produto, gera os documentos de domínio (docs/dominio), define os módulos iniciais, renomeia projeto/namespace e remove os módulos de exemplo. Use quando iniciar um sistema novo a partir do template, quando o CLAUDE.md ainda tiver a Visão marcada como "PREENCHIDO PELO PRONTUÁRIO", ou quando o usuário pedir o prontuário/anamnese/setup inicial do projeto.
---

# Prontuário inicial do sistema

Você vai transformar este template genérico num sistema real. Este é o momento
de **maior alavancagem do projeto inteiro**: o que for capturado aqui vira o
contexto permanente de todas as sessões futuras de IA. Seja minucioso; não
economize perguntas; NUNCA invente resposta de negócio.

## Pré-checagens

1. Leia `CLAUDE.md`. Se a seção **Visão** NÃO tiver mais o aviso "PREENCHIDO
   PELO PRONTUÁRIO", o prontuário já rodou — pergunte ao usuário se quer
   REVISÁ-LO (atualizar docs de domínio) em vez de recriá-lo, e siga só os
   passos 2–3 nesse caso.
2. Confirme que a suíte está verde antes de mexer em qualquer coisa:
   `composer test`. Se não estiver, PARE e reporte.

## Passo 1 — A entrevista (a coleta de prompts extensos)

Conduza por blocos, na ordem abaixo. Faça as perguntas em grupos pequenos
(AskUserQuestion quando as opções forem enumeráveis; texto livre quando não).
Aprofunde cada resposta com "por quê?" e "o que acontece quando isso dá
errado?" — as regras de negócio moram nos casos de erro. Registre TUDO.

**Bloco A — Identidade**
- Nome do sistema (e o nome em PascalCase para o namespace — proponha a partir
  do nome; ex.: "Notas Fiscais SaaS" → `NfseSaas`).
- Uma frase: o que o sistema faz e para quem?
- O que já existe hoje (planilha, sistema legado, processo manual)? O que dói?

**Bloco B — Tenancy e atores**
- É multi-tenant (vários clientes isolados)? Quem é o tenant (empresa, pessoa,
  conta)? Se single-tenant: registrar — os models não usarão `Entityable` e o
  módulo `tenancy` será adaptado/removido.
- Quem usa o sistema (papéis)? Quem administra a plataforma?
- Como os clientes/integrações se autenticam (painel com sessão? API com
  token? ambos)?

**Bloco C — O domínio (o coração; gaste tempo aqui)**
- Quais são as "coisas" centrais do negócio (os agregados)? Para cada uma:
  ciclo de vida (estados e transições), quem cria, o que a torna válida/
  inválida, o que NUNCA pode acontecer com ela.
- Quais operações são demoradas ou dependem de terceiros? (candidatas a
  202+Job)
- Onde há dinheiro, validade legal ou segredo? (candidatas a módulo isolado de
  núcleo)
- Quais integrações externas existem/virão? Há mais de um provedor possível
  para alguma? (candidatas a Strategy atrás de contrato)
- O que precisa notificar quem, quando? (candidatas a evento + listener)
- Idempotência: o que o cliente pode reenviar sem duplicar efeito?

**Bloco D — Regras e números**
- Para cada área citada: as regras concretas, com números (limites, prazos,
  percentuais, retenções). Peça exemplos reais ("me dá um caso concreto de X").
- O que é decisão FIRME × o que é chute inicial a revisitar?

**Bloco E — Operação**
- Volume esperado (pedidos/dia, tenants, picos)?
- Retenção de dados (o que guardar por quanto tempo)?
- Front-end pretendido (React/Angular/Vue consumindo a API? painel admin?).
- Existe base de documentação externa (Notion, wiki) que deva ser canônica?

## Passo 2 — Gerar os documentos de domínio

Para cada área de negócio identificada, crie `docs/dominio/<AREA>.md` seguindo
EXATAMENTE o contrato de `docs/dominio/README.md` (problema → regras numeradas →
fluxo mermaid → decisões/porquês → ⚠️ pendências → mapa de código; o mapa de
código nasce com "a implementar" e é preenchido pelas features). Ambiguidade não
resolvida na entrevista vai para "⚠️ Pendências do dono do produto" — nunca a
resolva sozinho.

## Passo 3 — Desenhar os módulos iniciais

Proponha (e valide com o usuário) o mapa de módulos usando os critérios de
`docs/arquitetura/ARQUITETURA.md` §"Como desenhar OS SEUS módulos": núcleo de
alto risco separado da periferia; integrações atrás de contrato; sem
over-engineering (3–5 módulos de domínio no início). Registre o mapa aprovado em
`docs/arquitetura/ARQUITETURA.md` (substituindo o diagrama de exemplo) e a
partição em `CLAUDE.md`.

## Passo 4 — Renomear projeto e namespace

Com o nome PascalCase aprovado (ex.: `MeuSistema`), execute a renomeação global:

1. `config/app-modules.php`: `modules_namespace` → `MeuSistema` (mantenha
   `modules_vendor` = kebab-case, ex.: `meu-sistema` — ou mantenha `modules` se
   o usuário preferir).
2. Em TODOS os `app-modules/*/composer.json`: namespaces PSR-4
   (`Modules\\X\\` → `MeuSistema\\X\\`) e, se mudou o vendor, os names
   (`modules/x` → `meu-sistema/x`) — nesse caso atualize também os requires no
   `composer.json` raiz.
3. Busca e substituição em todo o código: `Modules\` → `MeuSistema\` (arquivos
   `.php` em `app-modules/`, `app/`, `tests/`, `config/models.php`).
4. `tests/Arch/ArchTest.php`: constante `DOMAIN_MODULES` e os `expect()`.
5. `composer.json` raiz: `name` e `description` do projeto; `.env.example`:
   `APP_NAME`.
6. `composer dump-autoload` e depois `composer test` — TUDO verde antes de
   prosseguir.

## Passo 5 — Remover os módulos de exemplo

Pergunte antes: alguns usuários preferem manter `orders` como referência até o
primeiro módulo real existir. Para remover:

1. Apague `app-modules/orders`, `app-modules/payments`,
   `app-modules/notifications`.
2. Remova os requires `*/orders|payments|notifications` do `composer.json` raiz.
3. No `core`, apague os artefatos `[EXEMPLO — REMOVÍVEL]`:
   `Contracts/{PaymentGateway,OrderReadModel}.php`,
   `DTOs/{PaymentResult,OrderSummaryDTO}.php`, `Events/OrderPaid.php`,
   `Exceptions/PaymentGatewayUnavailableException.php`.
4. Apague os testes de exemplo: `tests/Feature/Orders/`,
   `tests/Feature/MultiTenant/EntityScopeTest.php` e
   `tests/Feature/Audit/AuditObserverTest.php` **SÓ SE** já existir um model
   real para reescrevê-los em cima (senão, mantenha-os até o primeiro módulo
   real e então adapte — o isolamento de tenant e a auditoria DEVEM permanecer
   testados; regra de ouro nº 9).
5. `tests/Arch/ArchTest.php`: atualize `DOMAIN_MODULES`.
6. `composer update modules/ 2>/dev/null || composer update --lock` e
   `composer test` — verde.

## Passo 6 — Reescrever a identidade do repositório

1. `CLAUDE.md`: seção **Visão** (removendo o aviso de placeholder), mapa de
   módulos, seção **Estado atual**.
2. `README.md`: título e primeira seção (o restante do tutorial de arquitetura
   permanece válido — não o apague).
3. `docs/reports/sync-state.json`: `project`, e adicione um report
   `v0.0.1/feat_prontuario` (crie o arquivo de report desta transformação —
   contrato em `docs/reports/README.md`) com status `pending`.
4. Rode `/sync-docs` ao final para o SISTEMA.md nascer refletindo o sistema real.

## Passo 7 — Encerramento

Apresente ao usuário: resumo do que foi capturado, os docs criados, o mapa de
módulos, pendências abertas e os próximos passos sugeridos (primeiro
`/novo-modulo` + primeira `/nova-feature`). Suíte verde é pré-condição de
encerramento — se algo quebrou, conserte antes de declarar concluído.
