// UiKit 2.35 demo host: logs the sortable events, refuses one card, and reverts another as if its write had failed.
// A real host does its fetch() on `moved` and calls detail.revert() when the answer is not ok.
(() => {
  const log = document.querySelector('[data-testid="log"]');
  const write = (text) => {
    const row = document.createElement('li');
    row.textContent = text;
    log.append(row);
  };
  for (const type of ['start', 'move', 'moved', 'cancel', 'reverted']) {
    document.addEventListener(`nowo-ui:sortable:${type}`, (event) => {
      const { scope, id, listId, fromListId, index, fromIndex, source, reason } = event.detail;
      write([type, scope, id, listId, fromListId, index, fromIndex, source, reason].filter((part) => part !== undefined).join(' '));
    });
  }
  document.addEventListener('nowo-ui:sortable:move', (event) => {
    if (event.detail.item.hasAttribute('data-demo-refuse')) {
      event.preventDefault();
    }
  });
  document.addEventListener('nowo-ui:sortable:moved', (event) => {
    if (event.detail.item.hasAttribute('data-demo-fail')) {
      window.setTimeout(() => event.detail.revert(), 150);
    }
  });
  // A colour set through the CSSOM, as TaskBoard does for its columns: the dragged copy must keep it, with no CSP report.
  document.querySelectorAll('.kit-sortable-card').forEach((card) => card.style.setProperty('--kit-card-accent', '#2563eb'));
})();
