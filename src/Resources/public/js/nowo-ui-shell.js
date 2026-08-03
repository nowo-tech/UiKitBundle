/**
 * App shell chrome: burger / left aside toggle + backdrop.
 * Expects .nowo-ui-shell root with [data-nowo-ui-shell].
 */
(function () {
  'use strict';

  function shellRoot(from) {
    return from.closest('[data-nowo-ui-shell]') || document.querySelector('[data-nowo-ui-shell]');
  }

  function setExpanded(btn, open) {
    if (btn) {
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
  }

  function isDesktop() {
    return window.matchMedia('(min-width: 901px)').matches;
  }

  function toggleAside(shell, forceOpen) {
    if (!shell) {
      return;
    }
    var btn = shell.querySelector('[data-nowo-ui-burger]');
    if (isDesktop()) {
      var collapsed = typeof forceOpen === 'boolean' ? !forceOpen : !shell.classList.contains('is-aside-collapsed');
      shell.classList.toggle('is-aside-collapsed', collapsed);
      shell.classList.remove('is-aside-open-mobile');
      document.body.classList.remove('nowo-ui-aside-open');
      setExpanded(btn, !collapsed);
      return;
    }
    var open = typeof forceOpen === 'boolean' ? forceOpen : !shell.classList.contains('is-aside-open-mobile');
    shell.classList.toggle('is-aside-open-mobile', open);
    document.body.classList.toggle('nowo-ui-aside-open', open);
    setExpanded(btn, open);
  }

  document.addEventListener('click', function (event) {
    var burger = event.target.closest('[data-nowo-ui-burger]');
    if (burger) {
      event.preventDefault();
      toggleAside(shellRoot(burger));
      return;
    }

    var backdrop = event.target.closest('[data-nowo-ui-aside-backdrop]');
    if (backdrop) {
      toggleAside(shellRoot(backdrop), false);
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') {
      return;
    }
    var shell = document.querySelector('[data-nowo-ui-shell].is-aside-open-mobile');
    if (shell) {
      toggleAside(shell, false);
    }
  });

  window.nowoUiToggleAside = toggleAside;
})();
