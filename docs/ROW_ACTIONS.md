# Row actions (REQ-UI-001)

## Table of contents

- [Goal](#goal)
- [One icon per action](#one-icon-per-action)
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
| `extend` / `revoke` / `refresh` / `archive` / `pause` / `resume` / `download` / `import` / `send` / `sign_in` / `sign_out` / `reset` / `mark_done` / `mark_all_done` / `move` / `enable` / `disable` / `hide` / `run` / `retry` / `stop` | `secondary` | see [One icon per action](#one-icon-per-action) | matching `action.*` |

CSS class always: `nowo-ui-action nowo-ui-action--{kind}` (BEM `--`, **not** `nowo-ui-action-edit`).

Override with `variant` / `icon` / `label` on the action hash when needed. Domain-only glyphs: set `icon` to a free name (or UX icon) and keep `kind` for the semantic style (`edit`, `view`, …).

## One icon per action

**Bundles pass a kind, never an icon name, for actions.** The host sets the icon of each kind once, in
`nowo_ui_kit.action_icons`, and every use follows it, kit chrome included. No bundle needs an option of its own. An
explicit `icon` on an action still wins; it is for glyphs of the domain that no kind covers.

Recommended standard (Symfony Beacon `docs/identity/UI-PATTERNS.md` section 4), for hosts that opt in:

| Action | Kind | Icon | Variant | Notes |
|--------|------|------|---------|-------|
| Create, add | `create` | `tabler:plus` | primary when it is the page's main action, else secondary | `Action::create()` = primary; `action_variants.create: secondary` for the rest |
| Edit | `edit` | `tabler:pencil` | secondary | |
| View, open a preview, show a value | `view` | `tabler:eye` | secondary | |
| Hide a value | `hide` | `tabler:eye-off` | secondary | toggle partner of `view` (pass the label) |
| Share | `share` | `tabler:share` | secondary | |
| Copy | `copy` | `tabler:copy` | secondary | |
| Export | `export` | `tabler:download` | secondary | |
| Download | `download` | `tabler:download` | secondary | same glyph as `export`, own label |
| Import, upload | `import` | `tabler:upload` | secondary | |
| Refresh, rotate | `refresh` | `tabler:refresh` | secondary | rotating a token asks for confirmation |
| Retry | `retry` | `tabler:refresh` | secondary | |
| Run | `run` | `tabler:player-play` | secondary | |
| Stop a timer | `stop` | `tabler:player-stop` | secondary | not danger: it deletes nothing |
| Pause / Resume | `pause` / `resume` | `tabler:player-pause` / `tabler:player-play` | secondary | |
| Extend (a date, an expiry) | `extend` | `tabler:clock-plus` | secondary | |
| Revoke (access, a member, a token, a link) | `revoke` | `tabler:user-off` | secondary | with confirmation; removing a member is `revoke` |
| Archive | `archive` | `tabler:archive` | secondary | |
| Filter | `filter` | `tabler:filter` | secondary | a filter's submit is not primary: `action_variants.filter: secondary` |
| Clear filters | `filter_off` | `tabler:filter-off` | secondary | |
| Close, dismiss, remove a chip | `close` | `tabler:x` | secondary | never for deleting data |
| Cancel | `cancel` | `tabler:x` | secondary | |
| Delete, empty, delete all, anonymise | `delete` | `tabler:trash` | danger | with confirmation |
| Save | `save` | `tabler:device-floppy` | primary | the form's main action |
| Send, send a test | `send` | `tabler:send` | secondary | |
| Sign in / Sign out | `sign_in` / `sign_out` | `tabler:login` / `tabler:logout` | secondary | |
| Reset to defaults, undo | `reset` | `tabler:arrow-back-up` | secondary | |
| Mark done, read or accepted | `mark_done` | `tabler:check` | secondary | |
| Mark all done | `mark_all_done` | `tabler:checks` | secondary | |
| Move, transfer | `move` | `tabler:arrows-exchange` | secondary | |
| Enable / Disable | `enable` / `disable` | `tabler:power` | secondary | one toggle, the label changes |

**Rule:** a filled primary never goes in a table row. Whether an action asks for confirmation stays with the caller
(`Confirm`, `confirm_id`, `confirm_message`); the table says which ones must.

Configuration for that table:

```yaml
nowo_ui_kit:
    icon_set: ux_icon
    row_actions_display: icon_text # set it together with action_icons, not before: until then kit kinds draw bi:*
    action_icons:
        create: 'tabler:plus'
        edit: 'tabler:pencil'
        view: 'tabler:eye'
        hide: 'tabler:eye-off'
        share: 'tabler:share'
        copy: 'tabler:copy'
        export: 'tabler:download'
        download: 'tabler:download'
        import: 'tabler:upload'
        refresh: 'tabler:refresh'
        retry: 'tabler:refresh'
        run: 'tabler:player-play'
        stop: 'tabler:player-stop'
        pause: 'tabler:player-pause'
        resume: 'tabler:player-play'
        extend: 'tabler:clock-plus'
        revoke: 'tabler:user-off'
        archive: 'tabler:archive'
        filter: 'tabler:filter'
        filter_off: 'tabler:filter-off'
        close: 'tabler:x'
        cancel: 'tabler:x'
        delete: 'tabler:trash'
        save: 'tabler:device-floppy'
        send: 'tabler:send'
        sign_in: 'tabler:login'
        sign_out: 'tabler:logout'
        reset: 'tabler:arrow-back-up'
        mark_done: 'tabler:check'
        mark_all_done: 'tabler:checks'
        move: 'tabler:arrows-exchange'
        enable: 'tabler:power'
        disable: 'tabler:power'
    action_variants:
        create: secondary # Action::create() stays primary for the page's main action
        filter: secondary # the filter bar's submit
```

What `action_icons` does under each `icon_set`:

| `icon_set` | `tabler:x` | `bi:x` | other prefix | bare `x` |
|------------|-----------|--------|--------------|----------|
| `ux_icon` | `ux_icon('tabler:x')` | `ux_icon('bi:x')` | `ux_icon` with the name as written | `ux_icon('x')` (a local icon) |
| `tabler-icons` | `ti ti-x` | kit default of the kind | kit default | `ti ti-x` |
| `bootstrap-icons` | kit default of the kind | `bi bi-x` | kit default | `bi bi-x` |
| `svg_inline` | kit default | kit default | kit default | kit default |
| `none` | the label | the label | the label | the label |

A name the set cannot draw never fails: the kind keeps its kit default, so a config written for `ux_icon` is harmless
under the others. Under `ux_icon` the build checks the grammar, not that the icon exists, since it may be local or on-demand.
Import the icons you name with `bin/console ux:icons:import`: `ux:icons:lock` does **not** see names written only in
`action_icons`. A wrong or missing name throws `IconNotFoundException` (a 500 on every page with that kind, chrome included)
unless `ux_icons.ignore_not_found` is on, which prints nothing and logs a warning; with `on_demand` on, it also calls Iconify
on every render. Render each mapped kind in one smoke test.

**Setting a kind in `action_icons` also shows its icon where it was text:** a toolbar `Action` of that kind without an
icon of its own now defaults to `icon_text`, and a menu item of that kind draws the icon (so *Delete* in the more menu has
its trash); a menu item with its own icon draws it once the map is not empty, and a 1.x hash in `_row_actions` with
`menu: true` draws the icon of a mapped kind. An explicit `display` still wins, and without the option all of these stay
text.

`action_variants` (kind → `primary` | `secondary` | `danger`; `delete` stays danger, any other value is refused at build)
applies only where an action has no explicit variant. It is
needed for two rows, because the kit's defaults differ from the table and cannot change for other hosts: `create` (kit
default primary, also in a row) and the filter submit (kit default primary; the key `filter` drives it). All other rows
already match: the kit paints `edit`, `view` and the rest as secondary (`View\Action` resolves them as `neutral`, painted
like secondary with its own row slot `action_neutral`), `delete` as danger, `save` as primary.

`Action::create()` sets `primary` explicitly and keeps it, so use `new Action('create', …)` in a row. A kind does not add a
confirmation.

Do not map `save: secondary` to keep it out of rows, since forms that
use `new Action('save')` would follow it; give the row action an explicit variant.

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
| `confirm_message` | Confirm on form submit: inline `window.confirm` (default) or the `confirm-submit` peer with `confirm_mode` `native` / `dialog` (2.22) — prefer `confirm_id` |
| `attr` | Extra HTML attributes |
| `visible` / `disabled` | Optional gates |

Macro `ui.action(kind, framework, variant=null)` only emits **classes**; prefer the partial for full controls.

## Confirm and POST

1. Prefer **UiKit `_confirm`** + `confirm_id` on the trigger; keep the real **POST + CSRF** form in the confirm footer (host owns the destructive request).
2. Inline POST without a dialog: `method: 'POST'` + `csrf_token` (optional `confirm_message` until migrated).
3. Never use GET for delete.
4. 2.0: `View\Action` + `View\Confirm` render the dialog for you; `Confirm::expectedValue` adds type-to-confirm. Theme
   slots `action_primary` / `action_secondary` / `action_danger` add host classes per resolved variant (e.g. a red
   delete on Tailwind) — [`PAGE_CONTRACT.md`](PAGE_CONTRACT.md#view-models).

## Confirm contract for POST hashes (2.22)

By default a 1.x POST hash with `confirm_message` keeps the 2.21 markup: `onsubmit="return confirm(…)"` on the form
(blocked by a strict CSP `script-src` without `'unsafe-inline'`). Opt in to the UiKit confirm contract with the
`_row_actions` option `confirm_mode` or the app-wide `nowo_ui_kit.row_actions_confirm` (the option wins):

| `confirm_mode` | Form attributes | Who asks |
| --- | --- | --- |
| `inline` (default) | `onsubmit="return confirm(&quot;…&quot;);"` | the browser, inline handler |
| `native` | `data-controller="confirm-submit" data-action="submit->confirm-submit#confirm" data-confirm-submit-message-value="…"` | `confirm-submit` peer, `window.confirm()` |
| `dialog` | as `native` + `data-confirm-submit-mode-value="dialog"` | `confirm-submit` peer, kit `<dialog>` (`askConfirm`), replays the submit once on Confirm |

```twig
{{ include('@NowoUiKitBundle/partials/_row_actions.html.twig', {actions: actions, confirm_mode: 'dialog'}) }}
```

`native` / `dialog` **require the `confirm-submit` Stimulus peer** to be registered
(`app.register('confirm-submit', ConfirmSubmitController)`, see [STIMULUS.md](STIMULUS.md)). Without it, or before it
connects, `js/nowo-ui-confirm.js` fails closed (2.23): it asks with `window.confirm()`, cancels the submit when declined
and warns in the console (once per form); only switch the global once every page that renders row actions loads the
peer (without the kit runtime either, the form submits with no confirmation). A host `attr.onsubmit` is dropped with
`inline` (the kit's handler wins) but rendered with `native` / `dialog`, where it runs before the peer. Host
`attr['data-controller']` / `attr['data-action']` tokens are kept in front of the peer's; other host attributes pass
through as before (the kit's own keys win). Unknown values fall back to `inline`. The same attributes are printed by
the `symfony/form` path and by the deprecated raw-form fallback.

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
