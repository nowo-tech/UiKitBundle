# Feature Specification: Detail page patterns and font preload

**Feature Branch**: `009-detail-page-patterns`  
**Created**: 2026-11-01 (backfill)  
**Status**: Implemented (2.21.0, PR #42)  
**Input**: Symfony Beacon UI standardisation — status badge next to the title, split header actions, key figures, the
compact details grid; Beacon KIT #24 — font preload tags.

**Related docs**: [`docs/UPGRADING.md`](../../docs/UPGRADING.md#to-2210), [`docs/USAGE.md`](../../docs/USAGE.md#detail-pages-221),
[`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md#font-preload-221), [`../001-baseline/spec.md`](../001-baseline/spec.md)
(FR-STATUS-001, FR-HEADERACTIONS-001, FR-STAT-001, FR-FACTS-001, FR-FONTPRELOAD-001)

---

## Summary

Four opt-in record-page patterns (status badge, split header actions, stat row, facts grid) and one opt-in head
optimisation (font preload). Pages that use none of them render byte-identical to 2.20.

## User Scenarios & Testing

### US-1 — Status next to the title (Priority: P1)

**Given** `PageHeader(..., status: new PageStatus(new Label('monitor.status.up'), 'success'))` (or `withStatus()`),
**When** rendered, **Then** the `page_status` partial prints a `badge_success` badge (optional `title` tooltip) inside a
`page_title_row` wrapper; host `page_header` overrides receive it pre-rendered as `status_badge`.

### US-2 — Split header actions (Priority: P1)

**Given** `actionsLayout: 'split'` (or `withSplitActions()`) and actions marked `asIcon()` / `withDisplay('icon')`,
**When** rendered, **Then** icon actions are square buttons with `aria-label` + `title`, at most one primary action is a
filled button, and the rest sit in the square "more" menu (popover) with destructive actions last and in red; POST /
confirm / dialog actions are menu items and their confirm dialogs render outside the popover panel; `extra` takes host
markup after the icon buttons.

### US-3 — Key figures and facts grid (Priority: P2)

**Given** `ShowPage(..., stats: [new Stat('Uptime', '99.9 %', tone: 'success')])` and
`new DetailSection('Details', $items, layout: 'facts')`, **When** rendered, **Then** a `<dl>` row of compact tiles
appears above the sections (block `nowo_ui_show_stats`) and the section is a label-over-value grid
(`detail_list` with `modifier: 'facts'`). Values are printed escaped.

### US-4 — Font preload (Priority: P3)

**Given** `nowo_ui_kit.font_preload` with `fonts` URLs and / or a Vite `manifest` (`entry`, `prefixes`, `base`),
**When** the document or error layout renders, **Then** `<link rel="preload" as="font" type crossorigin>` tags follow
the viewport meta; host layouts print `{{ nowo_ui_font_preload() }}`.

### US-5 — Byte-identical without options (Priority: P1)

**Given** no status, `toolbar` actions layout, no stats, list sections, menus without `square` / `menu_items` and no
`font_preload`, **When** the scaffolds, partials and layouts render, **Then** the output equals the 2.20.0 renders
stored under `tests/Fixtures/bc-2.20/`.

**Tests**: `tests/Unit/PageContract/DetailPagePatternsTest.php`, `DetailPagePatternsBcTest.php`,
`tests/Unit/Theme/FontPreloadTest.php`, `tests/Unit/Twig/ThemeExtensionTest.php`; demo e2e
`demo/symfony8/e2e/tests/detail-page.spec.ts` with axe (`@axe-core/playwright`) on the order page.

## Requirements

- **FR-001**: `View\PageStatus` (label, tone `success` / `danger` / `warning` / `neutral` / `info`, optional `title`);
  `PageHeader::$status`, `withStatus()`; theme partial `page_status` (slots `page_status` + `badge_<tone>`).
- **FR-002**: `PageHeader::$actionsLayout` (`toolbar` default | `split`), `withSplitActions()`; `View\HeaderActions::split()`
  (`icons`, `primary`, `more`, `confirmActions()`; hidden actions dropped); theme partial `header_actions` (slot
  `header_actions`); Twig `nowo_ui_header_actions(actions)`; icon `more` in every icon set.
- **FR-003**: `Action::withDisplay(display, icon = null)` and `Action::asIcon(icon = null)` return immutable copies.
- **FR-004**: `menu` partial: `square: true` (slots `btn_secondary` + `btn_icon` + `menu_toggle_square`), `menu_items:
  true` draws POST / confirm / dialog actions as `menu_item`s (the `action` partial's `menu_item` option).
- **FR-005**: `View\Stat` (label, value, hint, tone, href) + theme partial `stat_row` (slots `stat_row`, `stat`,
  `stat_label`, `stat_value`, `stat_hint`); `ShowPage::$stats`.
- **FR-006**: `DetailSection::$layout` `facts` + slot `detail_list_facts`.
- **FR-007**: `font_preload` config + `Theme\FontPreload` + Twig `nowo_ui_font_preload()`; manifest memoized per build
  (FrankenPHP worker safe); no CSP nonce needed for preload links.
- **FR-008**: CSS tokens `--nowo-ui-header-actions-gap`, `--nowo-ui-stat-*`, `--nowo-ui-detail-facts-*` (light / dark
  through the base tokens); no inline styles or SVG.

Baseline mapping: FR-001 → **FR-STATUS-001**; FR-002 … FR-004 → **FR-HEADERACTIONS-001** (+ FR-MENU-001,
FR-ACTION-001); FR-005 → **FR-STAT-001**; FR-006 → **FR-FACTS-001**; FR-007 → **FR-FONTPRELOAD-001**; FR-008 →
**FR-THEME-001**.

## Success criteria

- **SC-001**: `DetailPagePatternsBcTest` matches every `tests/Fixtures/bc-2.20/*.html` render.
- **SC-002**: The demo order page passes Playwright e2e with axe (no violations).

## BC notes

- Host `page_header` overrides that loop `actions` keep their own layout; print `toolbar|raw` or include
  `nowo_ui_partial('header_actions')` to follow the split.
- A host override of `show.html.twig` keeps the new `nowo_ui_show_stats` block if it uses `ShowPage::stats`.
- `icon_set: ux_icon` hosts commit `bi:three-dots-vertical` (icon `more`).

## Non-goals

- Status computation, the figures themselves, share / watch dialogs (host `extra` markup).
- Font hosting or `@font-face` declarations.
