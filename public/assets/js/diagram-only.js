/**
 * diagram-only.js — LifeOS
 *
 * Drop this script before </body> on any page that has .mermaid diagrams
 * to get a fixed toggle button (top-left) that hides all text content,
 * keeping only <header>, <nav>, and diagram containers.
 *
 * Self-contained: no jQuery, no extra markup, no CSS file needed.
 * Bails silently on pages with no .mermaid elements.
 *
 * Handles two structural patterns:
 *   • Direct body children (fichamento-mestre, trajetoria-dev):
 *     hides non-structural, non-diagram siblings; containers that
 *     hold both diagrams and text are kept but their text
 *     sub-elements are hidden recursively.
 *   • .wrap container (sistema-operacional): uses .wrap as the content
 *     root, keeping <header>/<nav> and recursing into any child that
 *     contains a .mermaid to strip out its text children.
 *
 * CSS variables used (with hardcoded fallbacks):
 *   --bg, --bg-surface, --border-mid, --text-mute,
 *   plus per-page accent vars if present
 */
(function () {
  'use strict';

  if (!document.querySelector('.mermaid')) return;
  if (document.getElementById('ps-diagram-toggle')) return;

  /* ── CSS ──────────────────────────────────────────────────────── */
  var style = document.createElement('style');
  style.textContent = [
    '#ps-diagram-toggle {',
    '  position: fixed;',
    '  top: 1.1rem;',
    '  left: 1.4rem;',
    '  z-index: 150;',
    '  font-family: "JetBrains Mono", monospace;',
    '  font-size: 0.57rem;',
    '  letter-spacing: 0.14em;',
    '  text-transform: uppercase;',
    '  color: var(--text-mute, #5A5249);',
    '  background: var(--bg-surface, var(--bg, #0D0D0F));',
    '  border: 1px solid var(--border-mid, #252230);',
    '  padding: 0.28rem 0.65rem;',
    '  cursor: pointer;',
    '  display: flex;',
    '  align-items: center;',
    '  gap: 0.38rem;',
    '  transition: color 0.18s, border-color 0.18s;',
    '}',
    '#ps-diagram-toggle:hover {',
    '  color: var(--azure, var(--steel, var(--reflex, #8BAEC4)));',
    '  border-color: var(--azure-dim, var(--steel-dim, var(--reflex-deep, #2A3A50)));',
    '}',
    '#ps-diagram-toggle.ps-active {',
    '  color: var(--teal, var(--moss, var(--reflex, #78C4A8)));',
    '  border-color: var(--teal-dim, var(--moss-dim, var(--reflex-deep, #183C30)));',
    '}',
  ].join('\n');
  document.head.appendChild(style);

  /* ── BUTTON ───────────────────────────────────────────────────── */
  var btn = document.createElement('button');
  btn.id = 'ps-diagram-toggle';
  btn.setAttribute('aria-label', 'Mostrar apenas diagramas');
  btn.innerHTML =
    '<svg width="11" height="11" viewBox="0 0 12 12" fill="none" aria-hidden="true">' +
      '<rect x="0.6" y="0.6" width="4.4" height="4.4" stroke="currentColor" stroke-width="1.1" rx="0.3"/>' +
      '<rect x="7" y="0.6" width="4.4" height="4.4" stroke="currentColor" stroke-width="1.1" rx="0.3"/>' +
      '<rect x="0.6" y="7" width="4.4" height="4.4" stroke="currentColor" stroke-width="1.1" rx="0.3"/>' +
      '<rect x="7" y="7" width="4.4" height="4.4" stroke="currentColor" stroke-width="1.1" rx="0.3"/>' +
    '</svg>' +
    '<span id="ps-dt-label">só diagramas</span>';
  document.body.appendChild(btn);

  /* ── TOGGLE LOGIC ─────────────────────────────────────────────── */

  // Páginas que envolvem todo o conteúdo num .wrap (ex: sistema-operacional)
  function findContentRoot() {
    var wrap = document.querySelector('.wrap');
    if (wrap && wrap.querySelector('header')) return wrap;
    return document.body;
  }

  // Elementos de overlay visual fixos — nunca esconder
  function isOverlay(el) {
    return el.classList.contains('grain') || el.classList.contains('scanlines');
  }

  // Elemento é estrutural de navegação — nunca esconder
  function isStructural(el) {
    var tag = el.tagName;
    return tag === 'HEADER' || tag === 'NAV';
  }

  // Elemento contém algum diagrama mermaid
  function hasDiagram(el) {
    return !!el.querySelector('.mermaid');
  }

  // Elemento é container direto de diagrama (não esconder)
  function isDiagramContainer(el) {
    return (
      el.classList.contains('diagram-section') ||
      el.classList.contains('diagram-frame') ||
      el.classList.contains('mermaid') ||
      el.classList.contains('section-label')
    );
  }

  // Títulos de seção — mantidos mesmo no modo diagrama
  var HEADING_TAGS = { H1: true, H2: true, H3: true, H4: true };

  function isHeading(el) {
    return !!HEADING_TAGS[el.tagName];
  }

  /**
   * Dentro de um container que TEM diagramas, coleta recursivamente
   * todos os filhos que são puramente texto corrido (sem .mermaid descendente)
   * para serem escondidos. Títulos (h1–h4) são preservados.
   */
  function collectTextInContainer(container, result) {
    Array.prototype.forEach.call(container.children, function (child) {
      // Containers de diagrama → manter inteiros
      if (isDiagramContainer(child)) return;

      // Títulos de seção → manter
      if (isHeading(child)) return;

      // Filho que também tem diagrama dentro → descer mais um nível
      if (hasDiagram(child)) {
        collectTextInContainer(child, result);
        return;
      }

      // Sem diagrama → é texto/conteúdo → esconder
      result.push(child);
    });
  }

  var saved = []; // [{ el: Element, display: string }, ...]
  var active = false;

  function collectHideables() {
    var result = [];
    var root = findContentRoot();

    Array.prototype.forEach.call(root.children, function (el) {
      if (el === btn) return;
      if (el.id === 'back-to-top') return;
      if (isOverlay(el)) return;
      if (isStructural(el)) return;

      // Container puro de diagrama → manter inteiro
      if (isDiagramContainer(el)) return;

      // Tem diagrama dentro → manter o wrapper, esconder apenas texto interno
      if (hasDiagram(el)) {
        collectTextInContainer(el, result);
        return;
      }

      // Sem diagrama nenhum → esconder o elemento inteiro
      result.push(el);
    });

    return result;
  }

  btn.addEventListener('click', function () {
    active = !active;
    var label = document.getElementById('ps-dt-label');

    if (active) {
      saved = collectHideables().map(function (el) {
        var prev = el.style.display;
        el.style.display = 'none';
        return { el: el, display: prev };
      });
      btn.classList.add('ps-active');
      btn.setAttribute('aria-label', 'Mostrar todo o conteúdo');
      label.textContent = 'ver tudo';
    } else {
      saved.forEach(function (item) {
        item.el.style.display = item.display;
      });
      saved = [];
      btn.classList.remove('ps-active');
      btn.setAttribute('aria-label', 'Mostrar apenas diagramas');
      label.textContent = 'só diagramas';
    }
  });
}());
