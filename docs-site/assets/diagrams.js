/* LifeOS · docs-site — renderiza os blocos <pre class="mermaid"> com a paleta
 * do site (tokens de site.css). Mermaid vem do CDN só se houver diagrama. */
(function () {
  'use strict';
  if (!document.querySelector('.mermaid')) return;
  var s = document.createElement('script');
  s.src = 'https://cdn.jsdelivr.net/npm/mermaid@10.9.0/dist/mermaid.min.js';
  s.onload = function () {
    var cs = getComputedStyle(document.documentElement);
    function tok(n, fb) { return cs.getPropertyValue(n).trim() || fb; }
    var dark = !window.matchMedia('(prefers-color-scheme: light)').matches;
    mermaid.initialize({
      startOnLoad: false,
      theme: 'base',
      themeVariables: {
        darkMode: dark,
        background: tok('--surface', '#0e1013'),
        primaryColor: tok('--surface-2', '#15181c'),
        primaryTextColor: tok('--ink', '#f0f2f5'),
        primaryBorderColor: tok('--accent', '#57b8c4'),
        lineColor: tok('--ink-faint', '#5a636e'),
        secondaryColor: tok('--surface-2', '#15181c'),
        tertiaryColor: tok('--surface', '#0e1013'),
        edgeLabelBackground: tok('--surface', '#0e1013'),
        fontFamily: tok('--font-mono', 'monospace'),
        fontSize: '13px'
      },
      er: { useMaxWidth: true },
      flowchart: { htmlLabels: true, curve: 'basis' }
    });
    mermaid.run({ querySelector: '.mermaid' });
  };
  document.head.appendChild(s);
})();
