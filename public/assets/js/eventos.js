/**
 * eventos.js — LifeOS · LifeOS · módulo Eventos (/eventos)
 *
 * JS cru, sem framework, sem build. Consumido por eventos.html. Módulo
 * isolado de Finanças — não importa nem é importado por financas.js. A
 * única coisa que compartilham é o backend (mesma senha mestre, mesmo
 * padrão de Edge Function) e, no LOCALSTORAGE, a chave da senha lembrada
 * ('financas_master') — de propósito, pra "lembrar" numa página valer nas
 * outras do LifeOS também.
 *
 * Calendário navegável por mês (prev/next, busca sob demanda por mês —
 * mesmo espírito de /financas) + linha do tempo (recentes/próximos,
 * sempre relativa a hoje) + criar/excluir evento.
 */
(function () {
  'use strict';

  /* ── Config ──────────────────────────────────────────────────── */
  /* URL derivada de lifeos-config.js — ver LIFEOS.md §2. */
  var EVENTOS_FN = (window.LIFEOS_CONFIG ? window.LIFEOS_CONFIG.supabaseUrl : '') + '/functions/v1/lifeos-eventos';
  var ANON_KEY = window.LIFEOS_CONFIG ? window.LIFEOS_CONFIG.anonKey : '';
  var LS_KEY = (window.LIFEOS_CONFIG && window.LIFEOS_CONFIG.sessionKey) || 'financas_master'; /* mesma chave de /financas — "lembrar" vale nas duas páginas */

  var TIPOS = ['faculdade', 'trabalho', 'lazer', 'vida', 'psicodelia'];
  var EVENTO_COR = {
    faculdade: '#5b8def', trabalho: '#c4913a', lazer: '#3fb98c',
    vida: '#e58b5b', psicodelia: '#b06ee0',
  };
  var MESES = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
    'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

  /* ── Estado ──────────────────────────────────────────────────── */
  var SESSION_PW = '';
  var EVENTOS = [];        /* eventos acumulados de todos os meses já buscados */
  var EVENTOS_LOADED = {}; /* ym -> true */
  var CAL_YM = '';         /* mês exibido no calendário (navegável) */
  var LOADING = false;

  /* ── Helpers ─────────────────────────────────────────────────── */
  function $(id) { return document.getElementById(id); }
  function todayYM() { var d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0'); }
  function todayISO() { var d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
  function nextMonth(ym) { var p = ym.split('-'); var y = +p[0], m = +p[1]; return (m === 12) ? ((y + 1) + '-01') : (y + '-' + String(m + 1).padStart(2, '0')); }
  function prevMonth(ym) { var p = ym.split('-'); var y = +p[0], m = +p[1]; return (m === 1) ? ((y - 1) + '-12') : (y + '-' + String(m - 1).padStart(2, '0')); }
  var DIAS_NO_MES = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
  function isBissexto(y) { return (y % 4 === 0 && y % 100 !== 0) || (y % 400 === 0); }
  function lastDayOfMonth(ym) { var p = ym.split('-'); var y = +p[0], m = +p[1]; return (m === 2 && isBissexto(y)) ? 29 : DIAS_NO_MES[m - 1]; }
  function monthLabel(ym) { var p = ym.split('-'); return MESES[(+p[1]) - 1] + ' ' + p[0]; }
  function fmtDate(d) { if (!d) return '—'; var p = d.split('-'); return p[2] + '/' + p[1]; }

  /* ── Dev mock (ambiente local) — mesmo motivo de /financas: CORS da Edge
     Function restringe a produção, login real é inalcançável em
     localhost/file://. Entra direto com eventos fictícios em memória. */
  var IS_LOCAL_DEV = (location.protocol === 'file:') ||
    /^(localhost|127\.0\.0\.1|\[::1\])$/.test(location.hostname);

  function seededRandom(seed) {
    var s = seed % 2147483647; if (s <= 0) s += 2147483646;
    return function () { s = (s * 16807) % 2147483647; return (s - 1) / 2147483646; };
  }
  function seedFromString(s) { var h = 0; for (var i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) | 0; return Math.abs(h) || 1; }
  function mockDelay(value) { return new Promise(function (resolve) { setTimeout(function () { resolve(value); }, 220); }); }

  var MOCK_EVENTOS = null;
  var MOCK_NAMES = ['Prova de Cálculo', 'Sessão', 'Reunião de equipe', 'Cinema com amigos', 'Consulta médica', 'Aniversário do Pai', 'Entrega do projeto', 'Trilha'];
  function seedMockEventos() {
    var rnd = seededRandom(seedFromString('eventos-seed'));
    var t = todayYM();
    var months = [prevMonth(t), t, t, t, nextMonth(t), nextMonth(t), nextMonth(nextMonth(t)), nextMonth(nextMonth(t))];
    var out = [];
    for (var i = 0; i < months.length; i++) {
      var ym = months[i];
      var day = 1 + Math.floor(rnd() * (lastDayOfMonth(ym) - 1));
      out.push({ id: 'mock-evt-' + i, name: MOCK_NAMES[i % MOCK_NAMES.length], date: ym + '-' + String(day).padStart(2, '0'), tipo: TIPOS[i % TIPOS.length] });
    }
    return out;
  }
  function mockQuery(from, to) {
    if (!MOCK_EVENTOS) MOCK_EVENTOS = seedMockEventos();
    return { ok: true, eventos: MOCK_EVENTOS.filter(function (e) { return e.date >= from && e.date <= to; }) };
  }
  function mockCreate(evento) {
    if (!MOCK_EVENTOS) MOCK_EVENTOS = seedMockEventos();
    var created = Object.assign({ id: 'mock-evt-new-' + Date.now() }, evento);
    MOCK_EVENTOS.push(created);
    return { ok: true, evento: created };
  }
  function mockDeleteFn(id) {
    if (MOCK_EVENTOS) { for (var i = 0; i < MOCK_EVENTOS.length; i++) { if (MOCK_EVENTOS[i].id === id) { MOCK_EVENTOS.splice(i, 1); break; } } }
    return { ok: true, id: id };
  }
  function showDevBadge() {
    var b = document.createElement('div');
    b.textContent = 'DEV · dados fictícios';
    b.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:2000;background:#c4913a;color:#14120f;' +
      "font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:0.12em;text-transform:uppercase;text-align:center;padding:4px 0;";
    document.body.appendChild(b);
  }

  /* ── Rede ────────────────────────────────────────────────────── */
  function apiQuery(pw, from, to) {
    if (IS_LOCAL_DEV) return mockDelay(mockQuery(from, to));
    return fetch(EVENTOS_FN, {
      method: 'POST',
      headers: { 'apikey': ANON_KEY, 'Authorization': 'Bearer ' + ANON_KEY, 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: pw, from: from, to: to }),
    }).then(function (res) {
      if (res.status === 401) return Promise.reject({ code: 'unauthorized' });
      if (!res.ok) return res.text().catch(function () { return ''; }).then(function (d) { return Promise.reject({ code: 'server', detail: res.status + ' ' + d }); });
      return res.json();
    }).then(function (j) {
      if (!j || !j.ok) return Promise.reject({ code: 'server', detail: (j && j.error) || 'resposta inválida' });
      return j;
    });
  }
  function apiCreate(pw, evento) {
    if (IS_LOCAL_DEV) return mockDelay(mockCreate(evento));
    return fetch(EVENTOS_FN, {
      method: 'POST',
      headers: { 'apikey': ANON_KEY, 'Authorization': 'Bearer ' + ANON_KEY, 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: pw, action: 'create', evento: evento }),
    }).then(function (res) {
      if (res.status === 401) return Promise.reject({ code: 'unauthorized' });
      if (!res.ok) return res.text().catch(function () { return ''; }).then(function (d) { return Promise.reject({ code: 'server', detail: res.status + ' ' + d }); });
      return res.json();
    }).then(function (j) {
      if (!j || !j.ok) return Promise.reject({ code: 'server', detail: (j && j.error) || 'resposta inválida' });
      return j;
    });
  }
  function apiDelete(pw, id) {
    if (IS_LOCAL_DEV) return mockDelay(mockDeleteFn(id));
    return fetch(EVENTOS_FN, {
      method: 'POST',
      headers: { 'apikey': ANON_KEY, 'Authorization': 'Bearer ' + ANON_KEY, 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: pw, action: 'delete', id: id }),
    }).then(function (res) {
      if (res.status === 401) return Promise.reject({ code: 'unauthorized' });
      if (!res.ok) return res.text().catch(function () { return ''; }).then(function (d) { return Promise.reject({ code: 'server', detail: res.status + ' ' + d }); });
      return res.json();
    }).then(function (j) {
      if (!j || !j.ok) return Promise.reject({ code: 'server', detail: (j && j.error) || 'resposta inválida' });
      return j;
    });
  }

  /* ── Calendário (navegação sob demanda por mês) ─────────────────── */
  function ensureMonth(ym) {
    if (EVENTOS_LOADED[ym]) return Promise.resolve();
    var from = ym + '-01';
    var to = ym + '-' + String(lastDayOfMonth(ym)).padStart(2, '0');
    return apiQuery(SESSION_PW, from, to).then(function (j) {
      EVENTOS_LOADED[ym] = true;
      (j.eventos || []).forEach(function (e) {
        if (!EVENTOS.some(function (x) { return x.id === e.id; })) EVENTOS.push(e);
      });
    }).catch(function (err) {
      if (err && err.code === 'unauthorized') onLogout();
    });
  }

  function goCalMonth(delta) {
    if (LOADING) return;
    CAL_YM = delta > 0 ? nextMonth(CAL_YM) : prevMonth(CAL_YM);
    renderCalendar();
    LOADING = true;
    ensureMonth(CAL_YM).then(function () { LOADING = false; renderCalendar(); });
  }

  function renderCalendar() {
    var labelEl = $('cal-label'), dowHost = $('mini-cal-dow'), gridHost = $('mini-cal');
    if (!gridHost) return;
    if (labelEl) labelEl.textContent = monthLabel(CAL_YM);
    if (dowHost && !dowHost.childElementCount) {
      ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'].forEach(function (d) { var s = document.createElement('span'); s.textContent = d; dowHost.appendChild(s); });
    }

    var parts = CAL_YM.split('-'); var y = +parts[0], m = +parts[1];
    var firstDow = new Date(y, m - 1, 1).getDay();
    var totalDays = lastDayOfMonth(CAL_YM);
    var isRealCurrentMonth = CAL_YM === todayYM();
    var todayDate = new Date().getDate();

    var byDay = {};
    EVENTOS.forEach(function (e) {
      if (!e.date || e.date.slice(0, 7) !== CAL_YM) return;
      var day = parseInt(e.date.slice(8, 10), 10);
      if (!byDay[day]) byDay[day] = [];
      byDay[day].push(e.tipo);
    });

    gridHost.innerHTML = '';
    for (var i = 0; i < firstDow; i++) {
      var empty = document.createElement('div'); empty.className = 'mini-cal-cell is-empty';
      gridHost.appendChild(empty);
    }
    for (var day = 1; day <= totalDays; day++) {
      var cell = document.createElement('div'); cell.className = 'mini-cal-cell';
      if (isRealCurrentMonth && day === todayDate) cell.classList.add('is-today');
      var numEl = document.createElement('span'); numEl.textContent = day;
      cell.appendChild(numEl);
      if (byDay[day]) {
        var dots = document.createElement('span'); dots.className = 'mini-cal-dots';
        byDay[day].slice(0, 4).forEach(function (tipo) {
          var dot = document.createElement('span'); dot.className = 'mini-cal-dot';
          dot.style.background = EVENTO_COR[tipo] || 'var(--mute)';
          dots.appendChild(dot);
        });
        cell.appendChild(dots);
      }
      gridHost.appendChild(cell);
    }
  }

  function renderLegend() {
    var host = $('cal-legend');
    if (!host || host.childElementCount) return; /* fixo, monta uma vez só */
    TIPOS.forEach(function (tipo) {
      var item = document.createElement('span'); item.className = 'cal-legend-item';
      var dot = document.createElement('span'); dot.className = 'cal-legend-dot'; dot.style.background = EVENTO_COR[tipo];
      item.appendChild(dot);
      item.appendChild(document.createTextNode(tipo));
      host.appendChild(item);
    });
  }

  /* ── Linha do tempo (recentes + próximos, relativa a hoje) ──────── */
  function renderTimeline() {
    var host = $('eventos-timeline');
    if (!host) return;
    host.innerHTML = '';
    var today = todayISO();
    var past = EVENTOS.filter(function (e) { return e.date < today; })
      .sort(function (a, b) { return (a.date || '').localeCompare(b.date || ''); }).slice(-4);
    var future = EVENTOS.filter(function (e) { return e.date >= today; })
      .sort(function (a, b) { return (a.date || '').localeCompare(b.date || ''); }).slice(0, 8);

    if (!past.length && !future.length) {
      var empty = document.createElement('div'); empty.className = 'list-empty'; empty.textContent = 'nenhum evento por perto — crie um com o botão + no topo';
      host.appendChild(empty);
      return;
    }

    function row(ev, isPast) {
      var r = document.createElement('div'); r.className = 'list-row' + (isPast ? ' is-past' : '');
      var d = document.createElement('span'); d.className = 'list-date'; d.textContent = fmtDate(ev.date);
      var n = document.createElement('span'); n.className = 'list-name'; n.textContent = ev.name;
      var tag = document.createElement('span'); tag.className = 'evt-tag';
      tag.textContent = ev.tipo;
      var cor = EVENTO_COR[ev.tipo] || 'var(--mute)';
      tag.style.color = cor; tag.style.borderColor = cor;
      var actions = document.createElement('span'); actions.className = 'row-actions';
      var delBtn = document.createElement('button');
      delBtn.type = 'button'; delBtn.className = 'row-action-btn row-action-danger';
      delBtn.setAttribute('data-id', ev.id); delBtn.setAttribute('aria-label', 'Excluir evento');
      delBtn.innerHTML = '<i class="fad fa-trash"></i>';
      actions.appendChild(delBtn);
      r.appendChild(d); r.appendChild(n); r.appendChild(tag); r.appendChild(actions);
      return r;
    }

    past.forEach(function (e) { host.appendChild(row(e, true)); });
    var divider = document.createElement('div'); divider.className = 'timeline-divider';
    var divLabel = document.createElement('span'); divLabel.textContent = 'hoje'; divider.appendChild(divLabel);
    host.appendChild(divider);
    future.forEach(function (e) { host.appendChild(row(e, false)); });
  }

  /* ── Excluir (🗑, hover-only): confirmação inline de dois cliques —
     mesmo padrão de /financas (ver FINANCAS.md / LIFEOS.md §3), cópia
     própria aqui porque os dois módulos são isolados por design. */
  var DELETE_PENDING = null;
  function resetDeletePending() {
    if (!DELETE_PENDING) return;
    clearTimeout(DELETE_PENDING.timeoutId);
    var btn = DELETE_PENDING.btn;
    if (btn && document.body.contains(btn)) { btn.classList.remove('confirming'); btn.disabled = false; btn.innerHTML = '<i class="fad fa-trash"></i>'; }
    DELETE_PENDING = null;
  }
  function confirmDelete(btn, key, run, onDone) {
    if (DELETE_PENDING && DELETE_PENDING.key === key && DELETE_PENDING.btn === btn) {
      clearTimeout(DELETE_PENDING.timeoutId);
      DELETE_PENDING = null;
      btn.disabled = true;
      btn.innerHTML = '<i class="fad fa-spinner-third fa-spin"></i>';
      run().then(function () { onDone(); }).catch(function (err) {
        btn.disabled = false; btn.classList.remove('confirming'); btn.innerHTML = '<i class="fad fa-trash"></i>';
        if (err && err.code === 'unauthorized') { onLogout(); return; }
        window.alert('erro ao excluir — ' + ((err && err.detail) || 'tente de novo'));
      });
      return;
    }
    resetDeletePending();
    btn.classList.add('confirming');
    btn.textContent = 'confirmar?';
    DELETE_PENDING = { key: key, btn: btn, timeoutId: setTimeout(resetDeletePending, 3000) };
  }
  function onDeleteEvento(btn, id) {
    confirmDelete(btn, 'evt:' + id, function () { return apiDelete(SESSION_PW, id); }, function () {
      for (var i = 0; i < EVENTOS.length; i++) { if (EVENTOS[i].id === id) { EVENTOS.splice(i, 1); break; } }
      renderCalendar();
      renderTimeline();
    });
  }

  /* ── Modal de criação ────────────────────────────────────────────── */
  function openEventoModal() {
    $('evento-nome').value = '';
    $('evento-date').value = todayISO();
    $('evento-tipo').value = 'vida';
    $('evento-error').textContent = '';
    setEventoSaving(false);
    $('evento-modal').classList.add('open');
    var ni = $('evento-nome'); if (ni) ni.focus();
  }
  function closeEventoModal() { $('evento-modal').classList.remove('open'); }
  function setEventoSaving(on) { $('evento-save').disabled = on; $('evento-save').textContent = on ? 'Salvando…' : 'Salvar'; }

  function onEventoSubmit(e) {
    e.preventDefault();
    var nome = $('evento-nome').value.trim();
    var d = $('evento-date').value;
    var tipo = $('evento-tipo').value;
    if (!nome) { $('evento-error').textContent = 'nome obrigatório'; return; }
    if (!d) { $('evento-error').textContent = 'data inválida'; return; }

    setEventoSaving(true);
    $('evento-error').textContent = '';
    apiCreate(SESSION_PW, { name: nome, date: d, tipo: tipo }).then(function (j) {
      EVENTOS.push(j.evento);
      var evYm = j.evento.date.slice(0, 7);
      EVENTOS_LOADED[evYm] = true;
      CAL_YM = evYm;
      closeEventoModal();
      renderCalendar();
      renderTimeline();
    }).catch(function (err) {
      setEventoSaving(false);
      if (err && err.code === 'unauthorized') { onLogout(); return; }
      $('evento-error').textContent = 'erro ao salvar — ' + ((err && err.detail) || 'tente de novo');
    });
  }

  /* ── Gate / boot ─────────────────────────────────────────────────── */
  function enterApp() { if (window.LIFEOS_BLOG) window.LIFEOS_BLOG.aplicar(); $('gate').hidden = true; $('gate-checking').hidden = true; $('app').hidden = false; }
  function showGateForm() { $('gate').hidden = false; $('gate-checking').hidden = true; $('gate-form').hidden = false; $('app').hidden = true; var i = $('gate-input'); if (i) i.focus(); }
  function setGateLoading(on) { $('gate-btn').disabled = on; $('gate-btn').textContent = on ? '…' : '→'; }
  function shake() { var f = $('gate-row'); f.classList.remove('shake'); void f.offsetWidth; f.classList.add('shake'); }

  function loadInitialWindow() {
    var t = todayYM();
    CAL_YM = t;
    var fromYm = prevMonth(t), toYm = nextMonth(nextMonth(t));
    var from = fromYm + '-01';
    var to = toYm + '-' + String(lastDayOfMonth(toYm)).padStart(2, '0');
    return apiQuery(SESSION_PW, from, to).then(function (j) {
      EVENTOS = j.eventos || [];
      EVENTOS_LOADED = {};
      [fromYm, t, nextMonth(t), toYm].forEach(function (ym) { EVENTOS_LOADED[ym] = true; });
    });
  }

  function authAndLoad(pw) {
    SESSION_PW = pw;
    return loadInitialWindow().then(function () {
      enterApp();
      renderCalendar(); renderLegend(); renderTimeline();
    });
  }

  function onSubmit(e) {
    e.preventDefault();
    var pw = $('gate-input').value.trim();
    if (!pw) return;
    setGateLoading(true);
    $('gate-error').textContent = '';
    authAndLoad(pw).then(function () {
      setGateLoading(false);
      if ($('gate-remember').checked) localStorage.setItem(LS_KEY, pw); else localStorage.removeItem(LS_KEY);
    }).catch(function (err) {
      setGateLoading(false);
      SESSION_PW = '';
      if (err && err.code === 'unauthorized') { shake(); $('gate-error').textContent = 'senha incorreta'; $('gate-input').value = ''; $('gate-input').focus(); }
      else $('gate-error').textContent = 'erro ao carregar — ' + ((err && err.detail) || 'tente de novo');
    });
  }

  function onLogout() {
    localStorage.removeItem(LS_KEY);
    SESSION_PW = ''; EVENTOS = []; EVENTOS_LOADED = {}; resetDeletePending(); closeEventoModal();
    $('gate-input').value = ''; $('gate-remember').checked = false; $('gate-error').textContent = '';
    showGateForm();
  }

  function boot() {
    if (IS_LOCAL_DEV) {
      showDevBadge();
      authAndLoad('local-dev').catch(function (err) {
        $('gate-checking').hidden = true;
        $('gate-error').textContent = 'mock local falhou — ' + ((err && err.detail) || 'ver console');
        showGateForm();
      });
      return;
    }
    var saved = localStorage.getItem(LS_KEY);
    if (!saved) { showGateForm(); return; }
    $('gate').hidden = false; $('gate-form').hidden = true; $('gate-checking').hidden = false;
    authAndLoad(saved).catch(function (err) {
      localStorage.removeItem(LS_KEY); $('gate-checking').hidden = true; showGateForm();
      if (err && err.code === 'unauthorized') $('gate-error').textContent = 'sessão expirada — entre novamente';
    });
  }

  function init() {
    $('gate-form').addEventListener('submit', onSubmit);
    $('logout-btn').addEventListener('click', onLogout);
    $('add-evento-btn').addEventListener('click', openEventoModal);
    $('cal-prev').addEventListener('click', function () { goCalMonth(-1); });
    $('cal-next').addEventListener('click', function () { goCalMonth(1); });
    $('evento-form').addEventListener('submit', onEventoSubmit);
    $('evento-cancel').addEventListener('click', closeEventoModal);
    $('evento-modal-close').addEventListener('click', closeEventoModal);
    $('evento-modal').addEventListener('click', function (e) { if (e.target === $('evento-modal')) closeEventoModal(); });
    document.addEventListener('click', function (e) {
      var btn = e.target.closest ? e.target.closest('.row-action-btn') : null;
      if (!btn) { resetDeletePending(); return; }
      onDeleteEvento(btn, btn.getAttribute('data-id'));
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && $('evento-modal').classList.contains('open')) { closeEventoModal(); return; }
      if ($('app').hidden || $('evento-modal').classList.contains('open')) return;
      if (e.key === 'ArrowLeft') goCalMonth(-1);
      if (e.key === 'ArrowRight') goCalMonth(1);
    });
    boot();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
}());
