# Feature Specification: Every UiKit form is a Form Type

**Feature Branch**: `007-form-types-everywhere`  
**Created**: 2026-11-01 (backfill)  
**Status**: Implemented (2.19.0, PR #40)  
**Input**: REQ-TWIG-005 — after 2.18 ([`../006-action-csrf-forms/spec.md`](../006-action-csrf-forms/spec.md)) six kit
partials still printed hand-written `<form>` / `<input>` / `<select>` markup.

**Related docs**: [`docs/UPGRADING.md`](../../docs/UPGRADING.md#to-2190), [`docs/CHANGELOG.md`](../../docs/CHANGELOG.md),
[`../001-baseline/spec.md`](../001-baseline/spec.md) (FR-FORM-002, FR-FORM-003, FR-FORM-004)

---

## Summary

The partials `row_actions` (1.x POST hash), `user_menu` (logout), `locale_switcher` (form entries), `filters`
(search), `pagination_compact` (per page) and `segmented` (plain checkbox) render Form Type views through
`form_start` / `form_label` / `form_widget` / the child `form_row` loop / `form_end`. Requests are unchanged; a new
release gate prevents raw form markup from coming back.

## User Scenarios & Testing

### US-1 — Same requests, Form markup (Priority: P1)

**Given** each of the six partials with symfony/form installed, **When** rendered, **Then** the form is a Form Type view
and the URL, method, field names (`_token`, `_csrf_token`, `redirect`, `q`, `per_page`, nested `status[0]`…), values
and field order are those of 2.18; the markup differs only in attribute order and `form_div_layout` details
(`data-x=""`, `selected="selected"`, `checked="checked"`, `/>`).

### US-2 — Release gate (Priority: P1)

**Given** a raw `<form` / `<input` / `<textarea` / `<select>` in `src/Resources/views` or `demo/*/templates` outside
`src/Resources/views/form/`, the `*_legacy` fallbacks and `twig-005-ignore` controls, **When** `make
check-no-raw-html-form` (part of `release-check` and CI) runs, **Then** it fails.

### US-3 — Fallback without symfony/form (Priority: P2)

**Given** symfony/form (or its Twig extension) is missing, or a field name is not a valid form field name, **When** a
partial renders, **Then** the 2.18 raw markup (`*_legacy`) is printed with a deprecation; a bare Twig environment
without `ThemeExtension` renders it without deprecation.

### US-4 — Segmented plain checkbox id (Priority: P3)

**Given** `segmented` in plain-checkbox mode without `id`, **When** rendered, **Then** the input gets the generated id
(`prefs_involved` for `prefs[involved]`).

**Tests**: `tests/Unit/PageContract/RawFormPartialsTest.php`, `CompactPaginationTest.php`, `LegacyPartialsBcTest.php`,
`tests/Unit/HostChromePartialsTest.php`.

## Requirements

- **FR-001**: `Form\HiddenFieldsType` — one hidden field per `fields` entry, nested arrays as compound children,
  `null` / `false` skipped, `true` sent as `1`, form CSRF off (a host token is one more hidden field).
- **FR-002**: `Form\SearchFilterType` — nameless GET search field `q_name` (default `q`), input id `nowo-ui-filter-q`,
  visually hidden label, no CSRF.
- **FR-003**: `Form\PerPageType` — nameless GET form: the other query parameters as hidden fields, then the `per_page`
  select keeping its id, classes, `data-nowo-ui-autosubmit` and slot attributes.
- **FR-004**: `Form\UiFormViews` builds every view on a standalone factory (core types only) with the theme
  `form/nowo_ui_kit_fields.html.twig` (hidden fields without id, no wrapper); Twig `nowo_ui_form(kind, options)`
  (`hidden`, `search`, `per_page`, `checkbox`) and `nowo_ui_form_hidden(field)`.
- **FR-005**: Partials dispatch through `partials/_ui_form.html.twig` to `_row_actions_form`,
  `_user_menu_logout_form`, `_locale_switcher_form`, `_filters_form`, `_pagination_per_page_form`,
  `_segmented_checkbox_form`; `_action_form_view` prints hidden fields through `nowo_ui_form_hidden()`.
- **FR-006**: Deprecated raw fallbacks `_row_actions_legacy`, `_user_menu_logout_legacy`, `_locale_switcher_legacy`,
  `_filters_legacy`, `_pagination_per_page_legacy`, `_segmented_legacy_checkbox` (removed in 3.0).
- **FR-007**: `make check-no-raw-html-form` (`.scripts/check-no-raw-html-form.sh`) wired into `release-check` and CI.
- **FR-008**: The type-to-confirm input of `confirm` / `page/confirm` stays a nameless, never-submitted control — a
  documented REQ-TWIG-005 exception (`twig-005-ignore`).
- **FR-009**: Kitchen sink controls are Symfony Forms passed by the demo controller (`kitchen_forms`); skipped without
  them.

Baseline mapping: FR-001 … FR-005 → **FR-FORM-002** (+ FR-PAGINATION-001, FR-SEGMENTED-001); FR-006 →
**FR-FORM-003**; FR-007 → **FR-FORM-004**; FR-008 → **FR-CONFIRM-001**; FR-009 → **FR-DEMO-001**.

## Success criteria

- **SC-001**: `make check-no-raw-html-form` passes on the kit views and the demos.
- **SC-002**: Every partial's request is identical to 2.18 (names, values, order, method, URL).

## BC notes

- Exact-markup assertions should assert names / values / selectors.
- Host overrides of these partials keep `form_start` / `form_end` (`ui_form` is the passed `FormView`).
- `kitchen_sink.html.twig` needs `kitchen_forms` to show its form samples.

## Non-goals

- Changing request contracts or field names.
- Host forms (hosts adopt the same check in their own CI).
