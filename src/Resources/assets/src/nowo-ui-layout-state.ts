/**
 * Pre-paint layout state (2.48, `js/nowo-ui-layout-state.js`, opt-in): applies the open / collapsed state that
 * `nowo-ui-side-panel.js` and `nowo-ui-rail.js` remembered in localStorage (markup with `data-nowo-ui-side-panel-persist`
 * / `data-nowo-ui-rail-persist`, see nowo-ui-layout-state-core.ts) before the first paint, so a remembered panel or
 * rail never flashes in its server-rendered state.
 *
 * Load it as a blocking external script in <head> (`<script src="…/nowo-ui-layout-state.js"></script>`, no inline code:
 * CSP needs only the asset origin, or a nonce): it watches the parser until DOMContentLoaded and fixes each persisted
 * panel / rail as soon as it is parsed. With `defer` (or not at all) the deferred side-panel and rail scripts apply the
 * same state when they start, which can show the server state for one frame. A host store rendered on the server is
 * always better (no JavaScript, no flash) and needs neither.
 */

import { watchStoredStates } from './nowo-ui-layout-state-core';

declare global {
  interface Window {
    nowoUiLayoutState?: () => () => void;
  }
}

window.nowoUiLayoutState = () => watchStoredStates(document);
window.nowoUiLayoutState();
