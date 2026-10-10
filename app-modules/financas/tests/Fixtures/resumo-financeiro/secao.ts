// ═══ RESUMO FINANCEIRO · início ══════════════════════════════════════════
//
// Os números da tela de Finanças (lifeos/financas.html), calculados aqui
// com as MESMAS regras -- terceira cópia da lógica, junto de financas.js e
// lifeos.js (LIFEOS.md §2: cópia, não import; decisão do dono, set/2026,
// até o port pra Laravel). Nomes e comentários seguem financas.js pra
// facilitar comparar as três lado a lado.
//
// Esta seção é PURA: sem fetch, sem Deno, sem VOCAB global -- recebe as
// linhas e devolve o resumo. É isso que permite verificar a porta rodando
// as duas implementações sobre os mesmos dados (o teste extrai o trecho
// entre os marcadores "RESUMO FINANCEIRO · início/fim").
//
// Regras (ver o modal "Como funciona" de financas.html):
//  - Compra em Crédito não sai do caixa no mês: entra em Saídas "com
//    crédito", fica fora do Saldo e vira fatura futura.
//  - Saldo = saldo de abertura (RPC lifeos_saldo_abertura) + entradas
//    realizadas − saídas de caixa realizadas. Data futura conta nos totais,
//    mas só entra no saldo quando a data chegar.
//  - Pagamento de fatura = Saida SEM meio com "fatura" no nome. Abate a
//    fatura que fecha no mês; o excedente vira adiantamento da seguinte,
//    carregado em cadeia (carryInto). "adiant" no nome = adiantamento
//    explícito, que pula direto pra próxima fatura.
//  - A fatura fecha no último dia do mês: compra antes dele cai em M+1; no
//    último dia, em M+2.
//
// Análise (set/2026, criarAnalise no fim da seção): campos ACRESCENTADOS ao
// resumo -- consumo, rateios, compromissos, sinais, ritmo e projeção. Não
// mexe em nada de calcularResumoMes. O nome da movimentação É a categoria;
// finChave junta grafias diferentes da mesma coisa. As constantes abaixo
// são o ajuste fino da heurística.

// Chave normalizada (sem acento, minúscula, espaços colapsados) -> chave
// canônica. As três últimas são grafias antigas de jun/2026.
const ALIASES: Record<string, string> = {
  "conta luz": "luz",
  "conta internet": "internet",
  "financiamento carro": "carro",
  "crunchroll": "crunchyroll",
  "posto delta": "delta",
  "areia gato": "areia",
  "conta de luz (variavel)": "luz",
  "conta de internet": "internet",
  "aluguel (variavel)": "aluguel",
};
// Compras feitas em nome de vários amigos: a Entrada com esse nome é a
// parte deles (não é renda); o custo real é Saída menos Entrada.
const RATEIOS = ["corre", "cogumelo"];
// Primeiro mês com dados de verdade; os anteriores ficam fora de janelas,
// do lookback de pontuais e das médias (senão entram como zero). null =
// automático: o primeiro mês com MIN_MOVIMENTACOES_INICIO lançamentos.
const INICIO_DADOS: string | null = null;
const MIN_MOVIMENTACOES_INICIO = 20;
const JANELA_COMPROMISSO = 3;            // meses completos; com menos (mín. 2), janela_incompleta
const MAX_OCORRENCIAS_COMPROMISSO = 2;   // por mês; acima disso é hábito
const TOLERANCIA_COMPROMISSO = 0.15;     // variacao_alta fora disso da mediana
// Chaves (ver finChave). "cartao" = parcelas antigas, todo dia 1; em mai/2026
// o nome era outro, e sem isso ele só vira compromisso com a janela jul-set.
const COMPROMISSOS_FORCAR: string[] = ["cartao"];
const COMPROMISSOS_IGNORAR: string[] = [];
const LIMIAR_PONTUAL = 200;
const MIN_REPETICOES_TICKET = 4;
const LIMIAR_ENTRADA_GRANDE = 300;

type Mov ={ id?: string; name: string; valor: number; date: string; tipo: string[]; created_at?: string };

function finNum(v: unknown): number { const n = Number(v); return Number.isFinite(n) ? n : 0; }
function finRound2(v: number): number { return Math.round(v * 100) / 100; }
function finHas(m: Mov, tag: string) { return Array.isArray(m.tipo) && m.tipo.includes(tag); }
function finIsSaida(m: Mov) { return finHas(m, "Saida"); }
function finIsEntrada(m: Mov) { return finHas(m, "Entrada"); }
function finNextMonth(ym: string) { const [y, m] = ym.split("-").map(Number); return m === 12 ? `${y + 1}-01` : `${y}-${String(m + 1).padStart(2, "0")}`; }
function finPrevMonth(ym: string) { const [y, m] = ym.split("-").map(Number); return m === 1 ? `${y - 1}-12` : `${y}-${String(m - 1).padStart(2, "0")}`; }
function finLastDay(ym: string) { const [y, m] = ym.split("-").map(Number); return new Date(Date.UTC(y, m, 0)).getUTCDate(); }
function finFaturaDestino(ym: string, dia: number) { const prox = finNextMonth(ym); return dia < finLastDay(ym) ? prox : finNextMonth(prox); }
function finCmpByDateAsc(a: Mov, b: Mov) { return (a.date || "").localeCompare(b.date || "") || (a.created_at || "").localeCompare(b.created_at || ""); }
function finNormName(s: string) { return (s || "").trim().toLowerCase().replace(/\s+/g, " "); }

function finCtx(meios: string[], hojeISO: string) {
  const hasAnyMeio = (m: Mov) => meios.some((me) => finHas(m, me));
  const isSaidaCaixa = (m: Mov) => finIsSaida(m) && !finHas(m, "Crédito");
  const isPagamentoFatura = (m: Mov) => finIsSaida(m) && !hasAnyMeio(m) && /fatura/i.test(m.name || "");
  const isAdiantamentoExplicito = (m: Mov) => isPagamentoFatura(m) && /adiant/i.test(m.name || "");
  const isRealizado = (m: Mov) => !m.date || m.date <= hojeISO;
  return { hasAnyMeio, isSaidaCaixa, isPagamentoFatura, isAdiantamentoExplicito, isRealizado };
}

