# Usage — Nowo UiKit Bundle

## Table of contents

- [Import macros](#import-macros)
- [Partials](#partials)
- [Assets](#assets)
- [App chrome](#app-chrome-aside-avatar-user-menu-footer)
- [Overriding templates (REQ-TWIG-001)](#overriding-templates-req-twig-001)
- [Override vs upgrade](#override-vs-upgrade)
- [Remap CSS tokens](#remap-css-tokens)
- [Feature bundles](#feature-bundles)
- [Kitchen sink](#kitchen-sink)
- [Related docs](#related-docs)

## Import macros

```twig
{% import '@NowoUiKitBundle/macros/ui.html.twig' as ui %}

<button class="{{ ui.btn('primary') }}">Primary</button>
<button class="{{ ui.btn('danger', 'sm') }}">Delete</button>
{# Pass a framework override (feature bundle’s own css_framework): #}
<button class="{{ ui.btn('primary', null, 'tailwind') }}">Tailwind</button>

<div class="{{ ui.toolbar() }}">…</div>
<div class="{{ ui.table_wrap() }}"><table class="{{ ui.table() }}">…</table></div>
<span class="{{ ui.badge('success') }}">OK</span>
<div class="{{ ui.card() }}">…</div>
<div class="{{ ui.progress() }}"><div class="{{ ui.progress_bar() }}" style="width:40%"></div></div>
<span class="{{ ui.spinner('sm') }}"></span>
```

Macros always emit semantic `nowo-ui-*` classes. Named stacks also emit Bootstrap / Tailwind / Foundation classes. With `custom` or `none`, only `nowo-ui-*` remain.

`ui.badge()` keeps BC: `ui.badge('tailwind')` is still a framework override; `ui.badge('success')` / `ui.badge('danger', 'bootstrap5')` set variants.

Twig globals (from config):

- `nowo_ui_kit_css_framework`
- `nowo_ui_kit_icon_set`
- `nowo_ui_kit_row_actions_display`

## Partials

| Subpath | Purpose |
|---------|---------|
| `partials/_pagination.html.twig` | Server-side pagination |
| `partials/_empty.html.twig` | Empty state |
| `partials/_flashes.html.twig` | Inline Symfony flashes |
| `partials/_toasts.html.twig` | Fixed toast stack from flashes (string or `{title, message}`) |
| `partials/_row_actions.html.twig` | Edit / delete / view / copy cluster (`display`: `icon` \| `text` \| `icon_text`) |
| `partials/_page_header.html.twig` | Title + intro + toolbar HTML |
| `partials/_tabs.html.twig` | Tab **link** nav (no JS). In-page panels: `nowo-ui-tabs.js` / Stimulus `tabs` peer |
| `partials/_filters.html.twig` | Search + apply/clear + actions slot |
| `partials/_card.html.twig` | Card / panel (header/body/footer) |
| `partials/_modal_shell.html.twig` | Modal shell |
| `partials/_confirm.html.twig` | Confirm `<dialog>` (host owns POST+CSRF) |
| `partials/_page_loader.html.twig` | Page loader: veil (`visual: spinner\|orb`) or minimal indicator (`bar`, `bar_loop`, `bar_spinner`, `corner_spinner`, `dots`, `glow`, `corner_mark`). See [Page loader and page transitions](#page-loader-and-page-transitions) |
| `partials/_page_transition.html.twig` | Cross-document page transitions (View Transitions, `<head>`, no JS) |
| `partials/_thinking_orb.html.twig` | Thinking orb canvas (`data-nowo-ui-orb`) |
| `partials/_release_version.html.twig` | Installed-version button + lazy GitHub release dialog (optional `release_check`; renders nothing while disabled). See [CONFIGURATION.md](CONFIGURATION.md#release-check) |
| `partials/_brand.html.twig` | Brand mark (text / img / SVG) |
| `partials/_theme_toggle.html.twig` | Light/dark toggle |
| `partials/_width_toggle.html.twig` | Main full width ↔ content toggle |
| `partials/_locale_switcher.html.twig` | Locale menu (host supplies URLs/forms) |
| `partials/_kebab.html.twig` | Overflow / kebab menu |
| `partials/_burger.html.twig` | Burger / aside toggle |
| `partials/_avatar.html.twig` | Avatar initials or image |
| `partials/_aside.html.twig` | Left sidebar (flat or nested `children`) |
| `partials/_aside_nav_items.html.twig` | Recursive nav items (internal) |
| `partials/_user_menu.html.twig` | Avatar dropdown + links + logout |
| `partials/_footer.html.twig` | Site footer |
| `partials/_shell.html.twig` | Composed chrome |
| `components/_icon.html.twig` | Icon by `icon_set` |
| `macros/ui.html.twig` | Class macros |
| `demo/kitchen_sink.html.twig` | Full component showcase |

Example:

```twig
{% include '@NowoUiKitBundle/partials/_pagination.html.twig' with {
    pagination: { page: 1, per_page: 20, total: 100, total_pages: 5 },
    item_count: 20,
    route: 'app_items',
    route_params: {},
    query: { q: app.request.query.get('q') }
} %}
```

Row actions (inherits `nowo_ui_kit_row_actions_display`; override with `display`). Full contract: [ROW_ACTIONS.md](ROW_ACTIONS.md).

```twig
{% include '@NowoUiKitBundle/partials/_row_actions.html.twig' with {
    actions: [
        { kind: 'view', href: path('app_item_show', { id: item.id }) },
        { kind: 'edit', href: path('app_item_edit', { id: item.id }) },
        { kind: 'delete', tag: 'button', confirm_id: 'delete-' ~ item.id }
    ]
} %}

{# Visible labels instead of icon-only #}
{% include '@NowoUiKitBundle/partials/_row_actions.html.twig' with {
    display: 'text',
    actions: [
        { kind: 'edit', href: path('app_item_edit', { id: item.id }) },
        {
            kind: 'delete',
            method: 'POST',
            href: path('app_item_delete', { id: item.id }),
            csrf_token: csrf_token('delete' ~ item.id)
        }
    ]
} %}
```

Confirm trigger:

```twig
<button type="button" class="{{ ui.btn('danger') }}"
    {{ ui.confirm_toggle_attrs() }} {{ ui.confirm_target_attr('delete-item') }}>
    Delete
</button>
{% include '@NowoUiKitBundle/partials/_confirm.html.twig' with {
    id: 'delete-item',
    title: 'Delete?',
    body: '<p>Irreversible.</p>',
    footer: confirm_footer_html
} %}
```

## Assets

Named package **`nowo_ui_kit`** (REQ-ASSETS-004), `base_path` `/bundles/nowouikit`.

Sources are **TypeScript** under `src/Resources/assets/src/`, built with **Vite + pnpm** to `src/Resources/public/js/*.js` (REQ-ASSETS-001):

```bash
pnpm install
pnpm run build
```

```twig
<link rel="stylesheet" href="{{ asset('css/nowo-ui.css', 'nowo_ui_kit') }}">
<script src="{{ asset('js/nowo-ui-modal.js', 'nowo_ui_kit') }}" defer></script>
<script src="{{ asset('js/nowo-ui-shell.js', 'nowo_ui_kit') }}" defer></script>
<script src="{{ asset('js/nowo-ui-toast.js', 'nowo_ui_kit') }}" defer></script>
<script src="{{ asset('js/nowo-ui-confirm.js', 'nowo_ui_kit') }}" defer></script>
<script src="{{ asset('js/nowo-ui-page-loader.js', 'nowo_ui_kit') }}" defer></script>
<script src="{{ asset('js/nowo-ui-clipboard.js', 'nowo_ui_kit') }}" defer></script>
<script src="{{ asset('js/nowo-ui-tabs.js', 'nowo_ui_kit') }}" defer></script>
<script src="{{ asset('js/nowo-ui-theme.js', 'nowo_ui_kit') }}" defer></script>
<script src="{{ asset('js/nowo-ui-orb.js', 'nowo_ui_kit') }}" defer></script>
<script src="{{ asset('js/nowo-ui-release.js', 'nowo_ui_kit') }}" defer></script>{# only with release_check #}
```

Strict CSP: kit scripts are external files (no inline JS, no `on*=` handlers). When your CSP uses nonces, pass the per-request nonce your app stores in the request attribute `csp_nonce`: `nonce="{{ app.request.attributes.get('csp_nonce') }}"`.

Run `php bin/console assets:install` after install/update.

`nowo-ui-modal.js` handles `data-nowo-modal-open` / `data-nowo-modal-close` for `custom` / `none` / `tailwind`. Bootstrap stacks use `data-bs-*` and do not require this script for open/close.

`nowo-ui-shell.js` toggles the left aside via `data-nowo-ui-burger`, nested groups via `data-nowo-ui-nav-group-toggle`, and main width via `data-nowo-ui-width-toggle`.

`nowo-ui-clipboard.js` copies via `data-nowo-ui-clipboard*` (text or same-origin URL). `nowo-ui-tabs.js` switches in-page panels via `data-nowo-ui-tabs*` (Twig `_tabs` remains link navigation).

`nowo-ui-release.js` lazily fetches the release-status JSON the first time a `dialog[data-nowo-ui-release]` opens (`_release_version.html.twig`).

Optional Stimulus peers: `src/Resources/assets/stimulus-peers/` — see [STIMULUS.md](STIMULUS.md).

`nowo-ui-orb.js` mounts Thinking Orbs on `canvas[data-nowo-ui-orb]` (local MIT canvas engine; no CDN). Use `_thinking_orb.html.twig` or `_page_loader.html.twig` with `visual: 'orb'`.

## Page loader and page transitions

The veil (`visual: spinner` / `orb`) suits slow admin screens. Public sites usually want a **minimal** indicator that never
blocks the page and stays invisible on fast loads:

```twig
{% include '@NowoUiKitBundle/partials/_page_loader.html.twig' with {
    visual: 'bar_spinner',   {# bar | bar_loop | bar_spinner | corner_spinner | dots | glow | corner_mark #}
    active: true,            {# visible from first paint; hidden on window load #}
    stimulus: true,          {# page-loader peer: link interception + leave animation, timing per visual #}
    mark_src: asset('brand/mark.png'), {# corner_mark only #}
} %}
```

Tune colour and corner position with CSS variables on `.nowo-ui-page-loader--minimal`:
`--nowo-ui-page-loader-color` (default `--nowo-ui-primary`), `--nowo-ui-page-loader-corner-bottom`,
`--nowo-ui-page-loader-corner-left` (e.g. add a fixed footer height).

Cross-page fades: include once in `<head>` on every page that should take part (both pages must opt in):

```twig
{% include '@NowoUiKitBundle/partials/_page_transition.html.twig' with {
    style: 'fade_slide',                     {# fade_slide | fade | slide | zoom | blur | wipe | none #}
    persist: {'.site-header': 'site-header'}, {# optional: chrome that stays put (developer values only) #}
} %}
```

It renders a single `<style>` with the request `csp_nonce` (or `nonce:`), so it works under a strict CSP. Browsers
without cross-document View Transitions (e.g. Firefox today) navigate as usual; `prefers-reduced-motion: reduce`
disables it.

## App chrome (aside, avatar, user menu, footer)

```twig
{% include '@NowoUiKitBundle/partials/_shell.html.twig' with {
    brand: 'My App',
    aside_items: [
        { label: 'Dashboard', href: path('app_home'), current: true },
        {
            label: 'Settings',
            open: true,
            children: [
                { label: 'General', href: path('app_settings') }
            ]
        }
    ],
    name: app.user.userIdentifier,
    initials: 'AB',
    user_links: [
        { label: 'Profile', href: path('app_profile') }
    ],
    logout_href: path('app_logout'),
    footer_links: [
        { label: 'Privacy', href: path('legal_privacy') }
    ],
    footer_copyright: '© 2026',
    main: page_html
} %}
```

Or include `_burger`, `_aside`, `_avatar`, `_user_menu`, and `_footer` individually. Pass `header_end` for theme/locale toggles.

## Overriding templates (REQ-TWIG-001)

Place a file at:

`templates/bundles/NowoUiKitBundle/<subpath>`

Application overrides **always win**. Twig namespace: **`NowoUiKitBundle`**.

### Override vs upgrade

| Depth | How | On package bump |
|-------|-----|-----------------|
| Preferred | Remap `--nowo-ui-*` CSS tokens; keep macros | Vendor UI upgrades apply |
| Surgical | Override one partial (`_row_actions`, `_pagination`, …) | Only that file is frozen |
| Full fork | Copy a whole template under `templates/bundles/…` | That path stays frozen until you delete/merge it |

## Remap CSS tokens

```css
:root, .kit-admin {
  --nowo-ui-primary: #1f6f54;
  --nowo-ui-danger: #c0392b;
  --nowo-ui-surface: #fff;
  --nowo-ui-text: #0c1210;
  /* …see nowo-ui.css for full token list */
}

/* Dark: kit ships defaults under [data-theme="dark"]; remap as needed */
```

## Feature bundles

Other Nowo admin UIs should import `@NowoUiKitBundle/macros/ui.html.twig` and include partials instead of shipping a private `_ui_macros` / `nowo-ui.css` copy.

Align `nowo_ui_kit.css_framework` with the feature’s `css_framework`, **or** pass the feature framework as the macros’ trailing `framework` argument.

See [ADOPTION.md](ADOPTION.md).

## Kitchen sink

Render `@NowoUiKitBundle/demo/kitchen_sink.html.twig` from a host controller (see `demo/symfony8`). Optional context: `pagination`, `layout_template`.

## Related docs

- [STIMULUS.md](STIMULUS.md) — data-attribute contracts / Stimulus peers
- [ADOPTION.md](ADOPTION.md) — consolidating Beacon + feature bundles
- [ROADMAP.md](ROADMAP.md) — phase status
