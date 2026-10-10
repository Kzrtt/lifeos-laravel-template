---
name: dominio
description: Carrega o contexto de negócio do sistema para a sessão — lê docs/dominio, SISTEMA.md, CLAUDE.md e o sync-state.json antes de responder perguntas sobre o domínio ou planejar trabalho. Use quando o usuário perguntar qualquer coisa sobre regras de negócio, "do que se trata o sistema", como um fluxo funciona, ou antes de planejar/implementar uma feature que toque regra de negócio.
---

# Carregar o contexto de domínio

Você vai montar o contexto de negócio ANTES de responder ou planejar. A regra é
a mesma do Notion no projeto de origem: **se não leu nesta sessão, você não
sabe** — memória de sessões passadas e suposições não contam.

## Sequência de leitura (nesta ordem)

1. **`CLAUDE.md`** — a Visão e as regras de ouro. Se a Visão ainda estiver
   marcada "PREENCHIDO PELO PRONTUÁRIO", avise o usuário de que o sistema ainda
   é o template genérico e sugira `/prontuario`; o "domínio" vigente é o exemplo
   executável (orders/payments/notifications).
2. **`docs/reports/sync-state.json`** — cheque `reports[].status`. Se houver
   reports `pending`, AVISE: "a documentação evergreen está X entregas atrás do
   código" — e leia TAMBÉM os reports pendentes (são mais novos que o
   SISTEMA.md; em conflito, o report pendente vence). Sugira `/sync-docs`.
3. **`docs/reports/SISTEMA.md`** — o retrato consolidado. Leia inteiro na
   primeira vez na sessão; nas seguintes, as partes relevantes à pergunta.
4. **`docs/dominio/*.md`** — os documentos das áreas tocadas pela pergunta
   (todos, se a pergunta for panorâmica). Preste atenção especial às seções
   "⚠️ Pendências do dono do produto" — elas delimitam o que NINGUÉM decidiu
   ainda.
5. **Código, se a pergunta exigir precisão de implementação** — siga o "Mapa de
   código" do doc de domínio até a classe/teste citados; o teste é a descrição
   mais confiável do comportamento real.

## Ao responder

- Cite a fonte de cada afirmação de negócio (`docs/dominio/PEDIDOS.md regra 4`,
  `SISTEMA.md §VIII`) — resposta sem fonte é opinião.
- Se docs e código divergirem: reporte a divergência explicitamente (é um bug de
  documentação — candidata a `/sync-docs` ou correção), e diga qual dos dois o
  usuário provavelmente quer como verdade.
- Se a resposta depender de algo em "⚠️ Pendências": diga "isso está pendente de
  decisão do dono do produto" — NÃO invente a resposta (regra de ouro nº 1).
- Se a pergunta revelar uma lacuna na documentação (área sem doc, regra não
  escrita): responda pelo código, e ofereça registrar a regra no doc de domínio
  correspondente.
