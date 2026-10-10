/*
 * LifeOS · docs-site — chrome de navegação compartilhado entre TODAS as
 * páginas: botão de voltar (injetado no .site-nav, que já é sticky) e botão
 * de voltar ao topo (fixed, canto inferior direito, só aparece ao rolar).
 * Importar com <script src="[caminho]/assets/site-chrome.js" defer></script>
 * — nada de markup extra precisa existir na página, os dois botões são
 * inseridos no DOM por aqui.
 */
(function () {
  'use strict';

  var ICON_BACK = '<svg width="13" height="13" viewBox="0 0 14 14" fill="none" aria-hidden="true">'
    + '<line x1="2" y1="7" x2="12" y2="7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>'
    + '<polyline points="6,2 2,7 6,12" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>'
    + '</svg>';

  var ICON_UP = '<svg width="13" height="13" viewBox="0 0 14 14" fill="none" aria-hidden="true">'
    + '<line x1="7" y1="12" x2="7" y2="2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>'
    + '<polyline points="3,6 7,2 11,6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>'
    + '</svg>';

  function initBackButton() {
    var nav = document.querySelector('.site-nav');
    var brand = nav && nav.querySelector('.brand');
    if (!nav || !brand) return;

    var wrapper = document.createElement('div');
    wrapper.className = 'site-nav-left';

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'nav-back-btn';
    btn.setAttribute('aria-label', 'Voltar');
    btn.innerHTML = ICON_BACK;

    btn.addEventListener('click', function () {
      try {
        var ref = document.referrer;
        if (ref && new URL(ref).origin === window.location.origin) {
          window.history.back();
          return;
        }
      } catch (e) { /* referrer malformado — cai no fallback abaixo */ }
      window.location.href = brand.getAttribute('href');
    });

    brand.parentNode.insertBefore(wrapper, brand);
    wrapper.appendChild(btn);
    wrapper.appendChild(brand);
  }

  function initScrollToTop() {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'scroll-top-btn';
    btn.setAttribute('aria-label', 'Voltar ao topo');
    btn.innerHTML = ICON_UP;
    document.body.appendChild(btn);

    function toggle() {
      if (window.scrollY > 400) btn.classList.add('visible');
      else btn.classList.remove('visible');
    }

    btn.addEventListener('click', function () {
      var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
    });

    window.addEventListener('scroll', toggle, { passive: true });
    toggle();
  }

  document.addEventListener('DOMContentLoaded', function () {
    initBackButton();
    initScrollToTop();
  });
})();
