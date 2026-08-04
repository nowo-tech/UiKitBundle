# Row actions (REQ-UI-001)

## Table of contents

- [Goal](#goal)
- [Canonical kinds](#canonical-kinds)
- [Display modes](#display-modes)
- [Partial API](#partial-api)
- [Confirm and POST](#confirm-and-post)
- [Do / don't](#do--dont)
- [Migration](#migration)

## Goal

**UiKitBundle** owns how table/list row action buttons look and behave. Feature bundles **MUST** compose `@NowoUiKitBundle/partials/_row_actions.html.twig` instead of inventing `nowo-ui-action-edit`, local SVG clusters, or ad-hoc `ui.btn` + text.

## Canonical kinds

| `kind` | Default btn variant | Default icon | i18n key |
|--------|---------------------|--------------|----------|
| `view` | `secondary` | eye | `action.view` |
| `edit` | `secondary` | pencil | `action.edit` |
| `delete` | `danger` | trash | `action.delete` |
| `copy` | `secondary` | clipboard/copy | `action.copy` |
| `create` | `primary` | plus | `action.create` |
| `save` | `primary` | check | `action.save` |
| `cancel` | `secondary` | close | `action.cancel` |
| `restore` | `secondary` | restore | `action.restore` |
| `share` | `secondary` | share | `action.share` |
| `config` | `secondary` | gear | `action.config` |
| `export` | `secondary` | download | `action.export` |
| `filter` | `secondary` | funnel | `action.filter` |
| `prev` / `next` / `close` | `secondary` | chevrons / x | matching `action.*` |

CSS class always: `nowo-ui-action nowo-ui-action--{kind}` (BEM `--`, **not** `nowo-ui-action-edit`).

Override with `variant` / `icon` / `label` on the action hash when needed. Domain-only glyphs: set `icon` to a free name (or UX icon) and keep `kind` for the semantic style (`edit`, `view`, …).

## Display modes

Config / Twig global `nowo_ui_kit.row_actions_display` / `nowo_ui_kit_row_actions_display`:

| Value | Rendering |
|-------|-----------|
| `icon` | Glyph + visually hidden label + `aria-label` / `title` (default) |
| `text` | Visible label only |
| `icon_text` | Glyph + visible label |

Independent of `icon_set` (how glyphs are drawn). With `display: text`, no glyphs are emitted.

Feature `web_ui.row_actions_display` **MAY** mirror the kit key and pass `display` into the include.

## Partial API

```twig
{% include '@NowoUiKitBundle/partials/_row_actions.html.twig' with {
    framework: feature_fw|default(null),
    display: null,
    actions: [
        { kind: 'view', href: path('…_show', { id: row.id }) },
        { kind: 'edit', href: path('…_edit', { id: row.id }), label: 'Edit item'|trans },
        {
            kind: 'delete',
            tag: 'button',
            confirm_id: 'delete-' ~ row.id,
            label: 'Delete'|trans
        }
    ]
} %}
```

| Field | Meaning |
|-------|---------|
| `method` | `GET` (default) or `POST` |
| `tag` | `auto` \| `a` \| `button` \| `form` — `auto` → `form` if POST; `button` if confirm/modal without href; else `a` |
| `csrf_token` / `csrf_field` | POST forms (`_token` default) |
| `confirm_id` | Opens UiKit `_confirm` |
| `modal_id` | Opens framework / kit modal |
| `confirm_message` | Transitional `window.confirm` on form submit — prefer `confirm_id` |
| `attr` | Extra HTML attributes |
| `visible` / `disabled` | Optional gates |

Macro `ui.action(kind, framework, variant=null)` only emits **classes**; prefer the partial for full controls.

## Confirm and POST

1. Prefer **UiKit `_confirm`** + `confirm_id` on the trigger; keep the real **POST + CSRF** form in the confirm footer (host owns the destructive request).
2. Inline POST without a dialog: `method: 'POST'` + `csrf_token` (optional `confirm_message` until migrated).
3. Never use GET for delete.

## Do / don't

**Do**

- Compose `_row_actions` for every admin table/list cluster.
- Use canonical `kind` values and BEM `--` modifiers.
- Let hosts switch icon/text via `row_actions_display`.

**Don't**

- Invent `nowo-ui-action-edit` / `nowo-ui-btn-view`.
- Hard-code `bi-*` / `ti-*` / local SVG for standard CRUD actions.
- Ship a private `_row_actions` or `_action_icons` for edit/delete/view/copy.
- Call `window.confirm` in new code when `_confirm` is available.

## Migration

1. Depend on `nowo-tech/ui-kit-bundle: ^1.5`.
2. Replace inline `ui.row_actions` + `ui.btn` / `ui.action` + icons with one `_row_actions` include.
3. Map delete triggers to `confirm_id` or `method: POST`.
4. Remove local icon macros for kinds covered by `_icon`.
5. Align feature docs with this file + [CONFIGURATION.md](CONFIGURATION.md).
