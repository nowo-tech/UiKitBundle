# Feature Specification: Live item attributes, pagination modes, provider-dependent fields, audit CK hardening

**Feature Branch**: `feat/2.23.0`  
**Created**: 2026-11-02  
**Status**: Implemented (target 2.23.0, awaiting symfony-beacon approval)  
**Input**: Symfony Beacon (monitor page live updates, phone lists, InstanceSettings 1.3.0 provider settings) and the
2.22 REQ-SEC-004 delta audit CK (L1, L2, O1).

**Related docs**: [`docs/USAGE.md`](../../docs/USAGE.md#live-item-attributes-223) (three 2.23 sections),
[`docs/STIMULUS.md`](../../docs/STIMULUS.md#list-pagination-223), [`docs/UPGRADING.md`](../../docs/UPGRADING.md#unreleased),
[`../001-baseline/code-inventory.md`](../001-baseline/code-inventory.md)

---

## Summary

Three opt-in features and three security hardenings. Pages that use none of the features render byte-identical to
2.22 (`OptInFeatures223BcTest`, fixtures `tests/Fixtures/bc-2.22`, rendered by 2.22.0).

## Requirements

- **FR-ITEMATTR-001** — `Stat::$attributes` (tile) / `$valueAttributes` (value `<dd>`), `PageStatus::$attributes`
  (badge), `DetailItem::$attributes` (item `<div>`) / `$valueAttributes` (value `<dd>`): validated with
  `Action::assertAttributes()` (no `on*`, valid names, scalar values), printed escaped with `nowo_ui_html_attrs`;
  empty = unchanged markup.
- **FR-PAGINATION-002** — `Pagination::$mode` `pager` (default) | `infinite` | `responsive` with `modeNarrow` /
  `modeWide` (`pager` | `infinite`), `breakpoint` (media query, default `(max-width: 900px)`, no markup characters, at
  most 200 chars) and `autoLoads` (≥ 0, default 3). A non-`pager` mode keeps the pager links (no-JS fallback) and adds
  the `list-pagination` contract (`_pagination_mode` partial); the list scaffold marks its rows region
  (`tbody[data-nowo-ui-list-rows]`). The peer: IntersectionObserver auto-load up to `autoLoads`, an always-present
  "Load more" button, same-origin fetch of the next page's full HTML, append of the rows region children only (scripts
  dropped) plus missing row confirm dialogs, `history.replaceState`, polite live region "N more rows loaded", stable
  focus, loading state, `nowo-ui:rows-appended`, live `matchMedia` switching; back to `pager` keeps appended rows.
- **FR-VISIBLEWHEN-001** — `visible-when` peer: rows `data-nowo-ui-visible-when-field` / `-in`; non-matching rows
  `hidden` + `inert` + `aria-hidden`; never disables or clears inputs; re-sync on connect / change / input; no JS =
  all visible. `Form\VisibleWhen::attributes()` / `fromOption()` / `rowAttr()` / `containerAttributes()`, Twig
  `nowo_ui_visible_when()`; the kit form theme renders `row_attr` (no theme change).
- **FR-SEC-223** — (L1) `js/nowo-ui-confirm.js` asks with `window.confirm()` and cancels on decline when a
  `confirm-submit` form submits without the peer connected; (L2) the peer clears `approved` in `finally` around the
  replay; (O1) the error page retry URI is path-only (`ErrorPageExtension::pathOnly()`, client `pathOnlyUrl()`).

## User Scenarios & Testing

### US-1 — Live monitor page (P1)

**Given** a show page with `PageStatus(attributes: ['data-detail-status' => 'up'])` and stats / facts with
attributes, **When** rendered, **Then** the badge, tiles, values and facts cells carry them escaped; `on*` names throw.
Tests: `OptInFeatures223Test`, demo e2e `kit-223.spec.ts` (item attributes + axe).

### US-2 — Lists that scroll on phones (P1)

**Given** `Pagination::fromRequest(...)->withMode('responsive')`, **When** the list renders on a phone, **Then** the
next pages load as the user scrolls (3 automatic loads, then "Load more"), filters / sort / per_page are kept, `?page=`
follows, rows render as cards; on a desktop the pager links stay; resizing switches without reload and keeps the rows.
Without JS the pager links work. Tests: `list_pagination_controller.test.ts`, `kit-223.spec.ts` at 390 px and 1280 px.

### US-3 — Provider settings (P1)

**Given** a settings form with rows bound to `settings[provider]`, **When** the provider changes, **Then** only its
rows show; saving submits every field, so the other provider's values and a blank secret ("keep") survive. Tests:
`visible_when_controller.test.ts`, `OptInFeatures223Test`, `kit-223.spec.ts` (switch, save, values kept, no-JS).

### US-4 — Fail closed (P1)

**Given** a row action in `native` / `dialog` mode and no connected peer, **When** submitted, **Then** the browser
confirm asks and a decline keeps the form unsent. Tests: `nowo-ui-confirm.test.ts`, `confirm_submit_controller.test.ts`
(L2), `ErrorPageExtension` cases in `OptInFeatures223Test` (O1), `nowo-ui-error-retry-core.test.ts`,
`row-actions-confirm.spec.ts`.

## Success criteria

- Defaults byte-identical to 2.22 (BC fixtures for show / list / stat row / page status / detail list / pagination /
  table / error page, three frameworks).
- PHP and TS coverage gates kept; demo e2e green at phone and desktop widths; axe clean on the new regions.
