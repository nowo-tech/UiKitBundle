# Feature Specification: Sortable column headers

**Feature Branch**: `008-sortable-columns`  
**Created**: 2026-11-01 (backfill)  
**Status**: Implemented (2.20.0, PR #41)  
**Input**: Sortable headers in the list scaffold and the `table` partial — server-side for paginated lists, client-side
for tables rendered whole — with one validated sort object instead of per-host query parsing.

**Related docs**: [`docs/UPGRADING.md`](../../docs/UPGRADING.md#to-2200), [`docs/USAGE.md`](../../docs/USAGE.md#sortable-column-headers-220),
[`docs/STIMULUS.md`](../../docs/STIMULUS.md#table-sort-220), [`../001-baseline/spec.md`](../001-baseline/spec.md)
(FR-SORT-001, FR-SORT-002, FR-SORT-003)

---

## Summary

`Listing\SortRequest` validates `sort` / `dir` against a whitelist. `ListPage` / `Table` take `sorting:` (server links)
or `clientSort: true` (the `table-sort` Stimulus peer). The new `sort_header` theme partial renders the header content
for both.

## User Scenarios & Testing

### US-1 — Server-side sort (Priority: P1)

**Given** `SortRequest::fromRequest($request, SortRequest::fieldsOf($columns), 'created')` and
`new ListPage(..., pagination: $pagination, sorting: $sort)`, **When** rendered, **Then** each sortable header links
to the next order, keeps filters and rows per page, drops the page parameter (back to page 1), and the sorted `<th>`
carries `aria-sort`.

### US-2 — Invalid query never errors (Priority: P1)

**Given** `?sort=unknown&dir=sideways`, **When** the request is read, **Then** an unknown key falls back to the default
sort (or no sort) and an unknown direction to the key's first-click direction.

### US-3 — Client-side sort (Priority: P2)

**Given** `Table(clientSort: true)` (lists rendered whole) and the `table-sort` peer registered, **When** a header button
is clicked, **Then** rows sort by text / number / date (or `data-sort-value`), empty values last, stable, pinned rows
and column spans respected, and `table-sort:sorted` is dispatched. **Given** a `ListPage` with `clientSort: true` and
more than one page, **Then** it is rejected.

### US-4 — Unchanged tables (Priority: P1)

**Given** a table without sort information, **When** rendered, **Then** the markup equals 2.19.

**Tests**: `tests/Unit/Listing/SortRequestTest.php`, `tests/Unit/PageContract/SortableHeadersTest.php`,
`tests/Unit/View/SortableColumnTest.php`, `src/Resources/assets/stimulus-peers/table_sort_controller.test.ts`.

## Requirements

- **FR-001**: `SortRequest` — `fromRequest()` / `fromQuery()`, whitelist (key → first-click direction; a plain list
  means `asc`), `fieldsOf()` (from `Column`s, `ListingField`s or a listing definition), `nextDirection()`, `query()`,
  `linkQuery()` (current query + next sort, page parameter dropped), `withPageParam()`, `sortRows()` (PHP rows:
  empty last, stable, natural strings).
- **FR-002**: `Column`: `sortKey` (default the column key), `sortFirst`, `sortType`, `sortValueKey`, `isSortable()`,
  `sortField()`. `ListingField`: `sortKey`, `sortFirst`. `Table`: `sorting`, `isSortedBy()`, `ariaSort()`,
  `sortQuery()`. `listing_head` takes `sorting` (links) or `client_sort` (buttons).
- **FR-003**: Theme partial `sort_header` (`partials/_sort_header.html.twig`): label, direction arrow (neutral arrows
  when not sorted), visually hidden hint; a link (`href`) or a `table-sort` button; slot `sort_button`.
- **FR-004**: Stimulus peer `table-sort` (`stimulus-peers/table_sort_controller.ts`) with markup
  `data-controller="table-sort"`, `data-sortable`, `data-sort-type`, `data-sort-first`, `data-sort-value`.
- **FR-005**: Canonical icons `sort`, `sort_asc`, `sort_desc` in every icon set; strings `table.sort.hint_asc` /
  `table.sort.hint_desc` in the 7 locales; tokens `--nowo-ui-sort-hover`, `--nowo-ui-sort-icon-opacity`.

Baseline mapping: FR-001 → **FR-SORT-001**; FR-002, FR-003 → **FR-SORT-002**; FR-004 → **FR-SORT-003**; FR-005 →
**FR-ICON-001**, **FR-I18N-001**, **FR-THEME-001**.

## Success criteria

- **SC-001**: `table_sort_controller.ts` at 100 % statements in Vitest.
- **SC-002**: Sort links never carry the page parameter; filters and per-page survive a sort change.

## BC notes

- 1.x `sortable` columns with `sort:` / `direction:` now print a label span, an arrow icon span and a hidden hint
  instead of the CSS `::after` arrow, and drop the page parameter; the `sort` / `direction` parameters are unchanged.
- `icon_set: ux_icon` hosts commit `bi:arrow-down-up`, `bi:arrow-up`, `bi:arrow-down`.

## Non-goals

- Multi-column sort; client-side sorting of paginated lists; the database query (host repository).