// Cópia de financas.js/splitPagamentosFatura -- comparação em CENTAVOS.
function finSplitPagamentos(pagamentos: Mov[], totalFatura: number) {
  const sorted = pagamentos.slice().sort(finCmpByDateAsc);
  const atual: Mov[] = [], adiantamento: Mov[] = [];
  let cumC = 0;
  const totalC = Math.round(totalFatura * 100);
  for (const m of sorted) {
    const valorC = Math.round(finNum(m.valor) * 100);
    const restanteC = totalC - cumC;
    if (restanteC <= 0) adiantamento.push(m);
    else if (valorC > restanteC) {
      atual.push({ ...m, valor: restanteC / 100 });
      adiantamento.push({ ...m, valor: (valorC - restanteC) / 100 });
    } else atual.push(m);
    cumC += valorC;
  }
  return { atual, adiantamento };
}

// Cópia de financas.js/calcularProjecaoFatura.
function finProjecaoFatura(rows: Mov[], ym: string) {
  const groups: Record<string, { total: number; rows: Mov[] }> = {};
  for (const m of rows) {
    if (!finIsSaida(m) || !finHas(m, "Crédito")) continue;
    if (/fatura/i.test(m.name || "")) continue; // pagamento da fatura, não compra nova
    const d = m.date ? parseInt(m.date.slice(8, 10), 10) : 0;
    const destino = finFaturaDestino(ym, d);
    (groups[destino] ??= { total: 0, rows: [] });
    groups[destino].total += finNum(m.valor);
    groups[destino].rows.push(m);
  }
  return groups;
}

// Resumo de UM mês. `rowsDoMes(ym)` devolve as movimentações de qualquer
// mês (a cadeia de faturas olha pra trás); `abertura` vem da RPC.
function calcularResumoMes(
  rowsDoMes: (ym: string) => Mov[], ym: string, abertura: number, hojeISO: string, meios: string[],
) {
  const c = finCtx(meios, hojeISO);
  const MROWS = rowsDoMes(ym);

  // KPIs -- cópia de computeMonthKpis.
  let entradas = 0, saidasTotais = 0, entradasRealizadas = 0, saidasCaixaRealizadas = 0, futuras = 0;
  for (const m of MROWS) {
    const v = finNum(m.valor), realizado = c.isRealizado(m);
    if (!realizado) futuras++;
    if (finIsEntrada(m)) { entradas += v; if (realizado) entradasRealizadas += v; }
    if (finIsSaida(m)) saidasTotais += v;
    if (c.isSaidaCaixa(m) && realizado) saidasCaixaRealizadas += v;
  }
  const variacaoMes = entradasRealizadas - saidasCaixaRealizadas;

  // Por meio -- cópia de computeDonutBuckets (modos saida e entrada).
  const porMeio = (dir: (m: Mov) => boolean) => {
    const out: Record<string, number> = {};
    let semMeio = 0;
    for (const m of MROWS) {
      if (!dir(m)) continue;
      const v = finNum(m.valor);
      let achou = false;
      for (const me of meios) if (finHas(m, me)) { out[me] = (out[me] || 0) + v; achou = true; }
      if (!achou) semMeio += v;
    }
    if (semMeio > 0) out["Sem meio"] = semMeio;
    for (const k of Object.keys(out)) out[k] = finRound2(out[k]);
    return out;
  };

  // Saldo acumulado dia a dia -- cópia de renderSaldo; aqui só o mínimo.
  const porDia: Record<string, number> = {};
  for (const m of MROWS) {
    if (!c.isRealizado(m)) continue;
    let delta = 0;
    if (finIsEntrada(m)) delta += finNum(m.valor);
    if (c.isSaidaCaixa(m)) delta -= finNum(m.valor);
    porDia[m.date] = (porDia[m.date] || 0) + delta;
  }
  let acc = abertura, saldoMinimo: { valor: number; dia: string | null } = { valor: abertura, dia: null };
  for (const d of Object.keys(porDia).sort()) {
    acc += porDia[d];
    if (acc < saldoMinimo.valor) saldoMinimo = { valor: acc, dia: d };
  }

  // Cadeia de faturas -- cópia de carryInto (síncrona: os dados já estão
  // todos em memória; memo por mês no lugar do cache de fetch).
  const memo: Record<string, Mov[]> = {};
  const carryInto = (m: string): Mov[] => {
    if (memo[m]) return memo[m];
    const rows = rowsDoMes(m);
    const pagamentosFatura = rows.filter(c.isPagamentoFatura);
    let res: Mov[];
    if (!pagamentosFatura.length) res = [];
    else {
      const explicitos = pagamentosFatura.filter(c.isAdiantamentoExplicito);
      const normais = pagamentosFatura.filter((x) => !c.isAdiantamentoExplicito(x));
      if (!normais.length) res = explicitos;
      else {
        const pm = finPrevMonth(m);
        const g = finProjecaoFatura(rowsDoMes(pm), pm)[m];
        const totalFaturaM = (g && g.rows.length) ? g.total : 0;
        const split = finSplitPagamentos(normais.concat(carryInto(pm)), totalFaturaM);
        res = split.adiantamento.concat(explicitos);
      }
    }
    return (memo[m] = res);
  };

  // Fatura deste mês -- cópia de renderFaturaMesPassado.
  const pYm = finPrevMonth(ym);
  const pagamentosFaturaAtual = MROWS.filter(c.isPagamentoFatura);
  const explicitosAtual = pagamentosFaturaAtual.filter(c.isAdiantamentoExplicito);
  const normaisAtual = pagamentosFaturaAtual.filter((m) => !c.isAdiantamentoExplicito(m));
  const gAtual = finProjecaoFatura(rowsDoMes(pYm), pYm)[ym];
  const totalFatura = (gAtual && gAtual.rows.length) ? gAtual.total : 0;
  const pagamentosFatura = normaisAtual.concat(carryInto(pYm));
  const pago = pagamentosFatura.reduce((s, m) => s + finNum(m.valor), 0);
  const split = finSplitPagamentos(pagamentosFatura, totalFatura);
  const carryOut = split.adiantamento.concat(explicitosAtual);
  const excedente = finRound2(carryOut.reduce((s, m) => s + finNum(m.valor), 0));

  // Fatura projetada -- cópia de renderFaturaProjetada + nota de adiantamento
  // (o adiantamento abate só o PRIMEIRO destino, como na tela).
  const proj = finProjecaoFatura(MROWS, ym);
  const faturaProjetada = Object.keys(proj).sort().map((dest, i) => ({
    fatura: dest,
    total: finRound2(proj[dest].total),
    compras: proj[dest].rows.length,
    ...(i === 0 && excedente > 0 ? { valor_apos_adiantamento: finRound2(Math.max(0, proj[dest].total - excedente)) } : {}),
  }));

  // Recorrências -- cópia de renderRecList (sem filtros), só as que repetem.
  const grupos: Record<string, { descricao: string; items: Mov[] }> = {};
  for (const m of MROWS) {
    const key = finNormName(m.name) || "—";
    (grupos[key] ??= { descricao: m.name || "—", items: [] }).items.push(m);
  }
  const recorrencias = Object.values(grupos).map((g) => {
    let e = 0, s = 0;
    for (const m of g.items) { if (finIsEntrada(m)) e += finNum(m.valor); if (finIsSaida(m)) s += finNum(m.valor); }
    return { descricao: g.descricao, vezes: g.items.length, entradas: finRound2(e), saidas: finRound2(s), liquido: finRound2(e - s) };
  })
    .sort((a, b) => (b.vezes - a.vezes) || (Math.abs(b.liquido) - Math.abs(a.liquido)))
    .filter((g) => g.vezes > 1);

  const maioresSaidas = MROWS.filter(finIsSaida)
    .sort((a, b) => finNum(b.valor) - finNum(a.valor)).slice(0, 5)
    .map((m) => ({ data: m.date, descricao: m.name, valor: finRound2(finNum(m.valor)), meio: meios.filter((me) => finHas(m, me)) }));

  return {
    mes: ym,
    movimentacoes: MROWS.length,
    lancamentos_futuros: futuras,
    entradas: finRound2(entradas),
    saidas_caixa: finRound2(saidasCaixaRealizadas),
    saidas_com_credito: finRound2(saidasTotais),
    saldo_abertura: finRound2(abertura),
    variacao_mes: finRound2(variacaoMes),
    saldo: finRound2(abertura + variacaoMes),
    saldo_minimo: { valor: finRound2(saldoMinimo.valor), dia: saldoMinimo.dia },
    saidas_por_meio: porMeio(finIsSaida),
    entradas_por_meio: porMeio(finIsEntrada),
    fatura_deste_mes: {
      compras_de: pYm,
      total: finRound2(totalFatura),
      compras: gAtual ? gAtual.rows.length : 0,
      pago: finRound2(Math.min(pago, totalFatura)),
      pagamentos: split.atual.length,
      restante: finRound2(Math.max(0, totalFatura - pago)),
      quitada: totalFatura > 0 && finRound2(Math.max(0, totalFatura - pago)) <= 0,
      adiantamento_para_proxima: excedente,
    },
    fatura_projetada: faturaProjetada,
    recorrencias: recorrencias.slice(0, 8),
    maiores_saidas: maioresSaidas,
  };
}

