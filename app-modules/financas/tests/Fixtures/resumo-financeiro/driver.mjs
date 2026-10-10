import fs from 'node:fs';
import { calcularResumoMes, criarAnalise, finNextMonth, finNum } from './secao.ts';

const MEIOS = ["Crédito","Débito","Pix","Vale","Boleto"];

// ---- fixture determinística
let seed = 12345; const rnd = () => (seed = (seed * 1103515245 + 12345) & 0x7fffffff) / 0x7fffffff;
const uuid = (n) => `00000000-0000-4000-8000-${String(n).padStart(12,'0')}`;
let nid = 1;
const movs = [];
const pad = (n) => String(n).padStart(2,'0');
const add = (date, name, valor, tipo) => {
  const id = nid++;
  movs.push({ id: uuid(id), name, valor: Math.round(valor*100)/100, date, tipo, created_at: `${date}T${pad(8 + (id % 12))}:${pad(id % 60)}:00+00:00` });
};
const meses = ['2026-03','2026-04','2026-05','2026-06','2026-07','2026-08','2026-09','2026-10'];
const SEGURO = { '2026-06': 100, '2026-07': 100, '2026-08': 116, '2026-05': 100, '2026-04': 100, '2026-03': 100, '2026-09': 100, '2026-10': 100 };
for (const ym of meses) {
  add(`${ym}-06`, 'Seguro', SEGURO[ym], ['Saida','Débito']);
  add(`${ym}-07`, 'Academia', 80, ['Saida','Pix']); add(`${ym}-21`, 'Academia', 80, ['Saida','Pix']);
  for (const d of ['04','11','18']) add(`${ym}-${d}`, 'Terapia', 150, ['Saida','Pix']);
  add(`${ym}-05`, 'Salário', 3100, ['Entrada','Pix']);
  add(`${ym}-10`, 'Aluguel', 950 + Math.round(rnd()*40), ['Saida','Pix']);
  add(`${ym}-12`, 'Conta Luz', 90 + Math.round(rnd()*60), ['Saida','Boleto']);
  add(`${ym}-15`, 'Conta Internet', 99.9, ['Saida','Débito']);
  add(`${ym}-01`, 'Cartão', 210, ['Saida','Crédito']);
  add(`${ym}-20`, 'Netflix', 39.9, ['Saida','Crédito']);
  for (let i = 0; i < 24; i++) {
    const d = 1 + Math.floor(rnd()*28);
    const nomes = ['Mercado','Padaria','Uber','iFood','Farmácia','Posto Delta','Café'];
    const nome = nomes[Math.floor(rnd()*nomes.length)];
    const meio = ['Pix','Débito','Crédito','Crédito'][Math.floor(rnd()*4)];
    add(`${ym}-${pad(d)}`, nome, 8 + rnd()*90, ['Saida', meio]);
  }
  add(`${ym}-08`, 'Corre', 300, ['Saida','Crédito']);
  add(`${ym}-09`, 'Corre', 220, ['Entrada','Pix']);
  add(`${ym}-28`, 'Fatura Nubank', 800 + Math.round(rnd()*300), ['Saida']);
  if (ym === '2026-08') { add(`${ym}-22`, 'Peça limite', 200, ['Saida','Débito']); add(`${ym}-23`, 'Peça quase', 199.99, ['Saida','Débito']); for (const d of ['12','13','14']) add(`${ym}-${d}`, 'Lanche', 15, ['Saida','Pix']); add(`${ym}-18`, 'Conserto Carro', 1600, ['Saida','Débito']); add(`${ym}-19`, 'Adiantamento fatura', 300, ['Saida']); }
  if (ym === '2026-09') add(`${ym}-14`, 'Freela', 650.5, ['Entrada','Pix']);
  for (let k = 0; k < 4; k++) add(`${ym}-${pad(3 + k*6)}`, 'Café', 12.5, ['Saida','Pix']);
}
add('2026-09-25', 'Show', 180, ['Saida','Pix']);       // futuro (hoje 18)
add('2026-10-02', 'Dentista', 250, ['Saida','Crédito']);
add('2026-09-29', 'Salário extra', 400, ['Entrada','Pix']);
movs.sort((a,b)=> a.date.localeCompare(b.date) || a.created_at.localeCompare(b.created_at) || a.id.localeCompare(b.id));

const CAD_NENHUMA = [];
const CAD_FAIXA = [
  { nome: 'Aluguel', direcao: 'Saida', valor_min: 900, valor_max: 1000, meio: 'Pix', dia: 10 },
  { nome: 'Conta de Luz (variavel)', direcao: 'Saida', valor_min: 80, valor_max: 160, meio: null, dia: null },
  { nome: 'Salário', direcao: 'Entrada', valor_min: 3000, valor_max: 3200, meio: 'Pix', dia: 5 },
];

