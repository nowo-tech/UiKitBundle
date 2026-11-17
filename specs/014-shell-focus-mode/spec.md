# Feature Specification: Shell focus mode

**Feature Branch**: `feat/focus-mode`  
**Created**: 2026-11-17  
**Status**: Implemented (target 2.55.0)  
**Input**: Beacon request, approved by the owner on 2026-11-17: a generic focus mode of the kit shell, so WikiBundle and
TaskBoard can add a toggle instead of each hiding the host chrome on its own.

**Related docs**: [`docs/USAGE.md`](../../docs/USAGE.md#shell-focus-mode-255), [`docs/THEME_SLOTS.md`](../../docs/THEME_SLOTS.md),
[`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/UPGRADING.md`](../../docs/UPGRADING.md)

---

## Summary

Two levels on the `[data-nowo-ui-shell]` root. Level 1 (`data-focus-mode="on"`) hides the shell regions around the work
area (sidebar, top bar, footer, side panels, edge tabs, a host's messages dock) and shows a small focus bar with an exit
button; the work area goes full width. Level 2 (`data-focus-mode="fullscreen"`) adds the browser's Fullscreen API on the
shell, from a control inside focus mode. Config `nowo_ui_kit.theme.focus_mode` (off by default), partials
`focus_mode_toggle` and `focus_mode_bar`, runtime `focus-mode` (loaded by the `scripts` partial when enabled).

## Requirements

- **FR-FOCUSMODE-001 (config and markup)** — `theme.focus_mode`: `enabled` (false), `regions` (default all built-in:
  `sidebar`, `header`, `footer`, `side_panels`, `edge_tabs`, `dock`), `keep` (`[]`; names of elements kept visible,
  `breadcrumb_min` built in), `shortcut` (`F`; null / false = none), `fullscreen` (true). Names are `^[a-z][a-z0-9_]{0,31}$`,
  shortcuts one letter or digit after optional `Ctrl+` / `Alt+` / `Shift+` / `Meta+`; anything else fails the container
  build. While `enabled` is false, `app_shell_open`, the `scripts` partial, `focus_mode_toggle` and `focus_mode_bar` render
  exactly the 2.54 output. Enabled: the shell root gets `data-nowo-ui-focus-regions` / `-keep-list` / `-shortcut` /
  `-fullscreen` (`-area` with the `focus_area` option), the main region starts with the focus bar, the scripts partial adds
  `focus-mode`. A page opts out with the `app_shell_open` option `focus_mode: false`.
- **FR-FOCUSMODE-002 (levels)** — `[data-nowo-ui-focus-mode]` (or `="toggle"`) toggles level 1, `="fullscreen"` enters
  level 2 (level 1 first) or returns to level 1, `="exit"` leaves both. Level 1 marks every element of the listed regions
  `data-nowo-ui-focus-hidden` + `inert` (the CSS adds `display: none`), except kept elements (`data-nowo-ui-focus-keep`
  with a name of the keep list), the focus bar and the live region: a region holding one is hidden branch by branch. An
  element that was already inert stays inert afterwards. Level 2 calls `requestFullscreen` on the shell after feature
  detection; unsupported, refused (rejected promise) or throwing: level 1 stays, no error, the live region says so. The
  level becomes `fullscreen` on `fullscreenchange`, and a browser-side exit (Escape, F11, the API, a removed element)
  returns to level 1. Fullscreen controls are hidden where the browser or the config has no fullscreen.
- **FR-FOCUSMODE-003 (keyboard and memory)** — The shortcut toggles level 1 only on pages with a toggle outside the focus
  bar, and never: while an input, textarea, select or contenteditable, or (a shortcut without Ctrl / Alt / Meta) a link,
  button or other interactive control is focused (WCAG 2.1.4: hosts remap or disable it with `shortcut`),
  with another modifier, on key repeat, during composition, when the event was already handled (`defaultPrevented`) or
  while a dialog, menu, listbox, popover, open kit menu, open popup (`aria-expanded` combobox / popup button) or the phone
  aside drawer owns the keys. Escape (no modifier) leaves fullscreen first, then focus mode, under the same "nothing else
  owns it" rule and never from a focused text field; Ctrl / Cmd combinations (Ctrl+K search) are never handled. The state is remembered per area
  (`data-nowo-ui-focus-area` on the toggle, an ancestor or the page) in localStorage `nowo-ui-focus:<area>` = `on` and
  restored quietly on load and after `turbo:load` (level 1 only: fullscreen needs a user gesture); every storage access is
  wrapped in try / catch, and without storage nothing is remembered.
- **FR-FOCUSMODE-004 (accessibility)** — The focused element keeps the focus unless focus mode hid it (then the exit
  button gets it); leaving from the focus bar returns the focus to the toggle that entered. Toggles carry `aria-pressed`.
  Each change is announced in a polite live region (`data-nowo-ui-focus-status`, created when a host shell has none) with
  the texts of its `data-nowo-ui-focus-msg-*` attributes, translated in every UiKit locale. No motion of its own (reduced
  motion needs nothing more). Hidden regions are `inert` and `display: none`, so they leave the tab order and the
  accessibility tree.
- **FR-FOCUSMODE-005 (CSP and integration)** — Delegated listeners on the document, no inline handlers, styles or scripts;
  kit tokens only. Event `nowo-ui:focus-mode:change` `{level, previous, area, source}` on the shell; API
  `window.NowoUiFocusMode` (`enter`, `exit`, `toggle`, `fullscreen`, `level`). Works with Turbo (`turbo:load`,
  `turbo:frame-load`) and in FrankenPHP worker mode (no server state; the Twig global is the processed config). A
  second copy of the script binds nothing (`Symbol.for('nowo-ui.focus-mode.bound')`), and a click already handled
  (`defaultPrevented`) toggles nothing. `inert` the host writes on a hidden element during focus mode stays the host's.

## Audit SK (2026-11-17) fixes

SK-M2 (second copy, `defaultPrevented`), SK-L3 (Escape stays with text fields), SK-L4 (host `inert` ownership), SK-L5
(2.55 labels, spec 014, SECURITY.md), I6 (`F` ignored on focused controls, WCAG 2.1.4 note), I7 (`<html>` state cleared
without a focus-mode shell), I8 (live region created on start), I9 (e2e in Chromium, Firefox and WebKit).

## Out of scope

- Remembering level 2 (browsers only enter fullscreen from a user gesture).
- Pre-paint restore of the remembered state (a host that stores the state per user can render `data-focus-mode` on the
  shell root; the script then hides the regions when it starts).
- Elements outside the shell (toasts, a dock outside it) are not visible in level 2: the browser only shows the
  fullscreen element's subtree and the top layer (native dialogs, popovers).

## Acceptance

- Vitest `nowo-ui-focus-mode.test.ts`: toggle, inert / kept regions, F and Escape rules, storage failure, fullscreen mock
  and sync, refused fullscreen, live region, Turbo, focus handling.
- PHPUnit `FocusModeTest`: config defaults and validation, Twig global, shell attributes, focus bar, partials, scripts;
  the existing byte-for-byte tests prove the disabled output is unchanged.
- Playwright `focus-mode.spec.ts` on `demo/symfony8` `/kit/focus-mode`: regions, tab order, F / Escape / Ctrl+K, dialog
  Escape, memory with and without storage, fullscreen, axe, no CSP violation.
