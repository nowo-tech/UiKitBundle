# Theming (`--nowo-ui-*` tokens)

Since **1.13.0** every colour, radius, font, focus ring, overlay and shadow that UiKit paints — in
`nowo-ui.css`, the `ui.*` macros and every partial — reads a documented `--nowo-ui-*` CSS custom
property. Kit output carries **no hard-coded palette**: no Tailwind colour / radius / divide
utilities (`bg-blue-600`, `border-slate-300`, `rounded-md`, `divide-y`…) and no fixed hex outside the
two token blocks of `nowo-ui.css`. A host theme is a short `:root` block.

## Table of contents

- [How it works](#how-it-works)
- [Writing a host theme](#writing-a-host-theme)
- [Base tokens](#base-tokens)
- [Typography, focus, links](#typography-focus-links)
- [Radii, overlays, shadows](#radii-overlays-shadows)
- [Buttons](#buttons)
- [Inputs, checkboxes, radios](#inputs-checkboxes-radios)
- [Tables](#tables)
- [Tabs](#tabs)
- [Pagination](#pagination)
- [Lists](#lists)
- [List filters grid (2.30)](#list-filters-grid-230)
- [Cards, navigation, progress](#cards-navigation-progress)
- [Detail pages (2.21)](#detail-pages-221)
- [Compact header (2.27)](#compact-header-227)
- [Full-height sidebar layout (2.32)](#full-height-sidebar-layout-232)
- [Motion](#motion)
- [Layers (public API, 2.28)](#layers-public-api-228)
- [Dialog sizes (2.29)](#dialog-sizes-229)
- [Remote form dialog (2.28)](#remote-form-dialog-228)
- [Corner tabs, edge tabs, scrollbars (1.17)](#corner-tabs-edge-tabs-scrollbars-117)
- [Phone mode (2.41)](#phone-mode-241)
- [Default-theme polish tokens (2.2)](#default-theme-polish-tokens-22)
- [Layout and metrics tokens](#layout-and-metrics-tokens)
- [CSS framework notes](#css-framework-notes)
- [Example: generic brand](#example-generic-brand)
- [Example: Symfony Beacon (moss / sand)](#example-symfony-beacon-moss--sand)
- [Appearance SPI](#appearance-spi)
  - [Emitted tokens](#emitted-tokens)
  - [Host storage and presets](#host-storage-and-presets)
  - [Keeping legacy variable names (`token_aliases`)](#keeping-legacy-variable-names-token_aliases)

## How it works

- **Base tokens** (palette, `--nowo-ui-radius`) are declared on `:root` (light) and on
  `[data-theme="dark"]` (dark) in `nowo-ui.css`.
- **Component tokens** (`--nowo-ui-btn-*`, `--nowo-ui-input-*`, `--nowo-ui-table-*`, `--nowo-ui-tab-*`,
  `--nowo-ui-pagination-*`, `--nowo-ui-list-*`, fonts, focus ring, shadows…) are **optional**. They are
  not declared anywhere: each rule reads them through a `var()` fallback chain that ends at a base token,
  e.g. `background: var(--nowo-ui-btn-primary-bg, var(--nowo-ui-primary))`. Consequences:
  - leave them unset and the kit looks exactly as it does from the base tokens;
  - set them on `:root` **or on any scope** (`.kit-admin`, a portalled `<dialog>`) — they are resolved
    where they are used, so a scoped `--nowo-ui-primary` also recolours hover states, focus rings, etc.;
  - dark mode keeps working: the fallbacks follow the dark base tokens.
- The *Default* column below is that fallback chain.

## Writing a host theme

```css
/* Load after nowo-ui.css, or use html:root (beats both :root and [data-theme="dark"] regardless of order). */
html:root {
  --nowo-ui-primary: #7c3aed;
  --nowo-ui-primary-contrast: #ffffff;
  /* …only what differs from the defaults */
}

html:root[data-theme="dark"] {
  --nowo-ui-primary: #a78bfa;
  --nowo-ui-primary-contrast: #0b0618;
}
```

If your own palette already switches with the theme (`--brand-*` redefined under
`[data-theme="dark"]`), one `html:root { --nowo-ui-primary: var(--brand-primary); … }` block covers
light and dark.

## Base tokens

Declared in `nowo-ui.css` (light on `:root`, dark on `[data-theme="dark"]`).

| Token | Light | Dark | Used by |
|-------|-------|------|---------|
| `--nowo-ui-primary` | `#2563eb` | `#60a5fa` | primary buttons, active tab border, active page, links, focus ring, progress, spinner, accent |
| `--nowo-ui-primary-contrast` | `#ffffff` | `#0b1220` | text on primary |
| `--nowo-ui-danger` | `#ef4444` | `#f87171` | danger button border |
| `--nowo-ui-danger-text` | `#b91c1c` | `#fecaca` | danger button / menu item text |
| `--nowo-ui-danger-bg` | `#fef2f2` | `#3f1d1d` | error flash / badge, danger button hover |
| `--nowo-ui-danger-border` | `#fecaca` | `#7f1d1d` | error flash / badge border |
| `--nowo-ui-danger-text-strong` | `#991b1b` | `#fecaca` | error flash / badge text |
| `--nowo-ui-success-bg` | `#ecfdf5` | `#052e1f` | success flash / badge |
| `--nowo-ui-success-border` | `#a7f3d0` | `#065f46` | success flash / badge border |
| `--nowo-ui-success-text` | `#065f46` | `#6ee7b7` | success flash / badge text |
| `--nowo-ui-warning-bg` *(1.13)* | `#fffbeb` | `#3b2a0b` | warning flash / badge |
| `--nowo-ui-warning-border` *(1.13)* | `#fde68a` | `#78350f` | warning flash / badge border |
| `--nowo-ui-warning-text` *(1.13)* | `#92400e` | `#fcd34d` | warning flash / badge text |
| `--nowo-ui-info-bg` *(1.13)* | `#f0f9ff` | `#082f49` | info flash / badge |
| `--nowo-ui-info-border` *(1.13)* | `#bae6fd` | `#075985` | info flash / badge border |
| `--nowo-ui-info-text` *(1.13)* | `#075985` | `#7dd3fc` | info flash / badge text |
| `--nowo-ui-surface` | `#ffffff` | `#0f172a` | cards, header, aside, menus, modals, inputs, buttons |
| `--nowo-ui-surface-muted` | `#f8fafc` | `#1e293b` | shell background, hover rows, stripes, neutral badge |
| `--nowo-ui-border` | `#e2e8f0` | `#334155` | dividers, cards, tables, tabs, lists |
| `--nowo-ui-border-strong` | `#cbd5e1` | `#475569` | control borders (buttons, inputs, pagination) |
| `--nowo-ui-text` | `#0f172a` | `#e2e8f0` | body text |
| `--nowo-ui-muted` | `#64748b` | `#94a3b8` | secondary text, placeholders, table head (data table) |
| `--nowo-ui-radius` | `0.375rem` | — | every control / surface radius (overridable per component) |

## Typography, focus, links

| Token | Default | Used by |
|-------|---------|---------|
| `--nowo-ui-font-family` | `inherit` | `.nowo-ui-shell` |
| `--nowo-ui-font-display` | `inherit` | page header title, card title, modal / confirm title, brand text |
| `--nowo-ui-heading-weight` | `700` | page header title |
| `--nowo-ui-focus-ring-color` | `color-mix(in srgb, var(--nowo-ui-primary) 35%, transparent)` | `:focus-visible` outline on buttons, links, tabs, pagination, toggles, menus, checkboxes |
| `--nowo-ui-focus-ring` | `0 0 0 0.2rem var(--nowo-ui-focus-ring-color)` | `box-shadow` of focused inputs (and Bootstrap controls with the bridge) |
| `--nowo-ui-link` | `var(--nowo-ui-primary)` | `.nowo-ui-link`, `.nowo-ui-btn--link` |
| `--nowo-ui-link-hover` | `var(--nowo-ui-link)` | link hover |
| `--nowo-ui-primary-hover` | `color-mix(in srgb, var(--nowo-ui-primary) 85%, white)` | primary button hover (custom / Tailwind stacks) |
| `--nowo-ui-accent` | `var(--nowo-ui-primary)` | `accent-color` of checkboxes / radios in kit surfaces |
| `--nowo-ui-font-mono` *(2.1)* | `ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace` | `.nowo-ui-code`, `.nowo-ui-code-block` (slots `code`, `code_block`) |
| `--nowo-ui-code-bg` *(2.1)* | `color-mix(in srgb, var(--nowo-ui-text) 7%, var(--nowo-ui-surface))` (2.2; 2.1: `var(--nowo-ui-surface-muted)`) | inline code and code block background |
| `--nowo-ui-code-text` *(2.1)* | `var(--nowo-ui-text)` | inline code and code block text |
| `--nowo-ui-code-border` *(2.1)* | `var(--nowo-ui-border)` | code block border |
| `--nowo-ui-code-radius` *(2.1)* | `var(--nowo-ui-radius)` | inline code and code block radius |
| `--nowo-ui-code-block-max-height` *(2.1)* | `32rem` | code block height before it scrolls |

## Radii, overlays, shadows

| Token | Default | Used by |
|-------|---------|---------|
| `--nowo-ui-badge-radius` | `999px` | `.nowo-ui-badge` |
| `--nowo-ui-modal-radius` | `var(--nowo-ui-radius)` | modal content, confirm panel |
| `--nowo-ui-card-radius` | `var(--nowo-ui-radius)` | `.nowo-ui-card` |
| `--nowo-ui-overlay` | modal `rgba(0, 0, 0, 0.45)`, confirm `rgba(15, 23, 42, 0.45)`, mobile aside `rgba(15, 23, 42, 0.35)` | one value for every backdrop when set |
| `--nowo-ui-shadow-popover` | `0 10px 28px rgba(15, 23, 42, 0.12)` | user menu, locale switcher, kebab menu |
| `--nowo-ui-shadow-toast` | `0 10px 28px rgba(15, 23, 42, 0.14)` | toasts |
| `--nowo-ui-shadow-dialog` | modal `0 8px 32px rgba(0, 0, 0, 0.18)`, confirm `0 16px 40px rgba(15, 23, 42, 0.2)` | modal, confirm |

## Buttons

`ui.btn(variant)` → `.nowo-ui-btn` + `.nowo-ui-btn--{secondary|primary|danger|outline|link}`. Hover
rules apply to the kit-painted stacks (`custom`, `none`, `tailwind`); Bootstrap `.btn` keeps its own
states (see the [bridge](#css-framework-notes)).

| Token | Default |
|-------|---------|
| `--nowo-ui-btn-radius` | `var(--nowo-ui-radius)` |
| `--nowo-ui-btn-bg` | `var(--nowo-ui-surface)` |
| `--nowo-ui-btn-text` | `var(--nowo-ui-text)` |
| `--nowo-ui-btn-border` | `var(--nowo-ui-border-strong)` |
| `--nowo-ui-btn-hover-bg` | `var(--nowo-ui-surface-muted)` |
| `--nowo-ui-btn-hover-border` | `var(--nowo-ui-btn-border)` |
| `--nowo-ui-btn-hover-text` | `var(--nowo-ui-btn-text)` |
| `--nowo-ui-btn-primary-bg` | `var(--nowo-ui-primary)` |
| `--nowo-ui-btn-primary-border` | `var(--nowo-ui-btn-primary-bg)` |
| `--nowo-ui-btn-primary-text` | `var(--nowo-ui-primary-contrast)` |
| `--nowo-ui-btn-primary-hover-bg` | `var(--nowo-ui-primary-hover)` (also the hover border) |
| `--nowo-ui-btn-danger-bg` | `var(--nowo-ui-surface)` |
| `--nowo-ui-btn-danger-border` | `var(--nowo-ui-danger)` |
| `--nowo-ui-btn-danger-text` | `var(--nowo-ui-danger-text)` |
| `--nowo-ui-btn-danger-hover-bg` | `var(--nowo-ui-danger-bg)` |
| `--nowo-ui-btn-outline-bg` *(new variant)* | `transparent` |
| `--nowo-ui-btn-outline-border` | `var(--nowo-ui-primary)` |
| `--nowo-ui-btn-outline-text` | `var(--nowo-ui-primary)` |
| `--nowo-ui-btn-outline-hover-bg` | `color-mix(in srgb, var(--nowo-ui-primary) 10%, var(--nowo-ui-surface))` |

The link variant uses `--nowo-ui-link` / `--nowo-ui-link-hover`.

## Inputs, checkboxes, radios

`ui.input()` / `ui.search_input()` → `.nowo-ui-input`; `ui.check()` → `.nowo-ui-check` (label wrapper,
1.13). Surface / text / placeholder / focus apply when the element is not a Bootstrap `.form-control`.

| Token | Default |
|-------|---------|
| `--nowo-ui-input-bg` | `var(--nowo-ui-surface)` |
| `--nowo-ui-input-text` | `var(--nowo-ui-text)` |
| `--nowo-ui-input-border` | `var(--nowo-ui-border-strong)` |
| `--nowo-ui-input-radius` | `var(--nowo-ui-radius)` |
| `--nowo-ui-input-placeholder` | `var(--nowo-ui-muted)` |
| `--nowo-ui-input-focus-border` | `var(--nowo-ui-primary)` (+ `--nowo-ui-focus-ring` shadow) |
| `--nowo-ui-accent` | `var(--nowo-ui-primary)` — checkboxes / radios in `.nowo-ui-check`, filter bars, searches, tables, lists, cards, modals, confirms |

## Tables

`ui.table()` (`.nowo-ui-table`, striped) and the opt-in `table.nowo-ui-data-table`.

| Token | Default |
|-------|---------|
| `--nowo-ui-table-border` | `var(--nowo-ui-border)` (data-table body rows: 70 % of it) |
| `--nowo-ui-table-head-bg` | `transparent` |
| `--nowo-ui-table-head-text` | plain table: inherited text colour; data table: `var(--nowo-ui-muted)` |
| `--nowo-ui-table-stripe-bg` | `var(--nowo-ui-surface-muted)` |
| `--nowo-ui-table-hover-bg` | `color-mix(in srgb, var(--nowo-ui-surface-muted) 80%, transparent)` |

Metrics (1.12): `--nowo-ui-table-head-py`, `--nowo-ui-table-cell-py`, `--nowo-ui-table-cell-px`,
`--nowo-ui-table-font-size`, `--nowo-ui-table-head-font-size` (data table only).

Sortable headers (2.20, partial `sort_header`):

| Token | Default |
|-------|---------|
| `--nowo-ui-sort-hover` | `var(--nowo-ui-link-hover, var(--nowo-ui-link, var(--nowo-ui-primary)))` (header label on hover) |
| `--nowo-ui-sort-icon-opacity` | `0.45` (arrow of a column that is not the sorted one) |

## Tabs

`ui.tab_list()` / `ui.tab_item(active)` and `partials/_tabs.html.twig`.

| Token | Default |
|-------|---------|
| `--nowo-ui-tabs-border` | `var(--nowo-ui-border)` (the list underline; `transparent` removes it) |
| `--nowo-ui-tabs-gap` | `0.25rem` |
| `--nowo-ui-tab-text` | `var(--nowo-ui-muted)` |
| `--nowo-ui-tab-bg` | `transparent` |
| `--nowo-ui-tab-radius` | `0` |
| `--nowo-ui-tab-hover-text` | `var(--nowo-ui-text)` |
| `--nowo-ui-tab-hover-bg` | `var(--nowo-ui-tab-bg)` |
| `--nowo-ui-tab-active-text` | `var(--nowo-ui-text)` |
| `--nowo-ui-tab-active-bg` | `var(--nowo-ui-tab-bg)` |
| `--nowo-ui-tab-active-border` | `var(--nowo-ui-primary)` (2px bottom border) |
| `--nowo-ui-tab-active-weight` | `600` |
| `--nowo-ui-locale-tabs-gap` *(2.5)* | `0.75rem` (between the tab list and the panel of the `locale_tabs` partial) |
| `--nowo-ui-locale-tabs-default` *(2.5)* | `var(--nowo-ui-primary)` (the default-language ★ marker) |

## Pagination

`partials/_pagination.html.twig` (`.nowo-ui-pagination__link`). Since 1.13 its `<ul class="pagination">`
lays out as a row on every stack (zero-specificity rule; Bootstrap's own `.pagination` still wins).

| Token | Default |
|-------|---------|
| `--nowo-ui-pagination-bg` | `var(--nowo-ui-surface)` |
| `--nowo-ui-pagination-text` | `var(--nowo-ui-text)` |
| `--nowo-ui-pagination-border` | `var(--nowo-ui-border-strong)` |
| `--nowo-ui-pagination-radius` | `var(--nowo-ui-radius)` |
| `--nowo-ui-pagination-hover-bg` | `var(--nowo-ui-surface-muted)` |
| `--nowo-ui-pagination-hover-border` | `var(--nowo-ui-pagination-border)` |
| `--nowo-ui-pagination-hover-text` | `var(--nowo-ui-pagination-text)` |
| `--nowo-ui-pagination-active-bg` | `var(--nowo-ui-primary)` |
| `--nowo-ui-pagination-active-border` | `var(--nowo-ui-pagination-active-bg)` |
| `--nowo-ui-pagination-active-text` | `var(--nowo-ui-primary-contrast)` |
| `--nowo-ui-pagination-active-weight` | `inherit` |

## Lists

`ui.list()` / `ui.list_item()`.

| Token | Default |
|-------|---------|
| `--nowo-ui-list-border` | `var(--nowo-ui-border)` (outer border; `transparent` removes it) |
| `--nowo-ui-list-radius` | `var(--nowo-ui-radius)` |
| `--nowo-ui-list-item-border` | `var(--nowo-ui-list-border)` (row separator) |
| `--nowo-ui-list-item-bg` | `transparent` |
| `--nowo-ui-list-item-hover-bg` | `var(--nowo-ui-list-item-bg)` (no hover by default) |

## List filters grid (2.30)

The list scaffold filter fields sit on `grid-template-columns: var(--nowo-ui-filters-grid-columns, repeat(auto-fit,
minmax(min(100%, var(--nowo-ui-filters-field-min, 12rem)), 1fr)))`.

| Token / class | Default | Notes |
|-------|---------|-------|
| `--nowo-ui-filters-grid-columns` | unset (auto-fit) | the whole track list; set it with `theme.pages.list.filters.grid: '<track list>'` (validated) or `theme.tokens.filters-grid-columns` (wins) |
| `--nowo-ui-filters-field-min` | `12rem` | minimum track width of the auto-fit / auto-fill modes |
| `--nowo-ui-filters-gap` | `0.75rem 1rem` | grid gap |
| `.nowo-ui-filters--grid-auto-fill` | — | `grid: auto-fill` / `ListPage::filtersGrid: 'auto-fill'`: empty tracks keep their width, so a lone filter does not stretch |
| `.nowo-ui-filters--grid-auto-fit` | — | `ListPage::filtersGrid: 'auto-fit'` over a host track list |

## Cards, navigation, progress

| Token | Default |
|-------|---------|
| `--nowo-ui-card-bg` | `var(--nowo-ui-surface)` |
| `--nowo-ui-card-border` | `var(--nowo-ui-border)` |
| `--nowo-ui-card-intro-text` | `color-mix(in srgb, var(--nowo-ui-muted) 85%, var(--nowo-ui-text))` (card intro under the title; keeps 4.5:1 on a Bootstrap card header) |
| `--nowo-ui-side-panel-width` | `22rem` (2.25: side panel column and overlay drawer width; phones use the full width) |
| `--nowo-ui-side-panel-bg` | `var(--nowo-ui-surface)` |
| `--nowo-ui-side-panel-border` | `var(--nowo-ui-border)` |
| `--nowo-ui-side-panel-shadow` | `0 0 1.5rem rgb(0 0 0 / 25%)` (drawer only) |
| `--nowo-ui-side-panel-backdrop` | `rgb(0 0 0 / 40%)` |
| `--nowo-ui-side-panel-z` | `var(--nowo-ui-z-overlay, 1040)` (backdrop; the drawer is one above; [Layers](#layers-public-api-228)) |
| `--nowo-ui-emoji-picker-width` | `20rem` (`emoji_picker` panel; never wider than the screen) |
| `--nowo-ui-emoji-picker-height` | `14rem` (the grid, which scrolls past it) |
| `--nowo-ui-emoji-picker-z` | `var(--nowo-ui-z-popover, 1080)` (the panel without the Popover API; with it, the top layer; [Layers](#layers-public-api-228)) |
| `--nowo-ui-image-crop-mask` | `rgb(15 23 42 / 60%)` (2.43: the dimmed image around the crop frame; read by the script on every draw) |
| `--nowo-ui-image-crop-frame` | `rgb(255 255 255 / 95%)` (the frame line) |
| `--nowo-ui-image-crop-width` | `32rem` (the crop dialog; never wider than the screen) |
| `--nowo-ui-sortable-drop-bg` | `color-mix(in srgb, var(--nowo-ui-primary) 10%, transparent)` (`sortable` placeholder) |
| `--nowo-ui-sortable-drop-line` | `var(--nowo-ui-primary)` (placeholder border; outline of the list under the pointer) |
| `--nowo-ui-sortable-ghost-shadow` | `0 0.5rem 1.5rem rgb(0 0 0 / 25%)` (the copy following the pointer) |
| `--nowo-ui-sortable-z` | `var(--nowo-ui-z-popover, 1080)` (the copy; [Layers](#layers-public-api-228)) |
| `--nowo-ui-tree-badge-text` | `color-mix(in srgb, var(--nowo-ui-muted) 85%, var(--nowo-ui-text))` (2.24: `tree` badges; keeps 4.5:1 on the tinted current row) |
| `--nowo-ui-nav-active-bg` | `color-mix(in srgb, var(--nowo-ui-primary) 12%, transparent)` (current aside link) |
| `--nowo-ui-nav-active-text` | `var(--nowo-ui-primary)` |
| `--nowo-ui-progress-track` | `var(--nowo-ui-surface-muted)` |
| `--nowo-ui-progress-bar` | `var(--nowo-ui-primary)` |
| `--nowo-ui-page-loader-color` | `var(--nowo-ui-primary)` (minimal page loaders) |

## Compact header (2.27)

The top-bar slot of the shells (`header_context`) and its partials `header_context`, `header_crumbs` and
`page_actions` ([USAGE](USAGE.md#compact-header-227)). Every colour derives from the base tokens, so host `tokens` /
`tokens_dark` keep working; only the panel shadow has its own dark default (`Theme::LIGHT_ONLY_TOKENS`, so a host
`tokens` value for it applies in light mode only).

| Token | Default |
|-------|---------|
| `--nowo-ui-header-context-gap` | `0.35rem` (between the trigger, the home link and the trail) |
| `--nowo-ui-header-crumbs-font-size` | `0.875rem` |
| `--nowo-ui-header-crumbs-color` | `var(--nowo-ui-muted)` (ancestor links, separators, "…", "‹") |
| `--nowo-ui-header-crumbs-current-color` | `var(--nowo-ui-text)` |
| `--nowo-ui-header-crumbs-current-weight` | `600` |
| `--nowo-ui-header-crumbs-separator` | `"/"` (a CSS string for `content:`; e.g. `"»"` or `"›"`) |
| `--nowo-ui-header-crumbs-separator-rtl` | `var(--nowo-ui-header-crumbs-separator)` (set it when the glyph is directional, e.g. `"«"`) |
| `--nowo-ui-header-crumbs-item-max` | `12rem` (cap of each ancestor label) |
| `--nowo-ui-header-crumbs-current-min` | `4rem` (floor of the current label) |
| `--nowo-ui-header-crumbs-focus-inset` | `2px` (padding that keeps focus rings visible inside the clipped list) |
| `--nowo-ui-header-crumbs-panel-bg` | `var(--nowo-ui-surface)` ("…" panel) |
| `--nowo-ui-header-crumbs-panel-border` | `var(--nowo-ui-border)` |
| `--nowo-ui-header-crumbs-panel-shadow` | `0 8px 24px rgb(15 23 42 / 12%)`; dark `0 8px 24px rgb(0 0 0 / 45%)` |
| `--nowo-ui-main-padding-top-compact` | `0.75rem` (top padding of `.nowo-ui-main` under a filled bar) |

Page header variants (2.39, [USAGE](USAGE.md#page-header-variants-239)): the root carries
`nowo-ui-page-header--inline`, `--no-intro`, `--no-title` or `--no-header` and `data-nowo-ui-page-header="<value>"`.
No colour is involved, so light / dark need nothing.

| Token | Default |
|-------|---------|
| `--nowo-ui-page-header-inline-gap` | `0.75rem` (between the title and the intro on one row, `inline`) |
| `--nowo-ui-page-header-inline-intro-min` | `16rem` (below this width the intro wraps under the title, `inline`) |

<a id="full-height-sidebar-layout-232"></a>

## Full-height sidebar layout (2.32)

`nowo_ui_kit.shell.sidebar_layout: full-height` (or the `sidebar_layout` option / the `_nowo_ui_sidebar_layout` request
attribute; [USAGE](USAGE.md#full-height-sidebar-layout-232)) puts the aside from the top edge to the bottom on desktop
(901 px and up), with the burger and the brand in its head; the header and the footer start at its right edge. Every
colour comes from the base tokens (`--nowo-ui-surface`, `--nowo-ui-border`, `--nowo-ui-text`, `--nowo-ui-primary` /
`--nowo-ui-primary-contrast` for the initial icon), so light / dark and host `tokens` need nothing new. Hooks to style:
`[data-nowo-ui-sidebar-layout='full-height']` / `.is-sidebar-full-height` on the shell, `.nowo-ui-aside-head`,
`.nowo-ui-aside-brand`, `.nowo-ui-aside-brand__icon` (`--initial` for the letter), `.nowo-ui-aside-brand__name`, and
`.is-aside-peek` for the hover-expanded rail (both layouts).

| Token | Default | Used by |
|-------|---------|---------|
| `--nowo-ui-aside-peek-width` | `15.5rem` | the hover-expanded rail and the full-height aside head |
| `--nowo-ui-aside-brand-icon-size` | `1.75rem` | the aside brand icon box |
| `--nowo-ui-shell-inset-start` | `0px` (the aside width in this layout on desktop) | read-only output token set by the shell (a host value is shadowed inside it): a host's fixed footer / bar reads it with `inset-inline-start: var(--nowo-ui-shell-inset-start)` |

The burger keeps its spot in every mode: centred on the icons-only rail's column (`(--nowo-ui-aside-rail-width -
2.25rem) / 2` from the aside's start edge), in a row `--nowo-ui-header-height` tall. Phones keep the 2.31 off-canvas
aside, header burger and header brand.

## Motion

One scale for opening and closing, so that no surface is abrupt or slow. Set the values on `:root`; the kit's own declarations have zero specificity, so yours win.

| Token | Default | Reduced motion | Used for |
|-------|---------|----------------|----------|
| `--nowo-ui-motion-small` | `150ms` | `80ms` | Small surfaces opening: menus, popovers, select panels, tooltips, tabs, folds |
| `--nowo-ui-motion-small-close` | `110ms` | `80ms` | The same closing |
| `--nowo-ui-motion-medium` | `200ms` | `80ms` | Dialogs and their backdrop, row detail, opening |
| `--nowo-ui-motion-medium-close` | `150ms` | `80ms` | The same closing |
| `--nowo-ui-motion-ease-out` | `cubic-bezier(0.2, 0, 0, 1)` | same | Entering |
| `--nowo-ui-motion-ease-in` | `cubic-bezier(0.4, 0, 1, 1)` | same | Leaving |
| `--nowo-ui-motion-lift` | `4px` | `0px` | How far a small surface travels |
| `--nowo-ui-motion-scale` | `0.98` | `1` | The scale a dialog starts from |

**A bundle without the kit's CSS** reads them with its own fallback: `transition: opacity var(--nowo-ui-motion-small, 150ms) var(--nowo-ui-motion-ease-out, ease-out);`.

**Reduced motion:** `prefers-reduced-motion` keeps a short fade and no movement, by changing the tokens. `html[data-motion="reduce"]` does the same, and `html[data-motion="full"]` restores the defaults whatever the system says. Rules use no `!important`, so a host can also set the tokens or write its own rule. If you set a duration on `:root`, it stays in force under reduced motion: set it inside your own `prefers-reduced-motion` block too.

**How a close works.** The kit's own closes (buttons, Escape, a backdrop click, `nowoUiCloseDialog`) set `data-nowo-ui-closing`, which starts the fade, and run the real `close()`, `hidePopover()` or `hidden` when it ends, with a timer in case `transitionend` never fires. So focus returns, the `close` event fires and `dialog.open` turns false up to 150 ms later than before. With the durations at `0ms`, or without the kit's CSS, it all happens at once, in the same task as before. Opening again during a fade ends the fade first. A test should wait for the closed state, or set the durations to `0ms`.

While it fades, a modal dialog is `inert`, so it takes no keys, and `close()` still returns focus. A click on a menu's toggle during its fade opens the menu. Every fade ends on `turbo:before-cache`, so that no snapshot holds one.

**Closes the kit does not run:** a host's own `dialog.close()`, a `popovertarget` button closing its popover and a light dismiss run at once, and only CSS can keep them painted, through `transition-behavior: allow-discrete`. That works in Chromium. WebKit does it for an element that is not in the top layer, and Firefox does not do it at all, so there they close at once. `collapse-panel` and filters collapse use this CSS path too.

**Where it applies:** centred dialogs and their backdrop, row detail, kebab, kit menu and popover, page actions, date range panel, emoji picker panel, `collapse-panel`, filters collapse, and the `multi-select` panel, on the small scale. `nowo-ui-multi-select.css` also works without `nowo-ui.css`, so it carries the same values as fallbacks, and an `80ms` fade under `prefers-reduced-motion`.

**Opening only:**
- Tabs fade in. The old panel goes at once, as two panels at the same time would stack.
- The icon-tab tooltip fades in. It is a pseudo-element that does not exist once it goes.

Side panels and the aside have their own tokens and are not part of this scale.

## Layers (public API, 2.28)

The stacking order of the kit is a **public API** since 2.28: the values below are stable for 2.x (changing one is a
minor with an UPGRADING entry). Every kit rule reads a token with the number as its last fallback, and no `:root`
value is declared, so the cascade is unchanged; a host maps its own scale once (e.g.
`--nowo-ui-z-overlay: var(--app-z-overlay)`). The specific token wins over the scale token
(`var(--nowo-ui-side-panel-z, var(--nowo-ui-z-overlay, 1040))`).

| Layer | Token | Default | Used by |
|-------|-------|---------|---------|
| Shell aside | — | `30` (rail peek `45`, phone `60`) | `.nowo-ui-aside` (internal) |
| Shell header | — | `40` | `.nowo-ui-header` (internal) |
| Header menus | — | `50` | user menu, kebab, locale switcher (internal) |
| Chat dock (ChatKitBundle) | `--nowo-chat-z-index` | `60` | ChatKit's floating dock (documented by ChatKit) |
| Page-edge tabs | `--nowo-ui-edge-tab-z` | `74` | `.nowo-ui-edge-tab__link`; closed edge drawers |
| Skip link | — | `1000` | `.nowo-ui-skip-link` |
| Overlay | `--nowo-ui-z-overlay` (`--nowo-ui-side-panel-z` wins) | `1040` | side panel backdrop; the drawer is `+1` (`1041`) |
| Modal (non-top-layer) | `--nowo-ui-z-modal` (`--nowo-ui-menu-z-index` wins for menus) | `1050` | legacy Bootstrap `modal_shell`, `menu` popover fallback |
| Popovers over modals | `--nowo-ui-z-popover` (`--nowo-ui-date-range-z`, `--nowo-ui-multi-select-z` win) | `1080` | calendar, multi-select panel (inline fallbacks) |
| Emoji picker panel | `--nowo-ui-emoji-picker-z` (`--nowo-ui-z-popover` below it) | `1080` | the open panel without the Popover API; with it, the top layer |
| Sortable copy | `--nowo-ui-sortable-z` (`--nowo-ui-z-popover` below it) | `1080` | the dragged copy; inside a modal `<dialog>` it stays in the top layer with its parent |
| Toasts | `--nowo-ui-z-toast` | `1100` | `.nowo-ui-toast-stack` |
| Page loader | `--nowo-ui-z-loader` | `1200` | `.nowo-ui-page-loader` |

**Top layer.** A native `<dialog>` opened with `showModal()` (`dialog`, `confirm`, `remote_form_dialog`) and a
`[popover]` panel render in the browser's top layer, above every `z-index`, in opening order. The table applies to
everything else. Rule of thumb for host chrome: what must stay usable over an open drawer is top layer or ≥ `1050`;
what an open drawer must cover is below `1040`, or hides on `html[data-nowo-overlay]` (the overlay registry's root
marker, see [USAGE](USAGE.md#overlays-and-the-shell-228)).

## Dialog sizes (2.29)

Two sizes for every kit dialog (owner rule; markup and classes in
[PAGE_CONTRACT](PAGE_CONTRACT.md#dialog-sizes-and-anatomy-229)). The tokens are **shared by every nowo-tech kit**
(hence the `--nowo-dialog-*` prefix) and, like the layers, have **no `:root` value**: each rule reads them with the
default as fallback, so a host sets them once on `:root` and it wins whatever the load order.

| Token | Default | Used for |
|-------|---------|----------|
| `--nowo-dialog-width-md` | `min(40rem, 100vw - 2rem)` | medium (default): forms, choosers, steps, confirmations |
| `--nowo-dialog-height-md` | `min(36rem, 100dvh - 2rem)` | |
| `--nowo-dialog-width-wide` | `min(56rem, 100vw - 2rem)` | wide: tables, two-column forms, guides, the remote form dialog |
| `--nowo-dialog-height-wide` | `min(52rem, 100dvh - 2rem)` | |
| `--nowo-ui-dialog-w` / `--nowo-ui-dialog-h` | the size of the dialog (set by `.nowo-ui-dialog`, `.nowo-ui-confirm-dialog`, `.nowo-ui-confirm` and the `--lg` / `--xl` / `--wide` modifiers) | one dialog's resolved size; set them on a single dialog to override it |
| `--nowo-ui-dialog-max-h` | `min(var(--nowo-ui-dialog-h), 100dvh - 2rem)` (2.31) | the height cap of one dialog |
| `--nowo-ui-dialog-chrome-bg` | `color-mix(in srgb, var(--nowo-ui-text) 4%, var(--nowo-ui-surface))` (2.31, no `:root` value) | background of the dialog header and footer (`dialog`, `confirm_dialog` anatomy, `confirm`) |
| `--nowo-ui-dialog-fill` | unset = `0` (2.31; clamped to 0 – 1) | `1` = a dialog with a field, list, table or fold has the size's height (2.29 / 2.30); printed on `:root` by `theme.dialog.height: size` |

The width belongs to the size. **The height follows the content** (2.31, dialog rule v2): capped at
`--nowo-ui-dialog-max-h`, the body scrolls inside, header and footer stay. `nowo_ui_kit.theme.dialog.height: size`
(default `content`) keeps the 2.29 / 2.30 height instead; a plain yes / no confirmation (no field, list, table or fold)
keeps its natural height either way. At 390 px both sizes are the screen width minus the 16 px gutters.

```css
:root {
  --nowo-dialog-width-md: min(42rem, 100vw - 2rem); /* every kit's medium dialogs */
}
```

## Remote form dialog (2.28)

| Token | Default |
|-------|---------|
| `--nowo-ui-remote-dialog-phone-inset` | `1rem` since 2.29 (gutter around the dialog on phones, ≤ 40 rem; `0` = full screen, the 2.28 default) |
| `--nowo-ui-remote-dialog-phone-radius` | the modal radius since 2.29 (corner radius on phones; `0` with a zero inset) |

The status line and the alert use `--nowo-ui-muted` and `--nowo-ui-danger-text` (falls back to `--nowo-ui-danger`).

## Detail pages (2.21)

Split page-header actions (`header_actions` partial), the key figures row (`stat_row`) and the compact details grid
(`detail_list` with `modifier: 'facts'`). Every token falls back to the base tokens, so light and dark follow
`--nowo-ui-surface` / `--nowo-ui-border` / `--nowo-ui-text` / `--nowo-ui-muted` and the tone `*-text` tokens. The
square "more" toggle has no token of its own: it is painted by the `btn_secondary` + `btn_icon` slots, like the icon
buttons next to it.

| Token | Default |
|-------|---------|
| `--nowo-ui-header-actions-gap` | `0.35rem` (space between the icon buttons, the primary button and the menu) |
| `--nowo-ui-stat-min-width` | `6.25rem` (narrowest tile; the row auto-fits) |
| `--nowo-ui-stat-gap` | `0.5rem` |
| `--nowo-ui-stat-padding` | `0.6rem 0.85rem` |
| `--nowo-ui-stat-bg` | `var(--nowo-ui-surface)` |
| `--nowo-ui-stat-border` | `var(--nowo-ui-border)` |
| `--nowo-ui-stat-radius` | `var(--nowo-ui-radius)` |
| `--nowo-ui-stat-label-color` | `var(--nowo-ui-muted)` |
| `--nowo-ui-stat-value-size` | `1.375rem` (value; tones use `--nowo-ui-{success,warning,danger,info}-text`) |
| `--nowo-ui-detail-facts-min-width` | `10rem` (narrowest cell of the details grid) |
| `--nowo-ui-detail-facts-gap` | `0.6rem 1.25rem` |
| `--nowo-ui-detail-facts-padding` | `0` (set e.g. `0.75rem 1rem` with a border for a framed grid outside a card) |
| `--nowo-ui-detail-facts-border-width` | `0` (border of the grid, colour `--nowo-ui-border`) |

## Corner tabs, edge tabs, scrollbars (1.17)

Opt-in chrome ([USAGE.md — Host chrome](USAGE.md#host-chrome-117-opt-in)); not declared, resolved through fallbacks.

| Token | Default | Used by |
|-------|---------|---------|
| `--nowo-ui-corner-tab-width` | `1.5rem` | `.nowo-ui-corner-tab` (table mode toggle, filter fold, `_corner_tab`) |
| `--nowo-ui-corner-tab-height` | `1.375rem` | same |
| `--nowo-ui-corner-tab-icon` | `0.875rem` | corner tab icons |
| `--nowo-ui-corner-tab-accent` | `var(--nowo-ui-primary)` | corner tab text; border 35 % / background 10 % (hover 18 %) of it mixed with `--nowo-ui-border` / `--nowo-ui-surface` |
| `--nowo-ui-border-width` | `1px` | corner and edge tab borders (also emitted by the appearance SPI) |
| `--nowo-ui-edge-tab-top` | `calc(var(--nowo-ui-header-height, 3.5rem) + 0.75rem)` | `.nowo-ui-edge-tab__link` top of slot 0 |
| `--nowo-ui-edge-tab-step` | `3.25rem` | offset per `slot` |
| `--nowo-ui-edge-tab-slot` | `0` | set by `data-nowo-ui-edge-tab-slot` (1–3); a number, multiplied by the step |
| `--nowo-ui-edge-tab-z` | `74` | edge tab z-index ([Layers](#layers-public-api-228), public API since 2.28) |
| `--nowo-ui-scrollbar-size` | `6px` | thin scrollbars (`.nowo-ui-thin-scroll`, `html[data-nowo-ui-thin-scrollbars]`) |
| `--nowo-ui-scrollbar-thumb` | `color-mix(in srgb, var(--nowo-ui-text) 28%, transparent)` | thumb (and Firefox `scrollbar-color`) |
| `--nowo-ui-scrollbar-thumb-hover` | `color-mix(in srgb, var(--nowo-ui-text) 45%, transparent)` | thumb on hover |

The row detail dialog uses the confirm dialog chrome (`--nowo-ui-modal-radius`, `--nowo-ui-shadow-dialog`,
`--nowo-ui-overlay`); its detail list labels use `--nowo-ui-muted`.

## Phone mode (2.41)

From Beacon's mobile UX audit (2026-11-09, 390 px, iPhone / WebKit).

| Token | Default | Used by |
|-------|---------|---------|
| `--nowo-ui-control-size-touch` | `2.75rem` (44px) under `pointer: coarse`, unset elsewhere | minimum height of icon buttons, tabs, pagination links, sort headers, row detail buttons, the burger (and minimum width of the square ones); `0px` opts out |
| `--nowo-ui-font-size-min` | `0.75rem` | floor of badges and the kit's meta labels on phones (up to 40rem) and touch screens; other bundles read it for their meta text |
| `--nowo-ui-select-chevron-gutter` | `2rem` (`1.75rem` on the pagination select) | end padding of `.nowo-ui-select` / kit selects, where the `currentColor` chevron sits |
| `--nowo-ui-edge-tabs-gutter` | `2.25rem` while the edge tabs are folded with `gutter`, else unset | right padding added to `.nowo-ui-main`; read it with `var(--nowo-ui-edge-tabs-gutter, 0px)` in a host container |

Select geometry: `.nowo-ui-select` (opt-in on any `<select>`), `select.nowo-ui-input` and the pagination select use
`appearance: none`, line-height 1.25 and the gradient chevron. Use `background-color`, not the `background` shorthand,
in host CSS for them. On iOS (`@supports (-webkit-touch-callout: none)`) kit fields are at least 1rem.

## Default-theme polish tokens (2.2)

UiKit 2.2 polishes the default theme of the page scaffolds. Every new rule reads a token with a fallback, so hosts
only set what they want to change.

| Token | Default | Used by |
|-------|---------|---------|
| `--nowo-ui-danger-solid` | light `#b91c1c`, dark `#ef4444` (token blocks) | solid destructive button |
| `--nowo-ui-danger-contrast` | light `#ffffff`, dark `#0b1220` (token blocks) | text on the solid destructive button |
| `--nowo-ui-btn-danger-solid-bg` | `var(--nowo-ui-danger-solid)` | slot `btn_danger_solid` (confirm button of a danger `Confirm`) |
| `--nowo-ui-btn-danger-solid-text` | `var(--nowo-ui-danger-contrast)` | idem |
| `--nowo-ui-btn-danger-solid-hover-bg` | `color-mix(in srgb, var(--nowo-ui-danger-solid) 85%, var(--nowo-ui-text))` | idem, hover (kit-painted stacks) |
| `--nowo-ui-confirm-width` | `min(28rem, calc(100vw - 2rem))` (`--sm` 22rem, `--lg` 40rem) | confirm `<dialog>` width (`Confirm::$size`) |
| `--nowo-ui-confirm-text` | `var(--nowo-ui-text)` | confirm message (≥ 4.5:1 on the surface) |
| `--nowo-ui-badge-offset` | `0.35rem` (`0` when the badge starts a cell, value, KPI, card header or list item) | badge left margin (1.x inline badges) |
| `--nowo-ui-badge-primary-bg` | `color-mix(in srgb, var(--nowo-ui-primary) 12%, var(--nowo-ui-surface))` | `badge_info`, `badge_primary` |
| `--nowo-ui-badge-primary-border` | `color-mix(in srgb, var(--nowo-ui-primary) 35%, var(--nowo-ui-surface))` | idem |
| `--nowo-ui-badge-primary-text` | `color-mix(in srgb, var(--nowo-ui-primary) 75%, var(--nowo-ui-text))` | idem |
| `--nowo-ui-table-head-size` | `0.75rem` | scaffold table header cells (`table_head_cell`) |
| `--nowo-ui-table-head-weight` | `600` | idem |
| `--nowo-ui-table-head-tracking` | `0.04em` | idem |
| `--nowo-ui-table-head-transform` | `uppercase` (`none` for sentence case) | idem |
| `--nowo-ui-table-head-border` | `var(--nowo-ui-border-strong)` | idem, bottom border |
| `--nowo-ui-table-head-border-width` | `2px` | idem |
| `--nowo-ui-filters-field-min` | `12rem` | list filters grid: minimum field width (`auto-fit`) |
| `--nowo-ui-filters-gap` | `0.75rem 1rem` | list filters grid gap |
| `--nowo-ui-filters-actions-span` | `1` (`2` with header actions in the filter row, ≥ 48rem) | columns of the filter actions cell |
| `--nowo-ui-kpi-accent-width` | `0` | KPI top accent (inset shadow: follows the tile radius), e.g. `4px` |
| `--nowo-ui-kpi-accent` | `var(--nowo-ui-primary)` | KPI top accent colour |
| `--nowo-ui-kpi-radius` | `var(--nowo-ui-radius)` | KPI tile radius |
| `--nowo-ui-help-text` | `var(--nowo-ui-muted)` | form help text (`.help-text`, `.form-text`, `#…_help`) |
| `--nowo-ui-form-row-gap` | `1rem` | space between stacked form rows (scaffold forms, dialogs): the `gap` of the `form` slot's flex column (2.5; was a sibling margin) |
| `--nowo-ui-form-actions-gap` *(2.5)* | `1rem` | space between the last form row and the `form_actions` row |
| `--nowo-ui-form-label-gap` *(2.5)* | `0.5rem` | label → control (`.form-label`, `.nowo-ui-form__label` inside UiKit forms) |
| `--nowo-ui-form-column-gap` *(2.5)* | `1rem` | between side-by-side fields (slot `form_columns`) |
| `--nowo-ui-form-column-min` *(2.5)* | `12rem` | minimum column width of `form_columns` (auto-fit grid) |
| `--nowo-ui-content-padding-block` *(2.3)* | `1rem` | vertical padding of the content region (slot `content`; was on `<body>` before 2.3; `0` inside the 1.x shell main) |
| `--nowo-ui-auth-panel-width` *(2.3)* | `28rem` | auth scaffold panel: max width (centred, `min(token, 100%)`) |
| `--nowo-ui-menu-z-index` *(2.3)* | `1050` | `menu` partial: stacking of the floating panel / popover |
| `--nowo-ui-menu-min-width` *(2.3)* | `12rem` | `menu` partial: minimum panel width |
| `--nowo-ui-menu-max-width` *(2.3)* | `22rem` | `menu` partial: maximum panel width (capped to the viewport) |
| `--nowo-ui-tabs-secondary-gap` | `0.35rem` | second tab level (slot `tabs_secondary`) |
| `--nowo-ui-tab-pill-radius` | `999px` | idem, pill radius |
| `--nowo-ui-tab-pill-hover-bg` | `var(--nowo-ui-surface-muted)` | idem, hover |
| `--nowo-ui-tab-pill-active-bg` | `color-mix(in srgb, var(--nowo-ui-primary) 12%, var(--nowo-ui-surface))` | idem, current section |
| `--nowo-ui-tab-pill-active-border` | `color-mix(in srgb, var(--nowo-ui-primary) 35%, var(--nowo-ui-surface))` | idem |
| `--nowo-ui-tab-pill-active-text` | `color-mix(in srgb, var(--nowo-ui-primary) 75%, var(--nowo-ui-text))` | idem |
| `--nowo-ui-progress-height` | `0.5rem` | `progress` partial (native `<progress>`, slot `progress_meter`; track / bar: `--nowo-ui-progress-track` / `--nowo-ui-progress-bar`) |
| `--nowo-ui-date-range-z` *(2.15)* | `1080` | date-range field: stacking of the calendar panel (inline fallback without the Popover API) |
| `--nowo-ui-date-range-shadow` *(2.15)* | `var(--nowo-ui-shadow-popover)` | date-range field: calendar panel shadow |
| `--nowo-ui-date-range-day-size` *(2.15)* | `2.25rem` (`2.1rem` below 30rem) | date-range field: day cell size of the calendar |
| `--nowo-ui-date-range-range-bg` *(2.15)* | `color-mix(in srgb, var(--nowo-ui-primary) 18%, var(--nowo-ui-surface))` | date-range field: days between the two ends of the range |

**One primary colour.** Links inside UiKit regions (`--nowo-ui-link`), the `info` / `primary` badges, the second tab
level, focus rings and the active pagination link all derive from `--nowo-ui-primary`. Third-party widgets follow
with an optional stylesheet: `css/nowo-ui-tom-select.css` paints Tom Select chips, the active option and the focus
ring from the same token (load it after Tom Select's CSS; overrides `--nowo-ui-chip-bg`, `--nowo-ui-chip-border`,
`--nowo-ui-chip-text`, `--nowo-ui-chip-radius`):

```twig
<link rel="stylesheet" href="{{ asset('css/nowo-ui-tom-select.css', 'nowo_ui_kit') }}">
```

## Layout and metrics tokens

Unchanged, listed for completeness: `--nowo-ui-aside-width`, `--nowo-ui-aside-rail-width` *(1.15, icons-only
rail width, default `3.75rem`)*, `--nowo-ui-aside-duration`,
`--nowo-ui-aside-ease`, `--nowo-ui-header-height`, `--nowo-ui-content-max-width`,
`--nowo-ui-control-height`, `--nowo-ui-control-height-compact`, `--nowo-ui-filter-control-height`,
`--nowo-ui-orb-size`, `--nowo-ui-page-loader-corner-bottom`, `--nowo-ui-page-loader-corner-left`.

App shell tokens added in 2.32: `--nowo-ui-aside-peek-width`, `--nowo-ui-aside-brand-icon-size`,
`--nowo-ui-shell-inset-start` (see [Full-height sidebar layout](#full-height-sidebar-layout-232)).

Since 2.11 `--nowo-ui-control-height` also sets the minimum height of `.nowo-ui-btn` and `.nowo-ui-input` (it used
to feed the filter rows only). See [Density tokens](#density-tokens-211) for the per-component metrics.

<a id="density-tokens-211"></a>

## Density tokens (2.11)

Re-audit OVR-11 from Symfony Beacon: component metrics a host used to restate in its own CSS. Every token is optional:
unset, the component keeps its 2.10 metrics (a token listed as *unset* is read without a fallback, so it changes nothing
until set).

| Token | Default | Used by |
|-------|---------|---------|
| `--nowo-ui-btn-py` / `--nowo-ui-btn-px` | `0.35rem` / `0.75rem` | `.nowo-ui-btn` padding |
| `--nowo-ui-btn-font-size` | `0.875rem` | `.nowo-ui-btn` |
| `--nowo-ui-btn-font-weight` | *unset* (inherited) | `.nowo-ui-btn` |
| `--nowo-ui-btn-justify` | *unset* | `.nowo-ui-btn` content alignment (e.g. `center`) |
| `--nowo-ui-btn-sm-size` | `auto` | `.nowo-ui-btn--sm` min height; squares `.nowo-ui-btn--sm.nowo-ui-btn--icon` |
| `--nowo-ui-btn-sm-py` / `--nowo-ui-btn-sm-px` | `0.2rem` / `0.5rem` | `.nowo-ui-btn--sm` padding |
| `--nowo-ui-btn-sm-font-size` | `0.8125rem` | `.nowo-ui-btn--sm` |
| `--nowo-ui-input-py` / `--nowo-ui-input-px` | `0.35rem` / `0.6rem` | `.nowo-ui-input` padding |
| `--nowo-ui-input-font-size` | *unset* (inherited) | `.nowo-ui-input` |
| `--nowo-ui-badge-py` / `--nowo-ui-badge-px` | `0.1rem` / `0.45rem` | `.nowo-ui-badge` padding |
| `--nowo-ui-badge-min-height` | `auto` | `.nowo-ui-badge` |
| `--nowo-ui-badge-font-size` | `0.75rem` | `.nowo-ui-badge` |
| `--nowo-ui-badge-font-weight` / `--nowo-ui-badge-letter-spacing` | *unset* | `.nowo-ui-badge` |
| `--nowo-ui-badge-bg` / `--nowo-ui-badge-border` / `--nowo-ui-badge-text` | `var(--nowo-ui-surface-muted)` / `var(--nowo-ui-border)` / `var(--nowo-ui-text)` | neutral badge |
| `--nowo-ui-badge-success-bg` / `--nowo-ui-badge-success-border` / `--nowo-ui-badge-success-text` | the `--nowo-ui-success-*` palette | `.nowo-ui-badge--success` |
| `--nowo-ui-badge-warning-bg` / `--nowo-ui-badge-warning-border` / `--nowo-ui-badge-warning-text` | the `--nowo-ui-warning-*` palette | `.nowo-ui-badge--warning` |
| `--nowo-ui-badge-danger-bg` / `--nowo-ui-badge-danger-border` / `--nowo-ui-badge-danger-text` | the `--nowo-ui-danger-*` palette | `.nowo-ui-badge--danger`, `--error` |
| `--nowo-ui-table-head-font-weight` | `bold` | `.nowo-ui-table > thead > tr > th` |
| `--nowo-ui-table-head-letter-spacing` | *unset* | `.nowo-ui-table` header (with `--nowo-ui-table-head-transform`, `--nowo-ui-table-head-font-size`) |
| `--nowo-ui-pagination-size` | `2rem` wide, `auto` tall | `.nowo-ui-pagination__link` min width / height (the pagination item size) |
| `--nowo-ui-pagination-gap` | `0.25rem` | 2.33: gap between the pagination items (`.nowo-ui-pagination__nav`, its `ul.pagination`) |
| `--nowo-ui-row-actions-gap` | `0.25rem` | 2.33: gap between the row actions (`.nowo-ui-row-actions`) |
| `--nowo-ui-row-action-size` | *unset* | 2.33: icon-only row actions (`.nowo-ui-row-actions--icon .nowo-ui-action.nowo-ui-btn--sm`) as squares of this size |
| `--nowo-ui-morph-size` | `1em` | 2.33: the morph-icon box of a [morph swap](#morph-swap-233) |
| `--nowo-ui-pagination-py` / `--nowo-ui-pagination-px` | `0.25rem` / `0.5rem` | `.nowo-ui-pagination__link` padding |
| `--nowo-ui-pagination-font-size` | `0.875rem` | `.nowo-ui-pagination__link` |
| `--nowo-ui-pagination-font-weight` / `--nowo-ui-pagination-line-height` | *unset* | `.nowo-ui-pagination__link` |
| `--nowo-ui-tab-py` / `--nowo-ui-tab-px` | `0.5rem` / `0.75rem` | `.nowo-ui-tabs__item` padding |
| `--nowo-ui-tab-min-height` | `auto` | `.nowo-ui-tabs__item` |
| `--nowo-ui-tab-font-size` / `--nowo-ui-tab-font-weight` | *unset* | `.nowo-ui-tabs__item` |
| `--nowo-ui-tooltip-bg` / `--nowo-ui-tooltip-text` | `var(--nowo-ui-text)` / `var(--nowo-ui-surface)` | tab label tooltip with `data-tab-style="icon"` (2.16) |

The table cell metrics (`--nowo-ui-table-cell-py`, `--nowo-ui-table-cell-px`, `--nowo-ui-table-font-size`) now also
apply to `.nowo-ui-table` (they were read by `.nowo-ui-data-table` only).

**Pill tabs.** Add `nowo-ui-tabs--pill` to the `tabs` slot (`theme.classes.tabs`): each tab is a bordered pill
(`--nowo-ui-tab-radius`, active border `--nowo-ui-tab-active-border`) instead of the underline.

### Morph swap (2.33)

A host toggle that pairs a static icon with a `<morph-icon>` (theme toggle, sidebar burger of its own…) gets the kit's
swap without its own `display: … !important`: class `nowo-ui-morph-swap` on the toggle, `nowo-ui-morph-swap__static`
on the static icon(s), `nowo-ui-morph-swap__morph` on the `<morph-icon>`. Before hydration only the static icon
paints; once the morphicons helpers add `is-morph-ready`, only the morph-icon (a real `inline-flex` box of
`--nowo-ui-morph-size`). The rules use `!important` inside the `nowo-ui` layer, which beats unlayered host
`!important`, so a host keeps only its own state rules (which static icon shows for which state).

```html
<button type="button" class="app-theme-toggle nowo-ui-morph-swap">
  <svg class="nowo-ui-morph-swap__static app-icon--moon">…</svg>
  <morph-icon class="nowo-ui-morph-swap__morph" data-theme-morph aria-hidden="true"></morph-icon>
</button>
```

## CSS framework notes

- **custom / none:** `nowo-ui.css` paints everything from the tokens.
- **tailwind:** macros add layout, spacing and type-scale utilities only (`inline-flex`, `px-3`,
  `text-sm`, `border` width…). Colours, radii and dividers come from the semantic classes. Because
  Tailwind v4 utilities live in `@layer utilities`, the unlayered `nowo-ui.css` also wins any overlap.
- **bootstrap5 / bootstrap4 / tabler:** macros keep Bootstrap's variant classes (`btn-primary`,
  `btn-outline-*`, `alert-*`, `badge bg-*`, `text-muted`, `table`, `list-group`, `pagination`,
  `nav-tabs`), so the default look is Bootstrap's. Add the opt-in class **`nowo-ui-bs-tokens`** to an
  ancestor (`<body>`, the panel root) to paint those Bootstrap 5.3 components from the same tokens
  (it sets the components' `--bs-*` variables; badge colour utilities are answered with scoped
  `!important`).
- **foundation:** Foundation's semantic classes (`button primary`, `callout success`) stay; colour them
  through Foundation's settings or the semantic `nowo-ui-*` classes.

## Example: generic brand

```css
html:root {
  --nowo-ui-primary: #0f766e;
  --nowo-ui-primary-contrast: #ffffff;
  --nowo-ui-surface-muted: #f5f5f4;
  --nowo-ui-border: #e7e5e4;
  --nowo-ui-border-strong: #d6d3d1;
  --nowo-ui-text: #1c1917;
  --nowo-ui-muted: #78716c;
  --nowo-ui-radius: 0.5rem;
  --nowo-ui-font-display: "Inter Display", system-ui, sans-serif;
}

html:root[data-theme="dark"] {
  --nowo-ui-primary: #2dd4bf;
  --nowo-ui-primary-contrast: #042f2e;
  --nowo-ui-surface: #1c1917;
  --nowo-ui-surface-muted: #292524;
  --nowo-ui-border: #44403c;
  --nowo-ui-border-strong: #57534e;
  --nowo-ui-text: #f5f5f4;
  --nowo-ui-muted: #a8a29e;
}
```

## Example: Symfony Beacon (moss / sand)

Beacon defines its palette once (`assets/styles/tailwind.css`): `--color-moss` (`#1f6f54` / dark
`#4aad7f`), `--color-moss-deep`, `--color-sand` (`#d8e2dc` / `#2a3832`), `--color-mist`,
`--color-ink` (`#0f1c18` / `#e6eee9`), `--color-surface` (`#ffffff` / `#151c19`), `--color-alert`,
`--font-display` (Montserrat), and the `--beacon-flash-*` pairs; all of them switch under
`[data-theme="dark"]`. The whole kit then themes from one block — no `.kit-admin` colour remap, no
neutralising of Tailwind `slate` / `blue` utilities:

```css
html:root {
  --nowo-ui-primary: var(--color-moss);
  --nowo-ui-primary-hover: var(--color-moss-deep);
  --nowo-ui-link-hover: var(--color-moss-deep);
  --nowo-ui-surface: var(--color-surface);
  --nowo-ui-surface-muted: var(--color-mist);
  --nowo-ui-border: var(--color-sand);
  --nowo-ui-border-strong: var(--color-sand);
  --nowo-ui-text: var(--color-ink);
  --nowo-ui-muted: color-mix(in srgb, var(--color-ink) 55%, transparent);
  --nowo-ui-danger: var(--color-alert);
  --nowo-ui-danger-text: var(--color-alert);
  --nowo-ui-danger-text-strong: var(--color-alert);
  --nowo-ui-danger-bg: var(--beacon-flash-error-bg);
  --nowo-ui-danger-border: var(--beacon-flash-error-border);
  --nowo-ui-success-bg: var(--beacon-flash-ok-bg);
  --nowo-ui-success-border: var(--beacon-flash-ok-border);
  --nowo-ui-success-text: var(--beacon-flash-ok-text);
  --nowo-ui-font-display: var(--font-display), ui-sans-serif, system-ui, sans-serif;
  --nowo-ui-heading-weight: 600;
  --nowo-ui-modal-radius: 0.75rem;
  --nowo-ui-badge-radius: 0.375rem;
  --nowo-ui-focus-ring-color: color-mix(in srgb, var(--color-moss) 20%, transparent);
  --nowo-ui-btn-bg: transparent;
  --nowo-ui-btn-hover-bg: var(--color-surface);
  --nowo-ui-btn-hover-border: color-mix(in srgb, var(--color-moss) 40%, var(--color-sand));
  --nowo-ui-btn-danger-bg: transparent;
  --nowo-ui-btn-danger-border: color-mix(in srgb, var(--color-alert) 55%, var(--color-sand));
  --nowo-ui-btn-danger-hover-bg: color-mix(in srgb, var(--color-alert) 12%, var(--color-surface));
  --nowo-ui-table-border: color-mix(in srgb, var(--color-sand) 85%, transparent);
  --nowo-ui-table-head-text: color-mix(in srgb, var(--color-ink) 55%, transparent);
  --nowo-ui-table-hover-bg: color-mix(in srgb, var(--color-mist) 55%, transparent);
  --nowo-ui-table-stripe-bg: color-mix(in srgb, var(--color-mist) 55%, transparent);
  --nowo-ui-list-border: transparent;
  --nowo-ui-list-item-border: color-mix(in srgb, var(--color-sand) 85%, transparent);
  --nowo-ui-list-item-hover-bg: color-mix(in srgb, var(--color-mist) 70%, transparent);
  --nowo-ui-tabs-border: transparent;
  --nowo-ui-tab-radius: 0.375rem;
  --nowo-ui-tab-text: color-mix(in srgb, var(--color-ink) 70%, transparent);
  --nowo-ui-tab-hover-bg: color-mix(in srgb, var(--color-sand) 35%, transparent);
  --nowo-ui-tab-active-bg: color-mix(in srgb, var(--color-moss) 12%, var(--color-surface));
  --nowo-ui-tab-active-text: var(--color-moss-deep);
  --nowo-ui-tab-active-weight: 500;
  --nowo-ui-pagination-hover-border: var(--color-moss);
  --nowo-ui-pagination-hover-text: var(--color-moss);
  --nowo-ui-pagination-hover-bg: var(--color-surface);
  --nowo-ui-pagination-active-bg: color-mix(in srgb, var(--color-moss) 12%, var(--color-surface));
  --nowo-ui-pagination-active-border: var(--color-moss);
  --nowo-ui-pagination-active-text: var(--color-moss);
  --nowo-ui-pagination-active-weight: 600;
}

html:root[data-theme="dark"] {
  --nowo-ui-primary-contrast: #0c1210;
}
```

The first 17 lines are the palette mapping; the rest only reproduces Beacon's existing look (ghost
secondary buttons, moss-tinted pills, sand table rules) and can be dropped for the kit defaults. What
stays in the host is product layout: control heights / paddings, Beacon's `<dialog>` confirm-panel
chrome, pill borders on all four sides of the active tab, toolbar and panel spacing.

## Appearance SPI

Since **1.16.0** (opt-in, `nowo_ui_kit.appearance.enabled: true`, see
[CONFIGURATION.md](CONFIGURATION.md#appearance)). Instead of a static host theme, an admin picks the site look:
a light and a dark **palette** of seven colours (accent, accent deep, danger, warn, paper, ink, surface), a **corner
style** (`sharp` / `soft` / `rounded`) and a **border strength** (`subtle` / `medium` / `strong`). The kit turns it
into tokens and prints them:

```twig
<head>
    {% include '@NowoUiKitBundle/partials/_theme_boot.html.twig' %}
    <link rel="stylesheet" href="{{ asset('css/nowo-ui.css', 'nowo_ui_kit') }}">
    {{ nowo_ui_appearance_style() }}   {# <style nonce="…" data-nowo-ui-appearance>…</style>, after nowo-ui.css #}
    <title>{{ nowo_ui_appearance().brandName }}</title>
```

Twig functions: `nowo_ui_appearance()` (the `Appearance` value object: `brandName`, `brandEyebrow`, `light` / `dark`
palettes, `themeId` / `themeIdDark`, `cornerStyle`, `borderStrength`, `footerFixed`, 2.40 `logos`), `nowo_ui_appearance_css()`
(stylesheet text), `nowo_ui_appearance_style(nonce = null)` (the nonce defaults to the request attribute
`csp_nonce`), `nowo_ui_appearance_presets(mode = null)`.

Stored colours are sanitised (hex, CSS colour functions, named colours); anything else falls back to the default
palette, so admin input can never break out of the `<style>` element.

**With the page contract's `theme.tokens`** both sources set `--nowo-ui-*`. Precedence (2.0): `theme.tokens` /
`tokens_dark` are the **host defaults**, the appearance is the **user's choice** and overrides them. Both are printed
with the same specificity (`:root` / `[data-theme="dark"]`), so the order decides: print the theme tokens first and
the appearance last — `{{ nowo_ui_tokens() }}` does exactly that (the UiKit document layout uses it). With custom
`selectors` of higher specificity the appearance wins regardless of order. Since 2.15.4 the palette entries of
`theme.tokens` (those with a UiKit dark default) are printed on `:root:where(:not([data-theme=dark]))` — still the
specificity of `:root` — so they apply in light mode only and dark mode keeps the UiKit dark defaults unless
`tokens_dark` sets them ([THEME_SLOTS](THEME_SLOTS.md#tokens)).

### Emitted tokens

Root block (`selectors.root`, default `:root`):

| Token | Value |
|-------|-------|
| `--nowo-ui-radius` | control radius: sharp `0.0625rem`, soft `0.375rem`, rounded `0.5rem` |
| `--nowo-ui-card-radius` | card radius: sharp `0.125rem`, soft `0.75rem`, rounded `1.25rem` |
| `--nowo-ui-border-width` | `1px` (subtle / medium), `1.5px` (strong) — not read by `nowo-ui.css`; for host CSS |

Light block (`selectors.light`, default `:root, [data-theme="light"]`) and dark block (`selectors.dark`, default
`[data-theme="dark"]`), each from its own palette. *m* is the border mix: subtle 7 / 9, medium 14 / 16, strong
26 / 30 (light / dark).

| Token | Value |
|-------|-------|
| `--nowo-ui-primary` | accent |
| `--nowo-ui-primary-deep` | accent deep *(new; for host CSS)* |
| `--nowo-ui-primary-hover` | accent deep |
| `--nowo-ui-primary-contrast` | surface or ink, whichever contrasts more with the accent (hex colours only) |
| `--nowo-ui-danger` | danger |
| `--nowo-ui-warning` | warn *(new; for host CSS)* |
| `--nowo-ui-paper` | paper (page background) *(new; for host CSS)* |
| `--nowo-ui-text` | ink |
| `--nowo-ui-surface` | surface |
| `--nowo-ui-border`, `--nowo-ui-border-strong` | `color-mix(in srgb, ink m%, paper)` |
| `--nowo-ui-accent-soft` | `color-mix(in srgb, accent 10%, paper)` *(new)* |
| `--nowo-ui-surface-muted` | `color-mix(in srgb, ink 5%, surface)` |
| `--nowo-ui-paper-accent` | `color-mix(in srgb, accent 8%, paper)` *(new; shell gradients)* |
| `--nowo-ui-paper-ink` | `color-mix(in srgb, ink 6%, paper)` *(new; shell gradients)* |
| `--nowo-ui-muted` | `color-mix(in srgb, ink 60%, surface)` |
| `--nowo-ui-brand-mark` | accent *(new)* |
| `--nowo-ui-shadow-rgb` | light: ink as `r g b` channels (`shadow_rgb_fallback` if not hex); dark: `0 0 0` — use as `rgb(var(--nowo-ui-shadow-rgb) / 0.12)` |

The default selectors have the same specificity as the `nowo-ui.css` token blocks: print the style **after** the
kit stylesheet (or set `selectors` to `html:root` / `html:root[data-theme="dark"]`). A host theme written as
`html:root { … }` (see above) still wins over the emitted values.

### Host storage and presets

- Implement `Nowo\UiKitBundle\Appearance\AppearanceProviderInterface::getAppearance(): Appearance` on top of your
  storage (entity, settings row…) and set `appearance.provider` to that service. Without one,
  `ConfigAppearanceProvider` serves `appearance.defaults`.
- `AppearancePresetRegistry`: the presets in display order — config `presets`, then services implementing
  `AppearancePresetProviderInterface` (autoconfigured tag `nowo_ui_kit.appearance_preset_provider`), then the
  built-ins **ocean**, **slate**, **sandstone** (light) and **midnight**, **obsidian**, **aurora**, **ember** (dark),
  restricted with `builtin_presets`. `apply($appearance, $id)` returns a copy with the preset's half applied (a light
  preset replaces the light colours and `themeId`, a dark one the dark colours and `themeIdDark`);
  `matchLightId()` / `matchDarkId()` recognise a stored palette (`custom` otherwise).
- `Appearance::toArray()` / `Appearance::fromArray($data, $defaults)` bridge to forms and storage. With
  `symfony/form` installed, `Nowo\UiKitBundle\Form\AppearanceType` (options `with_brand`, `with_colors`,
  `with_footer`) edits that array; colours are validated when `symfony/validator` is installed. The admin
  controller and persistence stay in the host.

### Brand and logos (2.40)

Spec 220 slice S1. `Appearance` has an optional `logos` (`AppearanceLogos`: `lightSrc`, `darkSrc`, `markSrc`,
`faviconSrc`, `appleTouchSrc`, `wordmark`). They are **URLs**, each passed through `SafeHref::filterImage()` (relative,
`http`, `https`; anything else is dropped), and the kit prints them only as `<img src>` / `<link href>`: never inlined,
so an SVG logo cannot run script in the page. They are not in `toArray()`; `fromArray()` keeps the logos of its
defaults. With `logos` null (the `ConfigAppearanceProvider`, and every host provider until it sets them) every template
renders the 2.39 markup. An `AppearanceLogos` whose every URL was filtered out counts as none: the shell keeps
`shell.brand.name` (a brand without images included).

**Who sets them.** A host provider (`new Appearance(…, logos: new AppearanceLogos('/brand/logo.svg', …))`), or the
brand contract: with `appearance.provider: null`, `appearance.brand: auto` (the default) and a
`Nowo\Brand\Contract\BrandProviderInterface` service (`composer require nowo-tech/brand-contracts`, a 20-line class
or InstanceSettings' brand section), `BrandAppearanceProvider` maps the `Brand`: name → `brandName`, eyebrow (a
translatable one in the current locale) → `brandEyebrow`, `light` / `dark` colours (primary → accent, primaryDeep →
accent deep, warning → warn, text → ink, the rest by name), and the slots after the contract's fallback chain
(`logo_light`, `logo_dark` → light, `mark` → `app_icon`, `favicon` → mark → app icon, `app_icon` → apple touch).
Corner, border and footer stay from `defaults`; a brand provider that throws is logged and `defaults` is served.

**Where they show** (precedence: page options > appearance logos > `shell.brand` config):

- App shell header and the full-height aside: when the page passes neither `brand_html` nor `brand`, the name defaults
  to the appearance brand name (else `shell.brand.name`), the icon to `markSrc` (else `shell.brand.icon_src`), and
  without `brand_icon_src` / `brand_icon_html` the lockup replaces the name text:
  `<img class="nowo-ui-brand__logo" data-nowo-ui-theme-only="light|dark" alt="{name}">` (one `<img>` without a separate
  dark logo). The kit CSS hides the image of the other `[data-theme]` (no script). In the icons-only rail the mark
  shows instead (class `nowo-ui-aside-brand__icon--rail`; the name's initial without a mark).
- Auth scaffold: the panel brand prints the lockup (alt = `page.brand`, else the appearance name); without logos,
  `page.brand` as text as before. 2.44: `nowo_ui_kit.auth_panel.brand: none` (or `nowo_ui_auth_brand = 'none'`) leaves
  the region out ([CONFIGURATION](CONFIGURATION.md#auth-panel-244)).
- Guest layout (block `nowo_ui_guest_brand`, emptied by the auth scaffold) and error layout (block
  `nowo_ui_error_brand`, top of `<main>`): `<div class="nowo-ui-brand-region nowo-ui-brand-region--guest|error"
  data-nowo-ui-region="brand">` with the lockup, only when there is one. The error layout reads the appearance through
  `nowo_ui_appearance_or_null()`, which returns null when the provider throws, so an error page with a broken database
  renders its config chrome (no 500 inside a 500). Since 2.40 the `nowo_ui_appearance*()` functions and `nowo_ui_tokens()` behave the same way: a provider exception is logged and the config `defaults` are used.

Tokens: `--nowo-ui-brand-logo-height` (lockup height in the shell, default `1.75rem`),
`--nowo-ui-brand-region-logo-height` (guest, error and auth panel, default `2.5rem`). Logos are markup, never a
`url()` in a custom property.

**Head links (opt-in).** `nowo_ui_appearance_head()` returns `<link rel="icon">`, `<link rel="apple-touch-icon">` (when
the logos have them) and `<meta name="theme-color" content="{paper}" media="(prefers-color-scheme: light|dark)">`,
escaped, for host layouts. The kit document and error layouts print it after the viewport meta only with
`appearance.head_links: true` (`nowo_ui_appearance_head_links()`).

**Token lint.** `vendor/bin/nowo-ui-token-lint [--allow=<glob>]... [--no-default-allow] [<path>...]` (default path
`src/Resources`) reports colour literals (`#rgb`, `#rrggbb`, `#rrggbbaa`, `rgb(`, `rgba(`) in `*.css`, `*.scss` and
`*.twig` that should read a `--nowo-ui-*` token. Allowed: the fallback of `var(--…, …)`, `rgb(var(--nowo-ui-…) / …)`,
comments, a line holding `nowo-ui-token-lint-ignore`, and `nowo-ui.css` / `nowo-ui.unlayered.css`. Exit 1 on findings.
A missing path exits 2; `make token-lint` runs it on `src/Resources` without the demo views. It is a heuristic (an id selector spelling a hex colour is reported too); bundles add it to CI in their next sweep, not
as a release blocker.

**Within one request** the appearance is read once per main request (memo, cleared on `kernel.reset`): a controller that saves a new appearance and renders in the same request shows the old values; redirect after saving, or call `AppearanceView::reset()` (audit IS-I9).

### Keeping legacy variable names (`token_aliases`)

`token_aliases` writes extra custom properties with the **same literal value** as a kit token (never
`var(--nowo-ui-…)`, so a host that also maps `--nowo-ui-*` onto its own names cannot create a cycle). With
`kit_tokens: false` only the aliases are written. This reproduces, declaration for declaration, the stylesheet
Symfony Beacon emitted before adopting the SPI (`--beacon-*` + Tailwind `--color-*`; guarded by
`AppearanceCssEmitterTest`):

```yaml
nowo_ui_kit:
    appearance:
        enabled: true
        provider: App\Shared\Appearance\SiteAppearanceProvider
        kit_tokens: false
        shadow_rgb_fallback: '15 28 24'
        token_aliases:
            '--nowo-ui-card-radius': ['--beacon-radius-card']
            '--nowo-ui-radius': ['--beacon-radius-control']
            '--nowo-ui-border-width': ['--beacon-border-width']
            '--nowo-ui-primary': ['--beacon-moss', '--color-moss']
            '--nowo-ui-primary-deep': ['--beacon-moss-deep', '--color-moss-deep']
            '--nowo-ui-danger': ['--beacon-alert', '--color-alert']
            '--nowo-ui-warning': ['--beacon-warn', '--color-warn']
            '--nowo-ui-paper': ['--beacon-paper', '--color-paper']
            '--nowo-ui-text': ['--beacon-ink', '--color-ink']
            '--nowo-ui-surface': ['--beacon-surface', '--color-surface']
            '--nowo-ui-border': ['--beacon-sand', '--color-sand']
            '--nowo-ui-accent-soft': ['--beacon-mist', '--color-mist']
            '--nowo-ui-surface-muted': ['--beacon-surface-muted', '--color-surface-muted']
            '--nowo-ui-paper-accent': ['--beacon-shell-a']
            '--nowo-ui-paper-ink': ['--beacon-shell-b']
            '--nowo-ui-brand-mark': ['--beacon-mark']
            '--nowo-ui-shadow-rgb': ['--beacon-shadow']
```

Dropping `kit_tokens: false` later also emits the `--nowo-ui-*` tokens, which makes most of the
[Beacon mapping block](#example-symfony-beacon-moss--sand) redundant.