function saldoAbertura(before) {
  let c = 0;
  for (const m of movs) { if (m.date >= before) continue;
    const e = m.tipo.includes('Entrada'), s = m.tipo.includes('Saida');
    const v = Math.round(m.valor*100);
    if (e) c += v; else if (s && !m.tipo.includes('Crédito')) c -= v; }
  return c/100;
}

// replica handleResumoFinanceiro (index.ts) com dados em memória
function resumo(args, hoje, cadastradas) {
  const reYM = /^\d{4}-(0[1-9]|1[0-2])$/;
  const mesHoje = hoje.slice(0, 7);
  const de = String(args?.de ?? args?.mes ?? mesHoje);
  const ate = String(args?.ate ?? args?.mes ?? de);
  const incluirMov = args?.incluir_movimentacoes === true || args?.incluir_movimentacoes === "true";
  const meses = [];
  for (let ym = de; ym <= ate; ym = finNextMonth(ym)) meses.push(ym);
  const limite = finNextMonth(finNextMonth(finNextMonth(ate > mesHoje ? ate : mesHoje))) + "-01";
  const todas = movs.filter(m => m.date < limite).map(m => ({ ...m, valor: finNum(m.valor), tipo: m.tipo ?? [] }));
  const porMes = {};
  for (const m of todas) (porMes[(m.date || "").slice(0, 7)] ??= []).push(m);
  const rowsDoMes = (ym) => porMes[ym] ?? [];
  const mesesAbertura = meses.includes(mesHoje) ? meses : [...meses, mesHoje];
  const aberturas = mesesAbertura.map(ym => saldoAbertura(`${ym}-01`));
  const intervalo = meses.length > 1;
  const A = criarAnalise(rowsDoMes, todas, hoje, MEIOS, cadastradas);
  const resumos = meses.map((ym, i) => ({
    ...calcularResumoMes(rowsDoMes, ym, aberturas[i], hoje, MEIOS),
    ...A.analisarMes(ym, { porNome: !intervalo, lista: incluirMov }),
  }));
  const comparativo = intervalo ? resumos.map((x) => ({
    mes: x.mes, entradas: x.entradas, saidas_caixa: x.saidas_caixa, saidas_com_credito: x.saidas_com_credito,
    variacao_mes: x.variacao_mes, saldo: x.saldo, fatura_deste_mes: x.fatura_deste_mes.total,
    parcial: x.parcial, futuro: x.futuro, consumo_mes: x.consumo_mes, entradas_proprias: x.entradas_proprias,
  })) : undefined;
  const comparativoPorNome = intervalo ? A.comparativoPorNome(meses) : undefined;
  const projecao = A.projecao(aberturas[mesesAbertura.indexOf(mesHoje)]);
  return JSON.stringify({ hoje, inicio_dados: A.inicio, comparativo, comparativo_por_nome: comparativoPorNome, projecao, meses: resumos }, null, 2);
}

const cenarios = [
  { nome: 'mes_corrente', hoje: '2026-09-18', args: {}, cad: CAD_NENHUMA },
  { nome: 'intervalo_jun_set', hoje: '2026-09-18', args: { de: '2026-06', ate: '2026-09' }, cad: CAD_NENHUMA },
  { nome: 'cadastro_ago_com_lista', hoje: '2026-09-18', args: { mes: '2026-08', incluir_movimentacoes: true }, cad: CAD_FAIXA },
  { nome: 'cadastro_corrente', hoje: '2026-09-18', args: {}, cad: CAD_FAIXA },
  { nome: 'mes_passado_unico', hoje: '2026-09-18', args: { mes: '2026-07' }, cad: CAD_NENHUMA },
  { nome: 'ultimo_dia_do_mes', hoje: '2026-09-30', args: { mes: '2026-09' }, cad: CAD_NENHUMA },
  { nome: 'inicio_dos_dados', hoje: '2026-04-20', args: { de: '2026-03', ate: '2026-04' }, cad: CAD_NENHUMA },
];
const out = { movimentacoes: movs, cenarios: [] };
for (const c of cenarios) out.cenarios.push({ ...c, esperado: resumo(c.args, c.hoje, c.cad) });
fs.writeFileSync('fixture.json', JSON.stringify(out, null, 2));
console.log(movs.length, 'movs;', cenarios.length, 'cenarios');
for (const c of out.cenarios) console.log(c.nome, c.esperado.length);
