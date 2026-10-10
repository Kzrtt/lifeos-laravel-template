# 📦 Reports — entregas versionadas + versionamento de contexto

Esta pasta implementa duas ideias complementares:

1. **Relatórios de entrega** (`vX.Y.Z/feat_*.md`) — fotografias datadas do que
   cada feature entregou. Preservam o contexto de QUANDO e POR QUE cada coisa
   foi feita, com as ressalvas honestas do momento. **Não se atualizam
   retroativamente.**
2. **Versionamento de contexto** (`sync-state.json` + `SISTEMA.md`) — o
   mecanismo que garante que a documentação *evergreen* nunca fique para trás:
   cada report nasce `pending` e a skill **`/sync-docs`** o propaga para os
   documentos vivos, marcando-o `synced`.

## Por que os dois níveis

Um report é ótimo para auditoria ("o que entrou na v0.0.3?") e péssimo como
fonte de contexto corrente (12 reports depois, ninguém sabe o estado agregado).
O `SISTEMA.md` é o inverso: retrato completo e atual, mas sem história. O
`sync-state.json` é a ponte — um índice máquina-legível que diz **exatamente
quais reports já foram absorvidos pela doc evergreen e quais não**. A IA não
precisa reler tudo nem adivinhar: lê o JSON, processa só o delta.

```
feature entregue
    └─> docs/reports/vX.Y.Z/feat_x.md      (fotografia, imutável)
    └─> sync-state.json: entrada "pending"  (dívida de documentação declarada)
            └─> /sync-docs (quando conveniente, 1..N reports de uma vez)
                    ├─> SISTEMA.md atualizado (evergreen)
                    ├─> docs/dominio/*.md atualizados (regras novas/mudadas)
                    ├─> CLAUDE.md §Estado atual / docs/README.md se preciso
                    └─> sync-state.json: entrada "synced" + last_sync
```

## O contrato do relatório de entrega (`feat_*.md`)

Nome: `feat_<slug>.md` dentro de `v<major>.<minor>.<patch>/`. Estrutura:

```markdown
# 📦 Documento de Entrega — <Projeto> · vX.Y.Z (iteração N)

## <Título da feature>

> **Data:** DD/MM/AAAA
> **Branch:** feat/<slug>
> **Escopo:** o que foi pedido, por quem, com que prioridade
> **Qualidade:** N testes / M asserts verdes · Pint verde · ArchTest verde

## O que mudou, em uma frase
A frase que um dev lê para decidir se precisa ler o resto.

## 1..N — blocos do que foi entregue
Por bloco: o que é, onde vive (caminhos reais), decisões tomadas e porquês,
o que ficou de fora e por quê.

## Ressalvas honestas
O que NÃO está 100%, defaults provisórios, pendências do dono do produto.

## Impacto na documentação (para o /sync-docs)
Lista objetiva: quais docs evergreen precisam absorver o quê. É o "diff de
contexto" — escreva-o AQUI, enquanto está fresco.
```

A seção **"Impacto na documentação"** é o que torna o sync barato: quem escreveu
a feature declara o delta de contexto na hora; quem sincroniza (a IA, depois) só
executa.

## O contrato do `sync-state.json`

```jsonc
{
  "version": 1,                    // versão do PRÓPRIO contrato (migrações futuras)
  "project": "...",                // nome do projeto
  "current_release": "v0.0.1",     // release em andamento (pasta atual de reports)
  "last_sync": {                   // última execução do /sync-docs (ou null)
    "at": "2026-07-07",
    "by": "claude-opus-4-8",       // quem executou (modelo/humano)
    "reports_processed": ["v0.0.1/feat_bootstrap"]
  },
  "targets": [                     // os documentos EVERGREEN que o sync mantém
    { "path": "docs/reports/SISTEMA.md", "role": "overview consolidado do sistema" },
    { "path": "CLAUDE.md",               "role": "regras + seção Estado atual" },
    { "path": "docs/dominio/",           "role": "regras de negócio por área" },
    { "path": "docs/README.md",          "role": "índice e mapa de leitura" },
    { "path": "docs/arquitetura/BANCO.md", "role": "tabela de ownership + ER" }
  ],
  "reports": [                     // UM registro por report, em ordem de entrega
    {
      "id": "v0.0.1/feat_bootstrap",       // vX.Y.Z/<slug> (sem .md)
      "file": "docs/reports/v0.0.1/feat_bootstrap.md",
      "title": "Nascimento do template",
      "delivered_at": "2026-07-07",
      "status": "synced",                  // "pending" | "synced"
      "synced_at": "2026-07-07",           // null enquanto pending
      "touches": ["SISTEMA.md"]            // dica: alvos que este report afeta
    }
  ]
}
```

### Regras de manutenção

- **Quem cria a entrada:** a skill `/nova-feature`, ao fechar a entrega
  (status `pending`, `synced_at: null`). Criar o report SEM registrar no JSON é
  violação da regra de ouro nº 10.
- **Quem muda `pending` → `synced`:** SOMENTE a skill `/sync-docs`, depois de
  efetivamente atualizar os targets. Nunca marque synced "de confiança".
- **Ordem de processamento:** sempre do report pendente mais ANTIGO para o mais
  novo (a doc evergreen evolui na mesma ordem da história).
- **Nova release:** ao abrir `v0.0.2/`, atualize `current_release`. Os reports
  da release anterior continuam no array (histórico completo).
- **Conflito:** se um report pendente contradiz a doc atual, o report vence
  (ele é mais novo) — mas se contradiz OUTRO report pendente mais novo, o mais
  novo vence e o sync anota a superação no SISTEMA.md.

## O `SISTEMA.md` (overview evergreen)

O único documento desta pasta que **se atualiza**: o retrato completo do sistema,
parte a parte (visão, stack, módulos, dados, fluxos, testes, como rodar, o que
falta). É o primeiro documento que uma sessão nova de IA deve ler (a skill
`/dominio` o carrega). Mantido exclusivamente via `/sync-docs` — editar à mão é
permitido, mas registre a edição na seção de changelog dele.

## Relação com o Git

Reports complementam commits, não os substituem: o commit diz O QUE mudou no
código; o report diz O QUE FOI ENTREGUE ao produto, com contexto e ressalvas. A
branch `feat/<slug>` do report deve existir no histórico (merge `--ff-only`
preferido — história linear).
