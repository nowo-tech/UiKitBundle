// UiKit 2.42 demo host (/kit/mobile): a Beacon-like drawer whose edge tab opens and closes it. The host keeps
// aria-expanded on the tab; the edge tabs launcher never folds a tab with aria-expanded="true" (MUX-01).
(() => {
  const drawer = document.getElementById('kit-mobile-drawer');
  const tab = drawer?.querySelector('.nowo-ui-edge-tab__link');
  if (!drawer || !tab) {
    return;
  }
  const set = (open) => {
    drawer.toggleAttribute('data-open', open);
    tab.setAttribute('aria-expanded', open ? 'true' : 'false');
    tab.setAttribute('aria-label', open ? 'Close developer notes' : 'Developer notes');
  };
  tab.addEventListener('click', () => set(tab.getAttribute('aria-expanded') !== 'true'));
})();
