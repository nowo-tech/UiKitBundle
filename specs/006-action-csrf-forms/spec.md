# Feature Specification: POST actions without a raw `<form>` (CSRF-only Form Type)

**Feature Branch**: `006-action-csrf-forms`  
**Created**: 2026-11-01 (backfill)  
**Status**: Implemented (2.18.0, PR #39)  
**Input**: REQ-TWIG-005 ("no raw HTML form markup in Twig") — a POST `View\Action` that carries a `csrfToken` instead
of a `form` still printed a hand-written `<form>` / `<input>` in `partials/_action_post.html.twig`.

**Related docs**: [`docs/UPGRADING.md`](../../docs/UPGRADING.md#to-2180), [`docs/CHANGELOG.md`](../../docs/CHANGELOG.md),
[`../001-baseline/spec.md`](../001-baseline/spec.md) (FR-FORM-001, FR-FORM-003, FR-ACTION-001)

---

## Summary

UiKit builds a nameless CSRF-only form for a POST action that has a `csrfToken` and renders it through `form_start` /
`form_end`, exactly like an action that already carries a `form`. The HTTP request does not change, so controllers keep
`isCsrfTokenValid()` on `$request->request->get('_token')`.

## User Scenarios & Testing

### US-1 — Toolbar / row action with a token (Priority: P1)

**Given** `new Action(label: 'Delete', href: '/orders/7', method: 'DELETE', csrfToken: $token)` and symfony/form installed,
**When** the toolbar, a row action, the confirm dialog or the `page/confirm` scaffold renders it,
**Then** the output is a `form_start` / `form_end` form posting to `/orders/7` with `method="post"`, a hidden `_token`
holding `$token`, a hidden `_method=DELETE`, the same button markup, classes, `data-*` / `disabled` attributes,
`class="nowo-ui-action__form"` and `id` (type-to-confirm), and no second token.

### US-2 — Custom token field (Priority: P2)

**Given** `csrfField: 'token'`, **When** rendered, **Then** the hidden field is named `token`.

### US-3 — Host without symfony/form (Priority: P2)

**Given** symfony/form or its Twig extension is missing, or `csrfField` is not a valid form field name (`token[x]`),
**When** the action renders, **Then** the 2.17 raw form (`_action_post_legacy`) is printed and a deprecation
(`Since nowo-tech/ui-kit-bundle 2.18 …`) is triggered.

**Tests**: `tests/Unit/PageContract/ActionPostFormTest.php`, `HeaderActionFormTokenTest.php`, `LegacyPartialsBcTest.php`.

## Requirements

- **FR-001**: `Form\ActionCsrfType` — one hidden field named `csrf_field` (default `_token`) holding `csrf_token`,
  under a nameless root, form CSRF protection off; `ActionCsrfType::createActionView()` builds the view.
- **FR-002**: The view is built by a standalone form factory with core types only (host type extensions do not change
  action forms), service `nowo_ui_kit.action_form_factory`, registered only with symfony/form.
- **FR-003**: Twig `nowo_ui_action_form(action)` (`ThemeExtension`) returns the `FormView` a POST action renders: its
  `form`, else the CSRF-only form of its `csrfToken`; null for GET actions.
- **FR-004**: `_action_post` renders the view through `_action_form_view` (`form_start` / `form_end`); only the order
  inside the form differs from 2.17 (token after the button, `_method` printed by `form_start`).
- **FR-005**: `_action_post_legacy` is the deprecated raw fallback (removed in 3.0).

Baseline mapping: FR-001 … FR-004 → **FR-FORM-001**; FR-005 → **FR-FORM-003**; the action model → **FR-ACTION-001**.

## Success criteria

- **SC-001**: Same URL, method, field names and values as 2.17 for every action shape (asserted by name / value).
- **SC-002**: No raw `<form>` printed for a POST action when symfony/form is installed.

## BC notes

- Exact-markup assertions must assert names / values, not field order.
- The action form now goes through the host's `form_start` block: a theme that decorates every `<form>` decorates
  these too.
- Bare Twig environments rendering `_confirm` with an `action` need `nowo_ui_action_form` (register `ThemeExtension`
  or a stub returning null).

## Non-goals

- The other kit partials with hand-written forms (handled in 2.19, [`../007-form-types-everywhere/spec.md`](../007-form-types-everywhere/spec.md)).
- Generating or validating the CSRF token (host responsibility).