// ── Análise ──────────────────────────────────────────────────────────────

function finChave(nome: string): string {
  const k = (nome || "").normalize("NFD").replace(/[\u0300-\u036f]/g, "")
    .toLowerCase().trim().replace(/\s+/g, " ");
  return ALIASES[k] ?? k;
}
function finMeioStr(m: Mov, meios: string[]): string | null {
  const ms = meios.filter((me) => finHas(m, me));
  return ms.length ? ms.join("+") : null;
}
function finMediana(xs: number[]): number {
  if (!xs.length) return 0;
  const s = xs.slice().sort((a, b) => a - b), h = Math.floor(s.length / 2);
  return s.length % 2 ? s[h] : (s[h - 1] + s[h]) / 2;
}
function finDia(m: Mov) { return m.date ? parseInt(m.date.slice(8, 10), 10) : 0; }
function finSomaDias(iso: string, n: number) {
  const d = new Date(iso + "T00:00:00Z");
  d.setUTCDate(d.getUTCDate() + n);
  return d.toISOString().slice(0, 10);
}
function finSoma(xs: number[]) { return xs.reduce((s, x) => s + x, 0); }

type Recorrente = {
  chave: string; nome: string; valor_tipico: number; valor_min?: number; valor_max?: number;
  dia_tipico: number | null; meio_tipico: string | null;
  variacao_alta: boolean; ja_lancado: boolean; valor_lancado: number; origem: string;
};
// Linha ativa de lifeos_recorrencias (cadastro em Finanças → Recorrências
// previstas; ver FINANCAS.md §9.2).
type Cadastrada = { nome: string; direcao: string; valor_min: number; valor_max: number; meio: string | null; dia: number | null };

