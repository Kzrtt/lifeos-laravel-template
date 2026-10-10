---
name: nova-feature
description: Executa o ciclo completo de uma feature — carregar contexto, branch, implementação por blocos com testes, suíte verde (Pest+Pint+ArchTest), relatório de entrega em docs/reports e registro no sync-state.json. Use quando o usuário pedir para implementar uma feature, tarefa ou mudança de comportamento no sistema.
---

# Ciclo de feature (o "batimento")

Toda feature segue o MESMO ritual, sem atalhos. O ritual é o que mantém o
repositório num estado em que qualquer sessão futura de IA consegue continuar o
trabalho do zero.

## Fase 0 — Contexto e contrato

1. Execute a sequência da skill `/dominio` (CLAUDE.md → sync-state → SISTEMA.md
   → docs de domínio da área tocada). Se houver reports `pending` que toquem a
   mesma área, leia-os — você pode estar prestes a editar código que mudou.
2. Reformule a feature em 2–4 frases: o que muda para o usuário/cliente, quais
   regras de negócio entram/mudam, quais módulos são tocados. **Valide com o
   usuário antes de codar** se houver QUALQUER ambiguidade de negócio (regra de
   ouro nº 1). Decisão de produto é do dono do produto.
3. Confirme o ponto de partida limpo: `git status` (working tree limpa) e
   `composer test` verde. Se não, pare e reporte.

## Fase 1 — Branch e plano

1. `git checkout -b feat/<slug-curto>` (a partir da main atualizada).
2. Divida a feature em blocos implementáveis (migration → model/observer →
   contrato/bind se cruzar fronteira → Action/Service → Job/eventos → rotas/
   Resource → testes de cada peça). Blocos pequenos: a suíte deve poder ficar
   verde ao fim de cada um.

## Fase 2 — Implementação por blocos

Para cada bloco, nesta disciplina:

- **Consulte o molde antes de criar**: o código de referência é o módulo de
  exemplo (`orders`) e o FAQ `docs/arquitetura/IMPLEMENTACAO.md`. Convenções de
  banco/nomenclatura: `docs/arquitetura/BANCO.md` e `CODE_STYLE.md`. Não
  improvise padrão novo sem necessidade — consistência vale mais que
  criatividade.
- **Toda regra de negócio nasce com teste** (o teste no mesmo bloco, não "no
  final"). Use os testes de exemplo como gabarito: isolamento de tenant,
  auditoria, 202+fila, idempotência, transporte×negócio, vazamento de colunas.
- **Cheque as regras de ouro nos pontos quentes**: model novo → `Entityable` +
  observer de auditoria + prefixo/UUID/SoftDeletes; job novo → entity_id no
  payload + `runAs` primeiro + guard de idempotência + NUNCA capturar transporte;
  capacidade cruzando módulo → contrato no core + bind no provider do dono;
  campo sensível → `$hidden` do observer E fora do Resource (teste ambos).
- Ao fim do bloco: `composer test` + `composer lint` — verde antes do próximo.

## Fase 3 — Fechamento de qualidade

1. Suíte completa verde: `composer test` (Unit+Feature+Modules+Arch).
2. Estilo: `composer lint:check`.
3. Módulo novo criado no caminho? Confirme que entrou em `DOMAIN_MODULES`
   (ArchTest) — o `/novo-modulo` faz isso, mas confira.
4. Releia o diff completo (`git diff`) procurando: regra de negócio em
   controller, query crua, import cruzado, segredo em claro, tenant filtrado à
   mão.

## Fase 4 — Relatório de entrega e sync-state (regra de ouro nº 10)

1. Escreva `docs/reports/v<release>/feat_<slug>.md` seguindo o contrato de
   `docs/reports/README.md`. Capriche em DUAS seções: **"Ressalvas honestas"**
   (o que NÃO está 100% — nunca esconda) e **"Impacto na documentação"** (o
   diff de contexto: quais docs evergreen precisam absorver o quê — é o que
   torna o `/sync-docs` barato).
2. Adicione a entrada no `docs/reports/sync-state.json`: `id`, `file`, `title`,
   `delivered_at` (hoje), `status: "pending"`, `synced_at: null`, `touches`
   (alvos prováveis). Se for a primeira feature de uma release nova, atualize
   `current_release`.
3. Atualize o "Mapa de código" do(s) doc(s) de domínio tocado(s) — regra nova
   implementada aponta para classe e teste. (A atualização PROFUNDA dos docs é
   do `/sync-docs`; o mapa de código é barato e fresco agora.)

## Fase 5 — Commit e merge

1. Commits em português, imperativos, prefixados (`feat:`, `test:`, `docs:`) —
   um commit por bloco coeso é o ideal; nunca commite com suíte vermelha.
2. Merge na main por `--ff-only` (história linear) e push, SE o usuário
   autorizar — pergunte.
3. Reporte ao usuário: o que foi entregue, números da suíte
   (N testes / M asserts), ressalvas, e lembre: há report `pending` — rodar
   `/sync-docs` agora ou acumular para a próxima leva.

## Proibições absolutas

- Declarar entrega completa com teste falhando ou ressalva omitida.
- Resolver ambiguidade de negócio por conta própria.
- Furar fronteira "só desta vez" (o ArchTest existe para isso).
- Fechar a feature sem report + entrada no sync-state.
