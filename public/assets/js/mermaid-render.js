/* mermaid-render.js — diagramas Mermaid nas notas (telas + PDF)
 * ──────────────────────────────────────────────────────────────────────────
 *
 * O markdown das notas passa por marked, que entrega um bloco ```mermaid como
 * `<pre><code class="language-mermaid">`. Este script troca cada um desses
 * blocos pelo SVG do diagrama.
 *
 *   - TELAS: um MutationObserver cobre qualquer container onde o HTML da nota
 *     for injetado (hub, notas.html, tarefas.html, pré-visualização do editor)
 *     — nenhum ponto de render precisa chamar nada. Paleta lida dos tokens CSS
 *     do tema ativo (--surface, --text, --gold...), com darkMode pela
 *     luminância de --bg.
 *   - PDF: `LifeosMermaid.renderIn(el, { print: true })` devolve uma Promise;
 *     quem imprime deve esperá-la antes de window.print(). Usa o tema claro
 *     padrão do Mermaid (papel branco), e o observer ignora #pdf-export-root.
 *
 * A biblioteca vem do CDN só na primeira vez que aparece um diagrama. Sintaxe
 * inválida (comum enquanto se digita no editor) deixa o bloco de código como
 * está, sem erro no console nem lixo no DOM.
 */
(function () {
  'use strict';

  var MERMAID_SRC = 'https://cdn.jsdelivr.net/npm/mermaid@10.9.0/dist/mermaid.min.js';
  var SELECTOR = 'pre > code.language-mermaid';

  var libPromise = null;
  var queue = Promise.resolve();   /* mermaid.initialize é global: renderiza um lote por vez */
  var seq = 0;

  function injectStyle() {
    var st = document.createElement('style');
    st.textContent =
      '.mermaid-diagram{display:flex;justify-content:center;margin:1em 0;overflow-x:auto}' +
      '.mermaid-diagram svg{max-width:100%;height:auto}' +
      '@media print{.mermaid-diagram{overflow:visible;break-inside:avoid;page-break-inside:avoid}}';
    document.head.appendChild(st);
  }

  function loadLib() {
    if (window.mermaid) return Promise.resolve(window.mermaid);
    if (!libPromise) {
      libPromise = new Promise(function (resolve, reject) {
        var s = document.createElement('script');
        s.src = MERMAID_SRC;
        s.onload = function () { resolve(window.mermaid); };
        s.onerror = function () { libPromise = null; reject(new Error('mermaid indisponível')); };
        document.head.appendChild(s);
      });
    }
    return libPromise;
  }

  function isDarkBg(bg) {
    var m = /^#?([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(bg || '');
    if (!m) return true;
    var h = m[1].length === 3 ? m[1].replace(/./g, '$&$&') : m[1];
    var lum = (0.299 * parseInt(h.slice(0, 2), 16) + 0.587 * parseInt(h.slice(2, 4), 16) + 0.114 * parseInt(h.slice(4, 6), 16)) / 255;
    return lum < 0.5;
  }

  function configure(mermaid, print) {
    var base = { startOnLoad: false, securityLevel: 'strict', flowchart: { htmlLabels: true } };
    if (print) {
      base.theme = 'default';
    } else {
      var cs = getComputedStyle(document.documentElement);
      var tok = function (n, fb) { return cs.getPropertyValue(n).trim() || fb; };
      var surface = tok('--surface', '#1c1a16'), surface2 = tok('--surface-2', '#242018');
      base.theme = 'base';
      base.themeVariables = {
        darkMode: isDarkBg(tok('--bg', '#14120f')),
        background: surface,
        primaryColor: surface2,
        primaryTextColor: tok('--text', '#ede8df'),
        primaryBorderColor: tok('--gold', '#c4913a'),
        lineColor: tok('--mute', '#6b6456'),
        secondaryColor: surface2,
        tertiaryColor: surface,
        clusterBkg: tok('--bg', '#14120f'),
        clusterBorder: tok('--border-mid', '#3a352d'),
        edgeLabelBackground: surface,
        titleColor: tok('--dim', '#b0a898'),
        fontFamily: tok('--mono', 'monospace'),
        fontSize: '14px',
      };
    }
    mermaid.initialize(base);
  }

  /* Fora do modo print, blocos dentro de #pdf-export-root ficam de fora: eles
     só podem ser renderizados com o tema claro (ver renderIn print:true). */
  function pendingBlocks(root, print) {
    return Array.prototype.slice.call(root.querySelectorAll(SELECTOR)).filter(function (c) {
      return print || !c.closest('#pdf-export-root');
    });
  }

  function renderBlock(mermaid, code) {
    var pre = code.parentNode;
    if (!pre || !pre.parentNode) return Promise.resolve();
    var src = code.textContent;
    return Promise.resolve(mermaid.parse(src, { suppressErrors: true })).then(function (ok) {
      if (!ok) return;
      return mermaid.render('lifeos-mmd-' + (++seq), src).then(function (out) {
        if (!pre.parentNode) return;
        var box = document.createElement('div');
        box.className = 'mermaid-diagram';
        box.setAttribute('data-mermaid-src', src);
        box.innerHTML = out.svg;
        pre.parentNode.replaceChild(box, pre);
      });
    }).catch(function () { /* mantém o bloco de código */ }).then(function () {
      var junk = document.getElementById('dlifeos-mmd-' + seq);
      if (junk && junk.parentNode) junk.parentNode.removeChild(junk);
    });
  }

  function renderIn(root, opts) {
    var print = !!(opts && opts.print);
    if (!root || !pendingBlocks(root, print).length) return Promise.resolve();
    queue = queue.then(function () {
      return loadLib().then(function (mermaid) {
        var blocks = pendingBlocks(root, print);
        if (!blocks.length) return;
        configure(mermaid, print);
        return blocks.reduce(function (p, code) {
          return p.then(function () { return renderBlock(mermaid, code); });
        }, Promise.resolve());
      });
    }).catch(function () { /* CDN fora do ar: segue com o código à mostra */ });
    return queue;
  }

  var timer = null;
  function schedule() {
    clearTimeout(timer);
    timer = setTimeout(function () {
      renderIn(document.body);
    }, 300);   /* debounce: o editor re-renderiza a cada tecla */
  }

  function init() {
    injectStyle();
    new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true });
    schedule();
  }

  window.LifeosMermaid = { renderIn: renderIn };
  if (document.body) init(); else document.addEventListener('DOMContentLoaded', init);
})();
