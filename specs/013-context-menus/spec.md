# Feature Specification: Context menus from the kebab

**Feature Branch**: `feat/context-menu`  
**Created**: 2026-11-17  
**Status**: Implemented (target 2.54.0)  
**Input**: Beacon request, approved by the owner: a generic context-menu primitive in UiKit that TaskBoard, ChatKit, Wiki and other
bundles consume next, so the API (attributes, theme flag, events) is stable and documented.

**Related docs**: [`docs/USAGE.md`](../../docs/USAGE.md#context-menus), [`docs/ROW_ACTIONS.md`](../../docs/ROW_ACTIONS.md#context-menu-254),
[`docs/THEME_SLOTS.md`](../../docs/THEME_SLOTS.md), [`docs/UPGRADING.md`](../../docs/UPGRADING.md#unreleased)

---

## Summary

A right-click, the ContextMenu key, Shift+F10 or a long press on a row or card opens the items of the kebab / row-action menu that
row or card already renders. Opt-in runtime `context-menu`; theme flag `theme.context_menus`; partial option `context_menu`; host
attribute `data-nowo-ui-context-menu`. Without the opt-in nothing changes.

## Requirements

- **FR-CTXMENU-ITEMS-001** — The context menu shows only the kebab's own entries (links, buttons, submit buttons, separators, nested
  menus as submenus; never the toggle nor `<dialog>` content), with their label (`aria-label` or text), icon, danger and disabled
  state. Hidden, `inert`, `aria-hidden` and unrendered entries are skipped; `:disabled` and `fieldset[disabled]` controls are
  disabled. Choosing one forwards `click()` to the kebab entry: no new item, no new authorization surface, POST / confirm / dialog /
  Stimulus behaviour unchanged. A kebab without entries gets no context menu.
- **FR-CTXMENU-HOST-001** — Host resolution by delegation (dynamic rows work): `[data-nowo-ui-context-menu]` (empty = first
  kebab / menu / row actions inside; a value = the id of one or of a panel inside one, never any other element; `off` = native
  menu), else the nearest
  `[data-nowo-ui-context-menu-scope]`, `tr`, `[role="row"]` or `[data-nowo-ui-card]` owning a `[data-nowo-ui-context-menu-source]`.
- **FR-CTXMENU-OPTIN-001** — Default off. `theme.context_menus: true` makes the `kebab` and `row_actions` partials print
  `data-nowo-ui-context-menu-source` and `data-nowo-ui-context-menu-label` (translation `context_menu.label`, 7 locales) and the
  `scripts` partial append `context-menu`; the `context_menu` option of `kebab`, `row_actions` and `menu` (the menu partial: option
  only) wins. The runtime is not in `nowo_ui_script_names()`.
- **FR-CTXMENU-NATIVE-001** — The browser's menu is kept with selected text, on `input`, `textarea`, `select`, `contenteditable`,
  `a[href]`, with Shift or Ctrl held, and when a host handler already prevented the `contextmenu` event.
- **FR-CTXMENU-A11Y-001** — WAI-ARIA menu: `role="menu"` named by the label, `role="none"` / `role="menuitem"` (`tabindex="-1"`,
  `aria-disabled`, `aria-haspopup` / `aria-expanded` / `aria-controls` for submenus), roving focus with Down / Up (wrap), Home /
  End, typeahead, Enter / Space, Right / Left (mirrored in RTL) for submenus, Escape (submenu first; then focus returns to the
  element, else the kebab toggle), Tab closes. A dialog opened by a forwarded item returns focus to the element (focus-return
  chain, FR-DIALOG-002).
- **FR-CTXMENU-TOUCH-001** — Long press (touch / pen): `LONG_PRESS_MS` 500 ms, cancelled by a move over 10 px, lifting, cancel or
  scroll; the click ending the press (right after that pointer's pointerup / pointercancel) is swallowed (menu items
  excepted); listeners passive (scrolling unaffected); Android's own
  long-press `contextmenu` opens the menu once; items at least 44 px tall under `pointer: coarse`.
- **FR-CTXMENU-LIFECYCLE-001** — One menu at a time (also closes open kit popovers and `<details>` menus); closes on an outside
  pointerdown, a scroll of the page (position changed) or of a scroller around the host, resize, window blur,
  `turbo:before-cache` / `turbo:before-render`, and another kit popover opening. Events on the host:
  `nowo-ui:context-menu:open` (cancelable, `{source, trigger}`) and `nowo-ui:context-menu:close` (`{source}`); attribute
  `data-nowo-ui-context-menu-open` while open.
- **FR-CTXMENU-PLATFORM-001** — `popover="manual"` top-layer panel (inside an open modal `<dialog>` around the host), `hidden` /
  `position: fixed` fallback without the Popover API; placed at the pointer (flipped and clamped to the viewport) or under the
  focused element; coordinates through the CSSOM; no inline script, handler or style attribute, no `eval` (strict CSP). Styles from
  the menu panel rules and `--nowo-ui-*` tokens. Server side stateless (FrankenPHP worker mode, kernel reset off). A second copy of the
  script (Turbo body scripts) binds nothing (`Symbol.for('nowo-ui.context-menu.bound')` window flag).

## Verification

Vitest `nowo-ui-context-menu.test.ts`; PHPUnit `ContextMenuTest` and `PageContractConfigurationTest`; Playwright
`context-menu.spec.ts` (Chromium, Firefox, WebKit, with axe and a CSP violation watch) and `context-menu-touch.spec.ts` (Pixel 7
touch emulation) on the demo page `/kit/context-menu` (`make test-e2e-context-menu`).
