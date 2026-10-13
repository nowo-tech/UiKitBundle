# Feature Specification: Host chrome extractions (icon filter actions, theme boot, sidebar rail, peers)

**Feature Branch**: `003-host-chrome-extractions`  
**Created**: 2026-10-13  
**Status**: Implemented (1.15.0)  
**Input**: Symfony Beacon spec 138 (`kit-medium-extractions`) **FR-001 … FR-004** — move generic chrome that Beacon
keeps in its own assets into the kit, opt-in, with neutral names and copy.

**Related docs**: [`docs/USAGE.md`](../../docs/USAGE.md#pre-paint-theme-boot), [`docs/STIMULUS.md`](../../docs/STIMULUS.md),
[`docs/UPGRADING.md`](../../docs/UPGRADING.md#to-1150)

---

## Requirements

- **FR-001 (icon filter actions)**: `partials/_filter_actions.html.twig` accepts `submit_icon` / `clear_icon` (icon
  name through `components/_icon.html.twig` and `nowo_ui_kit.icon_set`) and `icon_only`. Icon-only buttons keep the
  label as visually-hidden text plus `title` / `aria-label`; `.nowo-ui-filter-bar__action--icon-only` makes them
  square. Without the options the output is byte-identical to 1.14.
- **FR-002 (pre-paint theme boot)**: `nowo-ui-theme-boot.ts` → IIFE `js/nowo-ui-theme-boot.js` and an inline copy
  `views/inline/nowo-ui-theme-boot.js` printed by `partials/_theme_boot.html.twig` (`inline` default true, CSP nonce
  from `nonce` or the `csp_nonce` request attribute, `inline: false` + optional `src`). Reads
  `data-nowo-ui-pref-{theme,density,motion,font-scale,contrast,sidebar}` (alias `data-user-*`) on `<html>`, falls
  back to the theme cookie (`data-nowo-ui-theme-cookie`) then localStorage (`data-nowo-ui-theme-storage`, default
  `nowo-ui-theme`) then the OS, and writes `data-theme`, `data-ui-density`, `data-motion`, `data-font-scale`,
  `data-contrast`; mirrors to `window.nowoUiPrefs`. `nowo-ui-theme.ts` reads and writes the same keys through the
  shared `nowo-ui-theme-core.ts`, so toggle and boot never disagree (no flash).
- **FR-003 (sidebar rail)**: opt-in `data-nowo-ui-sidebar-rail` on `[data-nowo-ui-shell]`: modes `expanded` / `rail`
  / `hidden`, burger ping-pong through the rail (direction in sessionStorage), phones open / hidden without
  persistence, localStorage + optional cookie sync, configurable keys (`-storage`, `-cookie`, `-hidden-value`,
  `-default`, `-rail-labels`); `collapsed` is read as `hidden`. Pure helpers in `nowo-ui-sidebar-core.ts` with a
  configurable DOM contract (classes, rail attribute prefix, mode attribute, label lookup) for host shells. Theme
  boot performs the pre-paint cookie ↔ localStorage sync. `_shell.html.twig` `sidebar_rail` / `sidebar_mode`,
  `_burger.html.twig` `rail` labels (`nav.sidebar_*`, 7 locales), CSS rail block (`--nowo-ui-aside-rail-width`).
- **FR-004 (Stimulus peers)**: `temporary-reveal` (URL-credential or full masking, clear-on-hide purge of kit and
  legacy copy attributes), `collapse-panel` (storage key and server default as values, no globals, state written only
  on user toggle), `datatable` (server-side paging / sorting kept, Responsive child rows, `options` value, `stop`
  action; `datatables.net-dt` and `datatables.net-responsive-dt` as optional peer dependencies, stylesheets loaded by
  the host).

## Acceptance

- Vitest covers every new module and peer (gated in `vitest.config.ts`); PHPUnit covers the Twig partials, the inline
  copy matching the build, the opt-in scoping of the rail CSS.
- No `beacon` names or product copy in kit sources.

## Out of scope

Beacon's animated sidebar width transitions (Web Animations), menu morph icon, issue-specific panel defaults
(`window.__BEACON_*`), DataTables stylesheet bundling.
