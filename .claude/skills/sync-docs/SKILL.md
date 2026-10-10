---
name: sync-docs
description: Sincroniza a documentação evergreen com as entregas — lê o sync-state.json, encontra os reports pendentes, propaga cada um para SISTEMA.md, docs/dominio, CLAUDE.md e demais alvos, e marca como synced. Use quando o usuário pedir para sincronizar/atualizar a documentação, quando houver reports pending acumulados, ou ao fechar uma release.
---

# Sincronização de contexto (reports → documentação evergreen)

Você vai pagar a dívida de documentação declarada no `sync-state.json`. O
objetivo: ao final, uma sessão nova de IA que leia apenas `CLAUDE.md` +
`SISTEMA.md` + `docs/dominio/` tem o retrato FIEL do sistema — sem precisar
arqueologia nos reports.

## Passo 1 — Levantar o estado

1. Leia `docs/reports/sync-state.json`.
2. Filtre `reports[]` com `status: "pending"`. Se não houver nenhum, reporte
   "contexto em dia" (e confira `last_sync` × data do último report como sanidade)
   e encerre.
3. Ordene os pendentes do MAIS ANTIGO para o mais novo (`delivered_at`; empate:
   ordem no array). A doc evolui na ordem da história.
4. Anuncie ao usuário o plano: N reports pendentes, quais, e os alvos prováveis
   (`touches`).

## Passo 2 — Processar cada report (um por vez, na ordem)

Para cada report pendente:

1. **Leia o report inteiro** — priorize as seções "O que mudou, em uma frase",
   "Ressalvas honestas" e **"Impacto na documentação"** (o diff de contexto
   declarado por quem entregou; é seu roteiro).
2. **Verifique contra o código** qualquer afirmação que vá virar doc evergreen
   e pareça datada (classes citadas existem? teste citado existe?). O report é
   fotografia: pode ter sido superado por um report pendente MAIS NOVO — nesse
   caso o mais novo vence, e o SISTEMA.md registra só o estado final (a
   história fica nos reports).
3. **Atualize os alvos** (os `targets` do sync-state; tipicamente):
   - `docs/reports/SISTEMA.md` — a parte correspondente (módulos, dados,
     fluxos, testes, próximos passos) + linha no **Changelog** do próprio
     SISTEMA.md (data, mudança, report-fonte) + o cabeçalho "Última
     sincronização".
   - `docs/dominio/<AREA>.md` — regras novas/alteradas entram em "As regras";
     pendências respondidas migram de "⚠️ Pendências" para as regras; "Mapa de
     código" atualizado; cabeçalho "Última sincronização: vX.Y.Z".
   - `CLAUDE.md` — seção "Estado atual" (números da suíte, o que existe), e
     mapa de pastas/módulos se mudou.
   - `docs/arquitetura/BANCO.md` — tabela de ownership + ER se houve migration.
   - `docs/README.md` — mapa de leitura se surgiu doc novo.
4. **Marque o report**: `status: "synced"`, `synced_at: <hoje>`.

## Passo 3 — Fechar

1. Atualize `last_sync` no sync-state: `at` (hoje), `by` (você — modelo), e
   `reports_processed` (os ids desta leva).
2. Valide o JSON (parse) e confira que nenhum link markdown criado está
   quebrado (os caminhos citados existem).
3. Commite as mudanças de docs (`docs: sincroniza documentação até vX.Y.Z/<slug>`),
   se o usuário autorizar.
4. Reporte: quais reports foram absorvidos, o que mudou em cada alvo, e
   qualquer divergência doc×código encontrada no caminho (com sugestão de
   correção).

## Regras

- **Só esta skill** muda `pending` → `synced` — e só DEPOIS de os alvos estarem
  de fato atualizados. Nunca marque "de confiança".
- Reports **não são editados** (fotografias imutáveis) — exceto correção de
  erro factual óbvio, com anotação `> [corrigido em <data>: ...]`.
- Não invente conteúdo que não esteja no report nem no código: o sync propaga,
  não cria. Lacuna de informação → pergunta ao usuário ou anotação `⚠️ PENDENTE`.
- Se um report pendente for grande demais para absorver com segurança numa
  sessão, processe-o sozinho (levas menores > sync malfeito).
