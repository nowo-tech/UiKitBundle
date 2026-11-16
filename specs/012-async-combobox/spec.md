# Feature Specification: Async grouped combobox

**Feature Branch**: `feat/async-grouped-combobox`  
**Created**: 2026-11-16  
**Status**: Implemented (target 2.53.0)  
**Input**: Owner approval 2026-11-16 (question O10 of the Wiki anchors design: "Build UiKit `nowo-ui-combobox` now, for host
forms? Yes, as an independent UiKit minor"), to reduce host overrides. Milkdown keeps its own popup layer and does not depend on it.

**Related docs**: [`docs/USAGE.md`](../../docs/USAGE.md#combobox), [`docs/THEME_SLOTS.md`](../../docs/THEME_SLOTS.md),
[`docs/THEMING.md`](../../docs/THEMING.md), [`docs/SECURITY.md`](../../docs/SECURITY.md)

---

## Summary

A text input that searches a URL of the host as the user types and lists the answers in a grouped listbox ("This wiki" /
"Other wikis"). Partial `combobox`, form type `ComboboxType`, opt-in runtime `combobox`. Without JavaScript it is a plain text input.

## Requirements

- **FR-COMBOBOX-REQUEST-001** — `GET <url>?<param>=<term>` (param `q` by default) only to an http(s) URL on the page's origin,
  `mode: 'same-origin'`, credentials same-origin; after `debounce` ms (default 250, 0 to 2000) once the trimmed term has
  `min_length` characters (default 2, 1 to 50). A newer term aborts the request in flight; an older answer is dropped.
- **FR-COMBOBOX-ANSWER-001** — `{"groups": [{"label", "options": [{"value", "label", "description"}]}]}`, `{"options": [...]}` or
  a bare list; `application/json` or `+json`; own properties, strings (finite numbers as strings) only; at most 20 groups,
  100 options, 300 characters per text; written with `textContent` only.
- **FR-COMBOBOX-A11Y-001** — WAI-ARIA 1.2 combobox, list autocomplete without automatic selection: roles added by the script,
  `aria-activedescendant`, grouped listbox (`role="group"` named by its heading), Down / Up (wrap) / Alt+Down / Alt+Up / Enter
  (never submits on an option) / Escape (the list only, not a dialog around it) / Tab; polite status with the count, no results,
  the minimum length or the failure.
- **FR-COMBOBOX-PICK-001** — Cancelable `nowo-ui:combobox:pick` `{value, label, description, group}`, then the field gets the
  value; with `value_input` (a hidden input of the same form) the field gets the label and that input the value, and typing again
  empties it. `nowo-ui:combobox:results` `{term, count}`, `nowo-ui:combobox:error` `{term}`.
- **FR-COMBOBOX-PLATFORM-001** — No inline script or style (CSSOM placement, top-layer `[popover]`); light and dark through the
  tokens; LTR and RTL (aligned with the field's start edge); phone width (kept inside the viewport).
- **FR-COMBOBOX-BC-001** — Opt-in: the runtime is not in `nowo_ui_script_names()`; existing partials, blocks and markup are
  unchanged. Server side stateless (FrankenPHP worker mode, kernel reset off).

## Verification

Vitest `nowo-ui-combobox.test.ts`; PHPUnit `ComboboxTypeTest`; Playwright `combobox.spec.ts` (Chromium, Firefox, WebKit) on the
demo page `/kit/combobox`. FormKit mapping (`FormTypeMap`) is a later FormKit change.
