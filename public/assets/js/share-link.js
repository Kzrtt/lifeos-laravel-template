/**
 * share-link.js — LifeOS
 *
 * Drop this script before </body> on any page to get:
 *   1. A fixed "copiar link" button (top-right) that copies the current
 *      URL with ?ref=share appended to the clipboard.
 *   2. When the page is opened with ?ref=share, all .back-link elements
 *      are hidden — the visitor stays isolated on the page.
 *
 * Expects the page to have a <button id="share-btn"> with a child
 * <span class="share-label"> already in the markup, and the CSS classes
 * .share-btn / .share-btn.copied defined in the page's <style>.
 *
 * Safe to include on pages that don't have #share-btn — the script
 * bails out silently.
 */
(function () {
  'use strict';

  // ── SHARED-VIEW MODE ────────────────────────────────────────────
  // If the page was opened via a shared link:
  //   1. Lock this pathname into sessionStorage so share-guard.js can
  //      redirect back if the visitor tries to navigate elsewhere.
  //   2. Hide back-navigation UI.
  if (new URLSearchParams(window.location.search).get('ref') === 'share') {
    sessionStorage.setItem('lifeos_shared_page', window.location.pathname);
    document.body.classList.add('shared-view');
  }

  // ── COPY BUTTON ─────────────────────────────────────────────────
  var btn   = document.getElementById('share-btn');
  var label = btn && btn.querySelector('.share-label');
  if (!btn) return;

  function onCopied() {
    label.textContent = 'copiado ✓';
    btn.classList.add('copied');
    setTimeout(function () {
      label.textContent = 'copiar link';
      btn.classList.remove('copied');
    }, 2200);
  }

  btn.addEventListener('click', function () {
    var url = new URL(window.location.href);
    url.searchParams.set('ref', 'share');

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url.toString()).then(onCopied);
    } else {
      // Fallback para navegadores sem Clipboard API
      var ta = document.createElement('textarea');
      ta.value = url.toString();
      ta.style.cssText = 'position:fixed;opacity:0;pointer-events:none';
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
      onCopied();
    }
  });
}());
