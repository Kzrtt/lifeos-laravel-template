# Referência do resumo financeiro

`secao.ts` é a seção "RESUMO FINANCEIRO" do `lifeos-mcp/index.ts` do Supabase (cópia
literal, mais um `export`). `driver.mjs` roda essa seção em Node sobre movimentações
sintéticas determinísticas e grava `fixture.json` com o resultado esperado de cada
cenário. O teste `ResumoFinanceiroParidadeTest` roda o PHP sobre as mesmas
movimentações e exige saída idêntica, byte a byte.

Regenerar (só se a regra mudar nos dois lados): `node driver.mjs`.
