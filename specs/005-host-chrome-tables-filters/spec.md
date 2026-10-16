# Feature Specification: Host chrome — responsive tables, foldable filters, corner / edge tabs, scrollbars, morphicons

**Feature Branch**: `005-host-chrome-tables-filters`  
**Created**: 2026-10-16  
**Status**: Implemented (1.17.0)  
**Input**: Symfony Beacon spec 148 (responsive tables, implementation notes) and the chrome built with it — move the
generic pieces into the kit, opt-in, with neutral `nowo-ui-*` names, `--nowo-ui-*` tokens and copy.

**Related docs**: [`docs/USAGE.md`](../../docs/USAGE.md#host-chrome-117-opt-in), [`docs/STIMULUS.md`](../../docs/STIMULUS.md),
[`docs/THEMING.md`](../../docs/THEMING.md#corner-tabs-edge-tabs-scrollbars-117),
[`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md#route-links), [`docs/UPGRADING.md`](../../docs/UPGRADING.md#to-1170)

---

## Requirements

- **FR-001 (responsive table)**: `partials/_responsive_table.html.twig` (embed, block `table`), `_row_detail_head` /
  `_row_detail_cell`, Stimulus peer `responsive-table`. Scroll ↔ Responsive toggle (aria-pressed, label naming the
  target mode, static icons before JS); Responsive hides the last non-required columns (`data-required="1"` never)
  until the table fits, two-phase (without / with the detail column), on resize; no horizontal scroll at ≥ 320px
  (CSS: wrap, tighter padding under 30rem). Row detail `<dialog>` (confirm chrome): every column as label / value,
  cell contents MOVED in and back (forms, ids, listeners intact), title = first two columns (≤ 120 chars), Esc / ✕ /
  backdrop close, focus back to the opener; tap on a row on coarse pointers. Persistence host-owned: save URL + CSRF
  token + token refresh URL as values (JSON `{"mode"}`, `X-CSRF-TOKEN`, one refresh on 403); no URL = client-side
  only; failed save reverts and dispatches `responsive-table:save-failed` (protected `saveFailed()` for subclasses).
  Default mode: responsive on the phone media query, scroll elsewhere.
- **FR-002 (foldable filters)**: `partials/_filters_collapse.html.twig` + peer `filters-collapse`: server-rendered
  state from a cookie (configurable name, page key default = route), "N active filters" from a FormView (or
  `active_count`), corner tab under the fields (`isolation: isolate`, z-index -1), values kept while folded.
  `_filters.html.twig` gains `collapsible` / `collapse`.
- **FR-003 (corner tab)**: `.nowo-ui-corner-tab` (+ `--under`, `.nowo-ui-corner-host`), `_corner_tab.html.twig`,
  tokens `--nowo-ui-corner-tab-{width,height,icon,accent}`; used by the table toggle and the filter fold.
- **FR-004 (disclosure chevron)**: `_disclosure_icon.html.twig` (static icons from `aria-expanded` / `<details open>`)
  + peer `disclosure-icon`.
- **FR-005 (thin scrollbars)**: `.nowo-ui-thin-scroll`, `html[data-nowo-ui-thin-scrollbars]` (page + kit scrollers),
  tokens `--nowo-ui-scrollbar-*`, Firefox-only standard properties; SCSS mixin `assets/styles/_thin-scrollbars.scss`
  (`sass:selector`) for host selector lists.
- **FR-006 (page-edge tab)**: `_edge_tab.html.twig` (slots, inline variant) + `nowo_ui_route_link()` fed by
  `nowo_ui_kit.route_links` (`base_url`, `default_testid`, `routes`; longest prefix wins, `<prefix>_` matching).
- **FR-007 (morphicons)**: `assets/morphicons/index.ts` (optional `morphicons` + `lucide` peer dependencies):
  `prepareMorphHost`, `asMorphIcon`, generic `syncMorphIcon`, sync helpers (theme, content width, sidebar, password,
  listing customizer, table mode, filters collapse, disclosure, search magnifier ↔ X ↔ loader, support lifebuoy ↔ X),
  `data-motion="reduce"`; `stimulus-peers/icon_state.ts` bridge so the peers never import an icon library;
  `installMorphiconPeers()`.
- **FR-008 (icons / copy)**: canonical icon kinds `filter_off`, `scroll_horizontal`, `rows`, `chevron_up`,
  `chevron_down`, `chevron_right`, `book` in every icon set; translations `table_mode.*`, `filters_collapse.*`,
  `edge_tab.manual` (7 locales); recommended icon-only Filter / Clear pair documented.

## Acceptance

- Vitest covers the three peers, the bridge, the morphicons module (gated) and the SCSS mixin (compiled); PHPUnit
  covers every partial, the kitchen sink section, the icons, the CSS hooks, the translations, the config and the
  Twig function.
- No `beacon` names or product copy in kit sources; everything opt-in (1.16 markup renders byte-identical).

## Out of scope

The listing customizer (column picker, layouts), the persistence endpoint itself, Beacon's issue-table container
query fallbacks, the global search palette.
