# 🧾 Domínio — regras de negócio

> **Esta pasta nasce vazia de propósito.** Ela é preenchida pela skill
> **`/prontuario`** quando o template vira um sistema real, e cresce a cada
> feature via **`/sync-docs`**. É a fonte canônica do O QUE o sistema faz — e o
> que a skill **`/dominio`** carrega para dar contexto de negócio a qualquer
> sessão de IA.

## O contrato de um documento de domínio

Um arquivo por **área de negócio** (não por classe, não por tela), em
`SCREAMING_SNAKE.md` — ex.: `PEDIDOS.md`, `FATURAMENTO.md`, `CREDENCIAIS.md`.
Cada documento segue esta estrutura:

```markdown
# <Área> — <uma frase que resume>

> Estado: ATIVO | PARCIAL (o que falta) | PLANEJADO
> Última sincronização: vX.Y.Z (data) — via sync-state.json

## O problema de negócio
Por que esta área existe. O que acontece no mundo real.

## As regras
As regras de negócio, numeradas, em linguagem de negócio (não de código).
Casos-limite explícitos. O que é decisão firme × o que é default provisório.

## O fluxo
Diagrama (mermaid) do caminho feliz + desvios. Estados e transições, se houver
máquina de estados.

## Decisões e porquês
Tabela: decisão | alternativas consideradas | por que esta | quando revisitar.

## ⚠️ Pendências do dono do produto
O que foi implementado com default provisório aguardando resposta. (Quando
respondido, a resposta migra para "As regras" e sai daqui.)

## Mapa de código
Onde cada regra vive: tabela regra → classe/método → teste que a garante.
```

## Por que este formato

- **"O problema de negócio" primeiro** — a IA (e o dev novo) precisa do porquê
  antes do como; é o que evita implementações tecnicamente corretas e
  semanticamente erradas.
- **Regras numeradas** — viram referência estável em commits, testes e reports
  ("implementa PEDIDOS.md regra 4").
- **"Mapa de código" no fim** — é a ponte doc↔código que o `/sync-docs` mantém;
  se a classe citada não existe mais, a doc está velha e o sync corrige.
- **Pendências explícitas** — a IA nunca deve resolver ambiguidade de negócio
  sozinha (regra de ouro nº 1); esta seção é onde a ambiguidade fica visível e
  cobrável.

## Exemplo de referência

Enquanto o `/prontuario` não roda, o "domínio" do template é o exemplo executável
(`orders`/`payments`/`notifications`) descrito em
[`../reports/SISTEMA.md`](../reports/SISTEMA.md) — use-o como calibre de
detalhe: cada regra que o código implementa está lá descrita e testada.