// `todas` = todas as linhas lidas (o início dos dados, a grafia de exibição e
// as entradas grandes olham além dos meses pedidos). Um mês é "completo"
// quando já terminou e não é anterior ao início dos dados; o corrente
// (parcial) e os futuros ficam fora de médias e janelas.
function criarAnalise(
  rowsDoMes: (ym: string) => Mov[], todas: Mov[], hojeISO: string, meios: string[], cadastradas: Cadastrada[] = [],
) {
  const mesHoje = hojeISO.slice(0, 7);
  const diaHoje = parseInt(hojeISO.slice(8, 10), 10);
  const c = finCtx(meios, hojeISO);
  // Pela chave, e não por isPagamentoFatura: existe "Fatura " com espaço, e
  // um pagamento lançado com meio por engano também não é consumo.
  const ehFatura = (m: Mov) => finIsSaida(m) && finChave(m.name).includes("fatura");
  const ehRateio = (m: Mov) => RATEIOS.includes(finChave(m.name));
  const r2 = finRound2;

  // Cadastro por direção, uma linha por chave (dois nomes que viram a mesma
  // chave pelos ALIASES contariam em dobro: vale o primeiro).
  const cadastroPorDirecao: Record<string, Cadastrada[]> = { Entrada: [], Saida: [] };
  for (const c of cadastradas) {
    const lista = cadastroPorDirecao[c.direcao];
    if (!lista || lista.some((x) => finChave(x.nome) === finChave(c.nome))) continue;
    lista.push(c);
  }

  const porMesQtd: Record<string, number> = {};
  for (const m of todas) { const ym = (m.date || "").slice(0, 7); porMesQtd[ym] = (porMesQtd[ym] || 0) + 1; }
  const inicio = INICIO_DADOS
    ?? Object.keys(porMesQtd).sort().find((ym) => porMesQtd[ym] >= MIN_MOVIMENTACOES_INICIO)
    ?? mesHoje;
  const completo = (ym: string) => ym >= inicio && ym < mesHoje;

  const grafias: Record<string, Record<string, number>> = {};
  for (const m of todas) {
    const g = (m.name || "").trim(), k = finChave(m.name);
    const gs = (grafias[k] ??= {});
    gs[g] = (gs[g] || 0) + 1;
  }
  const nomes: Record<string, string> = {};
  const nomeDe = (k: string) =>
    nomes[k] ??= (Object.entries(grafias[k] ?? {}).sort((a, b) => b[1] - a[1])[0]?.[0] ?? k);

  const entradasGrandes = todas.filter((m) => finIsEntrada(m) && !ehRateio(m) && finNum(m.valor) >= LIMIAR_ENTRADA_GRANDE);

  // Os JANELA_COMPROMISSO meses completos mais recentes até ym; perto do
  // início dos dados, os que houver.
  const janela = (ym: string) => {
    const out: string[] = [];
    let m = ym < mesHoje ? ym : finPrevMonth(mesHoje);
    for (let i = 0; i < JANELA_COMPROMISSO && m >= inicio; i++) { out.unshift(m); m = finPrevMonth(m); }
    return out;
  };

  const consumoMes = (ym: string) => {
    let s = 0;
    for (const m of rowsDoMes(ym)) {
      if (finIsSaida(m) && !ehFatura(m)) s += finNum(m.valor);
      else if (finIsEntrada(m) && ehRateio(m)) s -= finNum(m.valor);
    }
    return s;
  };

  // Gasto variável acumulado até o dia `dia`: consumo sem os compromissos.
  // `semPontuais` é pras médias: um conserto de 1600 não é ritmo.
  const variavelAte = (ym: string, dia: number, comp: Set<string>, semPontuais = false) => {
    const fora = semPontuais ? new Set(pontuaisDe(ym)) : null;
    let s = 0;
    for (const m of rowsDoMes(ym)) {
      if (finDia(m) > dia || fora?.has(m)) continue;
      if (finIsSaida(m) && !ehFatura(m) && !comp.has(finChave(m.name))) s += finNum(m.valor);
      else if (finIsEntrada(m) && ehRateio(m)) s -= finNum(m.valor);
    }
    return s;
  };

  // Primeiro mês com janela cheia. Mês cuja janela própria é incompleta
  // (começo dos dados) usa a janela dele: com 2 meses a heurística pega
  // coincidência (uma multa em jun e jul), com 1 não pega nada.
  let primeiroCheio: string | null = null;
  for (let m = inicio; m < mesHoje; m = finNextMonth(m)) {
    if (janela(m).length === JANELA_COMPROMISSO) { primeiroCheio = m; break; }
  }

  // Compromissos (saídas) ou renda recorrente (entradas) vistos de ym: chave
  // presente em TODOS os meses da janela, no máximo N vezes por mês. Sem
  // janela cheia pra emprestar, usa a que houver; com menos de 2 meses, a
  // heurística não se sustenta e a lista sai vazia. ja_lancado/valor_lancado
  // são sempre do próprio ym.
  const recMemo: Record<string, {
    itens: Recorrente[]; total: number; total_min?: number; total_max?: number;
    fonte: string; sugestoes?: Omit<Recorrente, "chave">[];
    janela: string[]; janela_incompleta: boolean; janela_emprestada_de: string | null;
  }> = {};
  const recorrentes = (ym: string, entrada: boolean) => {
    const memoKey = ym + (entrada ? "E" : "S");
    if (recMemo[memoKey]) return recMemo[memoKey];
    const propria = janela(ym);
    const incompleta = propria.length < JANELA_COMPROMISSO;
    const emprestada = incompleta ? primeiroCheio : null;
    const jan = emprestada ? janela(emprestada) : propria;
    const elegivel = (m: Mov) => (entrada ? finIsEntrada(m) : finIsSaida(m)) && !ehFatura(m) && !ehRateio(m);
    const st: Record<string, { meses: Record<string, { qtd: number; soma: number }>; dias: number[]; meios: Record<string, number> }> = {};
    for (const j of jan) for (const m of rowsDoMes(j)) {
      if (!elegivel(m)) continue;
      const s = (st[finChave(m.name)] ??= { meses: {}, dias: [], meios: {} });
      const sm = (s.meses[j] ??= { qtd: 0, soma: 0 });
      sm.qtd++; sm.soma += finNum(m.valor);
      s.dias.push(finDia(m));
      const me = finMeioStr(m, meios) ?? "";
      s.meios[me] = (s.meios[me] || 0) + 1;
    }
    const lancado: Record<string, number> = {};
    for (const m of rowsDoMes(ym)) if (elegivel(m)) {
      const k = finChave(m.name);
      lancado[k] = (lancado[k] || 0) + finNum(m.valor);
    }
    let chaves = jan.length < 2 ? [] : Object.keys(st).filter((k) =>
      jan.every((j) => st[k].meses[j] && st[k].meses[j].qtd <= MAX_OCORRENCIAS_COMPROMISSO));
    if (!entrada && jan.length >= 2) {
      // Forçado sem nenhuma ocorrência na janela não tem valor típico: fica de fora.
      for (const k of COMPROMISSOS_FORCAR) if (st[k] && !chaves.includes(k)) chaves.push(k);
      chaves = chaves.filter((k) => !COMPROMISSOS_IGNORAR.includes(k));
    }
    const somasDe = (k: string) => st[k] ? jan.filter((j) => st[k].meses[j]).map((j) => st[k].meses[j].soma) : [];
    const meioHist = (k: string) => st[k] ? (Object.entries(st[k].meios).sort((a, b) => b[1] - a[1])[0][0] || null) : null;
    const diaHist = (k: string) => st[k] ? Math.round(finMediana(st[k].dias)) : null;
    const heuristica: Recorrente[] = chaves.map((k) => {
      const somas = somasDe(k);
      // Renda muda em degrau (reajuste), não oscila: vale o mês mais recente.
      // Compromisso oscila (luz, aluguel com condomínio): vale a mediana.
      const tipico = entrada ? somas[somas.length - 1] : finMediana(somas);
      return {
        chave: k,
        nome: nomeDe(k),
        valor_tipico: r2(tipico),
        dia_tipico: diaHist(k),
        meio_tipico: meioHist(k),
        variacao_alta: somas.some((x) => Math.abs(x - tipico) > TOLERANCIA_COMPROMISSO * tipico),
        ja_lancado: k in lancado,
        valor_lancado: r2(lancado[k] ?? 0),
        origem: "heuristica",
      };
    }).sort((a, b) => b.valor_tipico - a.valor_tipico);

    // Cadastro manda, por direção: com pelo menos uma recorrência ativa
    // nessa direção, os itens são só os cadastrados e a heurística vira
    // sugestão (o que ela acha e não está cadastrado). Valor típico = meio da
    // faixa; meio e dia não informados vêm do histórico.
    const cad = cadastroPorDirecao[entrada ? "Entrada" : "Saida"];
    let itens = heuristica, sugestoes: Omit<Recorrente, "chave">[] | undefined;
    if (cad.length) {
      itens = cad.map((c) => {
        const k = finChave(c.nome), somas = somasDe(k);
        const min = c.valor_min, max = c.valor_max;
        return {
          chave: k,
          nome: c.nome,
          valor_tipico: r2((min + max) / 2),
          valor_min: r2(min),
          valor_max: r2(max),
          dia_tipico: c.dia ?? diaHist(k),
          meio_tipico: c.meio ?? meioHist(k),
          // Algum mês da janela fora da faixa cadastrada (com a tolerância).
          variacao_alta: somas.some((x) => x < min * (1 - TOLERANCIA_COMPROMISSO) || x > max * (1 + TOLERANCIA_COMPROMISSO)),
          ja_lancado: k in lancado,
          valor_lancado: r2(lancado[k] ?? 0),
          origem: "cadastro",
        };
      }).sort((a, b) => b.valor_tipico - a.valor_tipico);
      const cadChaves = new Set(itens.map((i) => i.chave));
      sugestoes = heuristica.filter((i) => !cadChaves.has(i.chave)).map(semChave);
    }
    return (recMemo[memoKey] = {
      itens, total: r2(finSoma(itens.map((i) => i.valor_tipico))),
      ...(cad.length ? {
        total_min: r2(finSoma(itens.map((i) => i.valor_min ?? i.valor_tipico))),
        total_max: r2(finSoma(itens.map((i) => i.valor_max ?? i.valor_tipico))),
      } : {}),
      fonte: cad.length ? "cadastro" : "heuristica",
      sugestoes,
      janela: jan, janela_incompleta: incompleta, janela_emprestada_de: emprestada,
    });
  };
  const semChave = (i: Recorrente) => { const { chave: _, ...rest } = i; return rest; };
  const ehCredito = (i: Recorrente) => (i.meio_tipico ?? "").split("+").includes("Crédito");

  // Pontuais: saída grande cujo nome não aparece nos 3 meses anteriores
  // (dentro dos dados). Perto do início dos dados a referência é completada
  // com os meses completos seguintes; senão todo gasto do primeiro mês seria
  // "novo". Compromisso (inclusive forçado) nunca é pontual.
  const pontMemo: Record<string, Mov[]> = {};
  const pontuaisDe = (ym: string): Mov[] => {
    if (pontMemo[ym]) return pontMemo[ym];
    const comp = new Set(recorrentes(ym, false).itens.map((i) => i.chave));
    const ref: string[] = [];
    for (let p = finPrevMonth(ym); ref.length < 3 && p >= inicio; p = finPrevMonth(p)) ref.push(p);
    for (let p = finNextMonth(ym); ref.length < 3 && completo(p); p = finNextMonth(p)) ref.push(p);
    const vistas = new Set<string>();
    for (const p of ref) for (const m of rowsDoMes(p)) vistas.add(finChave(m.name));
    return (pontMemo[ym] = rowsDoMes(ym).filter((m) => {
      const k = finChave(m.name);
      return finIsSaida(m) && !ehFatura(m) && !ehRateio(m) && !comp.has(k)
        && finNum(m.valor) >= LIMIAR_PONTUAL && !vistas.has(k);
    }));
  };
  // Variável médio por dia nos meses da janela, sem os pontuais de cada mês.
  const mediaDiariaJanela = (jan: string[], comp: Set<string>) =>
    jan.length ? finSoma(jan.map((j) => variavelAte(j, 31, comp, true))) / finSoma(jan.map(finLastDay)) : 0;

  const analisarMes = (ym: string, opts: { porNome: boolean; lista: boolean }) => {
    const rows = rowsDoMes(ym);
    const comp = recorrentes(ym, false), renda = recorrentes(ym, true);
    const setComp = new Set(comp.itens.map((i) => i.chave));
    const consumo = consumoMes(ym);

    // Rateios e entradas próprias.
    const rateiosK: Record<string, { entrou: number; saiu: number }> = {};
    const entradasK: Record<string, number> = {};
    let entradas = 0, entradasRateio = 0;
    for (const m of rows) {
      const v = finNum(m.valor), k = finChave(m.name);
      if (finIsEntrada(m)) entradas += v;
      if (ehRateio(m)) {
        const r = (rateiosK[k] ??= { entrou: 0, saiu: 0 });
        if (finIsEntrada(m)) { r.entrou += v; entradasRateio += v; }
        if (finIsSaida(m)) r.saiu += v;
      } else if (finIsEntrada(m)) entradasK[k] = (entradasK[k] || 0) + v;
    }
    const rateios: Record<string, { entrou: number; saiu: number; custo_dono: number }> = {};
    for (const [k, r] of Object.entries(rateiosK)) rateios[nomeDe(k)] = { entrou: r2(r.entrou), saiu: r2(r.saiu), custo_dono: r2(r.saiu - r.entrou) };
    const entradasPorNome: Record<string, number> = {};
    for (const [k, v] of Object.entries(entradasK).sort((a, b) => b[1] - a[1])) entradasPorNome[nomeDe(k)] = r2(v);

    // Por nome (só no modo mês único).
    let porNome;
    if (opts.porNome) {
      const g: Record<string, { e: number; s: number; qe: number; qs: number; meios: Set<string> }> = {};
      for (const m of rows) {
        if (ehFatura(m)) continue;
        const x = (g[finChave(m.name)] ??= { e: 0, s: 0, qe: 0, qs: 0, meios: new Set() });
        const v = finNum(m.valor);
        if (finIsEntrada(m)) { x.e += v; x.qe++; }
        if (finIsSaida(m)) { x.s += v; x.qs++; }
        for (const me of meios) if (finHas(m, me)) x.meios.add(me);
      }
      porNome = Object.entries(g).map(([k, x]) => ({
        nome: nomeDe(k), entradas: r2(x.e), saidas: r2(x.s), liquido: r2(x.e - x.s),
        qtd_entradas: x.qe, qtd_saidas: x.qs,
        ticket_medio: x.qs ? r2(x.s / x.qs) : null, meios: [...x.meios],
      })).sort((a, b) => (b.saidas - a.saidas) || (b.entradas - a.entradas));
    }

    // Sinais.
    const ehVariavel = (m: Mov) => finIsSaida(m) && !ehFatura(m) && !ehRateio(m) && !setComp.has(finChave(m.name));
    const dias: Record<string, { total: number; itens: { nome: string; valor: number }[] }> = {};
    for (const m of rows) if (ehVariavel(m)) {
      const d = (dias[m.date] ??= { total: 0, itens: [] });
      d.total += finNum(m.valor);
      d.itens.push({ nome: (m.name || "").trim(), valor: r2(finNum(m.valor)) });
    }
    const picos = Object.entries(dias).sort((a, b) => b[1].total - a[1].total).slice(0, 5).map(([data, d]) => ({
      data, total: r2(d.total), itens: d.itens.sort((a, b) => b.valor - a.valor),
      apos_entrada: entradasGrandes.some((e) => e.date >= finSomaDias(data, -2) && e.date <= data),
    }));
    const tickets: Record<string, { k: string; valor: number; vezes: number }> = {};
    for (const m of rows) if (finIsSaida(m) && !ehFatura(m)) {
      const k = finChave(m.name), id = k + "|" + Math.round(finNum(m.valor) * 100);
      (tickets[id] ??= { k, valor: finNum(m.valor), vezes: 0 }).vezes++;
    }
    const ticketsRepetidos = Object.values(tickets).filter((t) => t.vezes >= MIN_REPETICOES_TICKET)
      .map((t) => ({ nome: nomeDe(t.k), valor: r2(t.valor), vezes: t.vezes, total: r2(t.valor * t.vezes) }))
      .sort((a, b) => (b.vezes - a.vezes) || (b.total - a.total));
    const pontuais = pontuaisDe(ym).map((m) => ({ data: m.date, nome: (m.name || "").trim(), valor: r2(finNum(m.valor)) }));

    // Ritmo (só no mês corrente). Toda média e comparação é sem pontuais;
    // só fechamento_projetado inclui os pontuais já lançados (é dinheiro que
    // já saiu).
    let ritmo;
    if (ym === mesHoje) {
      const jan = janela(ym), diasNoMes = finLastDay(ym);
      const ate = variavelAte(ym, diaHoje, setComp);
      const ateSem = variavelAte(ym, diaHoje, setComp, true);
      const mediaDiaria = ateSem / diaHoje;
      const mesmoDia = jan.length
        ? finSoma(jan.map((j) => variavelAte(j, Math.min(diaHoje, finLastDay(j)), setComp, true))) / jan.length
        : null;
      ritmo = {
        dias_decorridos: diaHoje,
        dias_no_mes: diasNoMes,
        variavel_ate_hoje: r2(ate),
        variavel_ate_hoje_sem_pontuais: r2(ateSem),
        media_mesmo_dia: mesmoDia !== null ? r2(mesmoDia) : null,
        desvio_pct: mesmoDia ? Math.round((ateSem - mesmoDia) / mesmoDia * 1000) / 10 : null,
        media_diaria: r2(mediaDiaria),
        media_diaria_janela: jan.length ? r2(mediaDiariaJanela(jan, setComp)) : null,
        media_mes_janela_sem_pontuais: jan.length
          ? r2(finSoma(jan.map((j) => variavelAte(j, 31, setComp, true))) / jan.length)
          : null,
        fechamento_projetado: r2(ate + mediaDiaria * (diasNoMes - diaHoje)),
        fechamento_sem_pontuais: r2(mediaDiaria * diasNoMes),
      };
    }

    // Consumo e variável de cada dia (out/2026, meta diária da lifeos-cli):
    // as regras de consumoMes e de variavelAte (com pontuais) restritas ao
    // dia, sem regra nova. Um item por dia, de 1 ao último dia do mês; no
    // mês corrente, só até hoje. Por isso a soma dos dias é consumo_mes e
    // variavel_mes, exceto no mês corrente com lançamento de data futura:
    // ali a soma do variável é ritmo.variavel_ate_hoje.
    const ultimoDia = ym === mesHoje ? diaHoje : finLastDay(ym);
    const acc = Array.from({ length: ultimoDia }, () => ({ consumo: 0, variavel: 0 }));
    for (const m of rows) {
      const d = acc[finDia(m) - 1];
      if (!d) continue;
      const v = finNum(m.valor);
      if (finIsSaida(m) && !ehFatura(m)) d.consumo += v;
      else if (finIsEntrada(m) && ehRateio(m)) d.consumo -= v;
      if (finIsSaida(m) && !ehFatura(m) && !setComp.has(finChave(m.name))) d.variavel += v;
      else if (finIsEntrada(m) && ehRateio(m)) d.variavel -= v;
    }
    const porDia = acc.map((d, i) => ({
      data: `${ym}-${String(i + 1).padStart(2, "0")}`, consumo: r2(d.consumo), variavel: r2(d.variavel),
    }));

    let lista;
    if (opts.lista) {
      lista = {
        colunas: ["id", "data", "nome", "valor", "direcao", "meio", "marca"],
        linhas: rows.slice().sort(finCmpByDateAsc).map((m) => [
          m.id ?? null, m.date, m.name, r2(finNum(m.valor)),
          finIsEntrada(m) ? "Entrada" : finIsSaida(m) ? "Saida" : null,
          finMeioStr(m, meios),
          ehFatura(m) ? "fatura"
            : ehRateio(m) ? "rateio"
            : finIsSaida(m) && setComp.has(finChave(m.name)) ? "compromisso"
            : !c.isRealizado(m) ? "futuro"
            : null,
        ]),
      };
    }

    return {
      parcial: ym === mesHoje,
      futuro: ym > mesHoje,
      consumo_mes: r2(consumo),
      variavel_mes: r2(variavelAte(ym, 31, setComp)),
      entradas_proprias: r2(entradas - entradasRateio),
      entradas_por_nome: entradasPorNome,
      rateios,
      ...(porNome ? { por_nome: porNome } : {}),
      compromissos: {
        fonte: comp.fonte,
        total: comp.total,
        ...(comp.total_min !== undefined ? { total_min: comp.total_min, total_max: comp.total_max } : {}),
        pct_renda: renda.total > 0 ? r2(comp.total / renda.total * 100) : null,
        janela: comp.janela,
        janela_incompleta: comp.janela_incompleta,
        janela_emprestada_de: comp.janela_emprestada_de,
        itens: comp.itens.map(semChave),
        ...(comp.sugestoes ? { sugestoes: comp.sugestoes } : {}),
      },
      renda_recorrente: {
        fonte: renda.fonte,
        total: renda.total,
        ...(renda.total_min !== undefined ? { total_min: renda.total_min, total_max: renda.total_max } : {}),
        itens: renda.itens.map(semChave),
        ...(renda.sugestoes ? { sugestoes: renda.sugestoes } : {}),
      },
      sinais: {
        picos,
        tickets_repetidos: ticketsRepetidos,
        pontuais,
        consumo_base: r2(consumo - finSoma(pontuais.map((p) => p.valor))),
      },
      ...(ritmo ? { ritmo } : {}),
      por_dia: porDia,
      ...(lista ? { lista_movimentacoes: lista } : {}),
    };
  };

  // Uma linha por (nome, direção); média só dos meses completos.
  const comparativoPorNome = (meses: string[]) => {
    const acc: Record<string, { k: string; dir: string; v: Record<string, number> }> = {};
    for (const ym of meses) for (const m of rowsDoMes(ym)) {
      if (ehFatura(m)) continue;
      const dir = finIsEntrada(m) ? "Entrada" : finIsSaida(m) ? "Saida" : null;
      if (!dir) continue;
      const k = finChave(m.name);
      const a = (acc[k + "|" + dir] ??= { k, dir, v: {} });
      a.v[ym] = (a.v[ym] || 0) + finNum(m.valor);
    }
    const comps = meses.filter(completo);
    return Object.values(acc).map((a) => {
      const valores: Record<string, number> = {};
      for (const ym of meses) valores[ym] = r2(a.v[ym] ?? 0);
      return {
        nome: nomeDe(a.k), direcao: a.dir, valores,
        media: comps.length ? r2(finSoma(comps.map((ym) => a.v[ym] ?? 0)) / comps.length) : null,
      };
    }).sort((a, b) => ((b.media ?? -1) - (a.media ?? -1)));
  };

  // Os 2 meses seguintes ao corrente. Lançado (inclusive com data futura)
  // substitui o típico, nunca soma.
  const projecao = (aberturaHoje: number) => {
    const comp = recorrentes(mesHoje, false), renda = recorrentes(mesHoje, true);
    const setComp = new Set(comp.itens.map((i) => i.chave));
    const lancadoEm = (ym: string, i: Recorrente, entrada: boolean) => {
      let soma = 0, achou = false;
      for (const m of rowsDoMes(ym)) {
        if (!(entrada ? finIsEntrada(m) : finIsSaida(m)) || ehFatura(m) || finChave(m.name) !== i.chave) continue;
        soma += finNum(m.valor); achou = true;
      }
      return achou ? soma : null;
    };

    // Saldo estimado no fim do mês corrente.
    const rHoje = calcularResumoMes(rowsDoMes, mesHoje, aberturaHoje, hojeISO, meios);
    let futuros = 0;
    for (const m of rowsDoMes(mesHoje)) {
      if (c.isRealizado(m)) continue;
      if (finIsEntrada(m)) futuros += finNum(m.valor);
      if (c.isSaidaCaixa(m)) futuros -= finNum(m.valor);
    }
    // Cenários: com faixa cadastrada, o pessimista usa a saída no máximo e a
    // entrada no mínimo; o otimista, o contrário; o provável, o meio da faixa
    // (valor_tipico). Sem faixa os três coincidem. Lançado vale nos três.
    type Cenario = "provavel" | "pessimista" | "otimista";
    const val = (i: Recorrente, entrada: boolean, cen: Cenario) =>
      cen === "provavel" || i.valor_min === undefined
        ? i.valor_tipico
        : ((cen === "pessimista") === entrada ? i.valor_min! : i.valor_max!);
    const temFaixa = [...comp.itens, ...renda.itens].some((i) => i.valor_min !== undefined && i.valor_min !== i.valor_max);

    const faturaRestante = rHoje.fatura_deste_mes.restante;
    const jan = janela(mesHoje);
    const mediaDiaria = mediaDiariaJanela(jan, setComp);
    const diasRestantes = finLastDay(mesHoje) - diaHoje;
    const variavelEstimado = mediaDiaria * diasRestantes;
    const saldoFimDe = (cen: Cenario) => {
      const rendaPendente = finSoma(renda.itens.filter((i) => !i.ja_lancado).map((i) => val(i, true, cen)));
      const compPendentes = finSoma(comp.itens.filter((i) => !ehCredito(i) && !i.ja_lancado).map((i) => val(i, false, cen)));
      return {
        rendaPendente, compPendentes,
        saldoFim: rHoje.saldo + futuros + rendaPendente - compPendentes - faturaRestante - variavelEstimado,
      };
    };

    // A fatura restante de cada mês projetado não depende do cenário.
    const faturaRestanteEm: Record<string, number> = {};
    const restanteEm = (ym: string) =>
      faturaRestanteEm[ym] ??= calcularResumoMes(rowsDoMes, ym, 0, hojeISO, meios).fatura_deste_mes.restante;

    const mesProj = (ym: string, saldoInicial: number, cen: Cenario) => {
      type Linha = { grupo: string; nome: string; valor: number; origem: string; valor_min?: number; valor_max?: number };
      const detalhe: Linha[] = [];
      const add = (grupo: string, nome: string, lancado: number | null, tipico: number, i?: Recorrente) => {
        const valor = r2(lancado ?? tipico);
        const linha: Linha = { grupo, nome, valor, origem: lancado !== null ? "lancado" : "tipico" };
        if (lancado === null && i && i.valor_min !== undefined && i.valor_min !== i.valor_max) {
          linha.valor_min = i.valor_min; linha.valor_max = i.valor_max;
        }
        detalhe.push(linha);
        return valor;
      };
      let rendaPrev = 0, compCaixa = 0;
      for (const i of renda.itens) rendaPrev += add("renda", i.nome, lancadoEm(ym, i, true), val(i, true, cen), i);
      for (const i of comp.itens) if (!ehCredito(i)) compCaixa += add("compromisso_caixa", i.nome, lancadoEm(ym, i, false), val(i, false, cen), i);
      // Fatura: o restante da lógica existente (compras do mês anterior já
      // lançadas, menos pagamentos e adiantamentos) + compromissos no
      // Crédito do mês anterior que ainda não foram lançados.
      const pm = finPrevMonth(ym);
      let fatura = add("fatura", `Fatura (compras de ${pm})`, restanteEm(ym), 0);
      for (const i of comp.itens) if (ehCredito(i) && lancadoEm(pm, i, false) === null) fatura += add("fatura", i.nome, null, val(i, false, cen), i);
      const livre = saldoInicial + rendaPrev - compCaixa - fatura;
      const diasNoMes = finLastDay(ym);
      return {
        mes: ym,
        dias_no_mes: diasNoMes,
        saldo_inicial: r2(saldoInicial),
        renda_prevista: r2(rendaPrev),
        compromissos_caixa: r2(compCaixa),
        fatura_a_pagar: r2(fatura),
        livre_para_variavel: r2(livre),
        livre_por_dia: r2(livre / diasNoMes),
        detalhe,
      };
    };

    const p1 = finNextMonth(mesHoje), p2 = finNextMonth(p1);
    const prov = saldoFimDe("provavel");
    // Nos cenários, só o resumo de cada mês (o detalhe é o do provável).
    const cenario = (cen: Cenario) => {
      const s = saldoFimDe(cen);
      return {
        saldo_fim_do_mes: r2(s.saldoFim),
        meses: [mesProj(p1, s.saldoFim, cen), mesProj(p2, 0, cen)].map(({ detalhe: _, ...resto }) => resto),
      };
    };
    const fonte = comp.fonte === renda.fonte ? comp.fonte : `compromissos: ${comp.fonte}, renda: ${renda.fonte}`;
    return {
      base: mesHoje,
      fonte,
      janela: jan,
      janela_incompleta: comp.janela_incompleta,
      composicao_saldo_inicial: {
        saldo_hoje: r2(rHoje.saldo),
        lancamentos_futuros_do_mes: r2(futuros),
        renda_pendente: r2(prov.rendaPendente),
        compromissos_pendentes: r2(prov.compPendentes),
        fatura_restante: r2(faturaRestante),
        variavel_estimado: r2(variavelEstimado),
        saldo_fim_do_mes: r2(prov.saldoFim),
      },
      meses: [mesProj(p1, prov.saldoFim, "provavel"), mesProj(p2, 0, "provavel")],
      ...(temFaixa ? { cenarios: { pessimista: cenario("pessimista"), otimista: cenario("otimista") } } : {}),
      premissas: [
        comp.fonte === "cadastro" || renda.fonte === "cadastro"
          ? "Recorrências do cadastro (Finanças, Recorrências previstas) onde houver, por direção; o que a heurística acha e não está cadastrado vem em sugestoes, fora das contas."
          : `Sem recorrências cadastradas: compromissos e renda vêm da janela ${jan.join(", ")}, nomes presentes em todos esses meses, no máximo ${MAX_OCORRENCIAS_COMPROMISSO} vezes por mês.`,
        "Valor típico: no cadastro, o meio da faixa; na heurística, a renda do mês completo mais recente (reajuste é degrau) e a mediana da janela pros compromissos.",
        "O que já está lançado no mês (inclusive com data futura) substitui o valor típico, nunca soma os dois.",
        `saldo_inicial de ${p1} = saldo de hoje + lançamentos futuros de ${mesHoje} + renda recorrente ainda não lançada - compromissos de caixa ainda não lançados - restante da fatura de ${mesHoje} - gasto variável médio da janela sem pontuais (${r2(mediaDiaria)}/dia) nos ${diasRestantes} dias que faltam.`,
        `saldo_inicial de ${p2} = 0: supõe que o livre de ${p1} é gasto.`,
        "Compromissos no Crédito não saem do caixa no mês: entram na fatura do mês seguinte.",
        ...(temFaixa ? ["cenarios: pessimista = saídas no máximo e entradas no mínimo da faixa; otimista = o contrário. Os campos principais são o provável."] : []),
        "Lançamentos avulsos já agendados nos meses projetados não entram, exceto compras no Crédito, que já estão na fatura.",
      ],
    };
  };

  return { inicio, analisarMes, comparativoPorNome, projecao };
}

export { calcularResumoMes, criarAnalise, finNextMonth, finNum };
