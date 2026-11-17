// UiKit 2.55 demo host (/kit/focus-mode): a Beacon-like global search shortcut. Ctrl+K (Cmd+K) focuses the header
// search field, which focus mode keeps (keep: [search]), so the shortcut works in focus mode too.
(() => {
  document.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && !event.altKey && !event.shiftKey && event.key.toLowerCase() === 'k') {
      const search = document.getElementById('kit-focus-search');
      if (search) {
        event.preventDefault();
        search.focus();
      }
    }
  });
})();
