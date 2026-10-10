/**
 * Alternância "regra de negócio" × "código real" — convenção padrão de TODA
 * página em docs-site/features/*.html (fixada em v0.5.0, depois que a
 * primeira página de feature virou reclamação de "carregada demais de
 * código"). Uma página nova que queira esse padrão só precisa:
 *
 *   1. incluir este script (defer) + <div class="view-switch" role="group">
 *      com dois <button data-view="business">/<button data-view="code">
 *      — copie o markup de features/credenciamento-carteirinha.html;
 *   2. marcar cada bloco de conteúdo com a classe .view-business OU
 *      .view-code — nunca as duas coisas escondidas atrás de uma coisa só,
 *      o HTML contém as duas versões sempre, é a visibilidade que alterna
 *      (ver a regra [data-view] em assets/site.css);
 *   3. escrever o texto de negócio em prosa comum, sem nome de classe/
 *      endpoint/JSON — se precisa de "código" pra fazer sentido, pertence
 *      à visão de código, não à de negócio.
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'lifeos-docs-view';

  function initViewSwitch() {
    var switchEl = document.querySelector('.view-switch');
    if (!switchEl) return;

    var buttons = Array.prototype.slice.call(switchEl.querySelectorAll('button[data-view]'));
    if (!buttons.length) return;

    function setView(view) {
      document.body.setAttribute('data-view', view);
      buttons.forEach(function (btn) {
        var isActive = btn.getAttribute('data-view') === view;
        btn.classList.toggle('active', isActive);
        btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      });
      try {
        localStorage.setItem(STORAGE_KEY, view);
      } catch (e) {
        // localStorage indisponível (modo privado etc.) — a escolha só não
        // sobrevive entre páginas; a alternância em si continua funcionando.
      }
    }

    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        setView(btn.getAttribute('data-view'));
      });
    });

    var saved = null;
    try {
      saved = localStorage.getItem(STORAGE_KEY);
    } catch (e) {
      saved = null;
    }
    setView(saved === 'code' ? 'code' : 'business');
  }

  document.addEventListener('DOMContentLoaded', initViewSwitch);
})();
