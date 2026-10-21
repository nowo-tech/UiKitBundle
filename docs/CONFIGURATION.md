# Configuration — Nowo UiKit Bundle

## Table of contents

- [Root keys](#root-keys)
- [Panel path rewrites](#panel-path-rewrites)
- [Release check](#release-check)
- [Appearance](#appearance)
- [Route links](#route-links)
- [Error pages](#error-pages)
- [App shell](#app-shell)
- [Examples](#examples)

## Root keys

Alias: **`nowo_ui_kit`**

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `css_framework` | enum | `bootstrap5` | `bootstrap` (alias of `bootstrap5`), `bootstrap4`, `bootstrap5`, `tabler`, `tailwind`, `foundation`, `custom`, `none` |
| `icon_set` | enum | `bootstrap-icons` | `bootstrap-icons`, `tabler-icons`, `ux_icon`, `svg_inline`, `none` — how glyphs are drawn |
| `row_actions_display` | enum | `icon` | Table/list row actions: `icon` (glyph + visually hidden label), `text` (visible label only), `icon_text` (glyph + visible label) |
| `panel_path_rewrites` | map | `{}` | Legacy path prefix → new prefix (e.g. `/admin/blog: /panel/blog`). Empty = feature disabled (since 1.9.0) |
| `release_check` | map | `enabled: false` | Optional GitHub release-version checker (footer dialog + JSON endpoint). See [Release check](#release-check) |
| `appearance` | map | `enabled: false` | Optional site appearance SPI: presets, `--nowo-ui-*` token emitter, Twig functions, optional form (since 1.16.0). See [Appearance](#appearance) |
| `route_links` | map | `routes: {}` | Route (prefix) → link map for `nowo_ui_route_link()`, e.g. the page-edge manual tab (since 1.17.0). See [Route links](#route-links) |
| `error_pages` | map | kit layout + texts | Kit HTTP error pages (since 2.6.0). See [Error pages](#error-pages) |
| `shell` | map | `kit_styles: true` | Signed-in app shell templates (since 2.6.0). See [App shell](#app-shell) |

Parameters / Twig globals:

- `%nowo_ui_kit.css_framework%` → `nowo_ui_kit_css_framework` (normalized: `bootstrap` → `bootstrap5`)
- `%nowo_ui_kit.icon_set%` → `nowo_ui_kit_icon_set`
- `%nowo_ui_kit.row_actions_display%` → `nowo_ui_kit_row_actions_display`
- `%nowo_ui_kit.panel_path_rewrites%` — the validated rewrite map (no Twig global)
- `%nowo_ui_kit.appearance.enabled%` (no Twig global; use the `nowo_ui_appearance*()` functions)
- `%nowo_ui_kit.route_links%` (no Twig global; use `nowo_ui_route_link()`)
- `%nowo_ui_kit.release_check.enabled%`, `%nowo_ui_kit.release_check.github_repo%`, `%nowo_ui_kit.release_check.current_version%` → Twig global `nowo_ui_kit_release_check` (`{enabled, current_version}`)
- `%nowo_ui_kit.error_pages%` → Twig global `nowo_ui_error_pages` (`{layout_template, theme_storage, theme_toggle, html_attributes, translation_domain, translation_prefix}`) and the functions `nowo_ui_error_page()` / `nowo_ui_error_text()` (2.6)
- `nowo_ui_kit.shell` → Twig global `nowo_ui_kit_shell` (the whole map, 2.6)

Asset package (always prepended): `nowo_ui_kit` → `/bundles/nowouikit`.

`icon_set` and `row_actions_display` are independent: with `row_actions_display: text`, `_row_actions` does not emit glyphs (regardless of `icon_set`).

## Panel path rewrites

Since **1.9.0**. Some kits hardcode `/admin/...` in their route attributes. `panel_path_rewrites` lets the host move those surfaces (for example to `/panel/...`) without forking the kit:

```yaml
# config/packages/nowo_ui_kit.yaml
nowo_ui_kit:
    panel_path_rewrites:
        '/admin/page-builder': '/panel/page-builder'
        '/admin/blog': '/panel/blog'
        '/settings/seo': '/panel/seo/settings'
```

When the map is **non-empty** the bundle registers:

1. **`PanelPathRewritingLoader`** — decorates `routing.loader` and rewrites every loaded `RouteCollection` path (the **longest** matching prefix wins).
2. **`LegacyPanelPathRedirectSubscriber`** (`kernel.request`, priority 32) — redirects requests to a legacy prefix to the new path, preserving the sub-path and query string: **301** for `GET`/`HEAD`, **308** for other methods (method/body preserved).

Rules:

- Prefixes are matched on path-segment boundaries: `/admin/blog` matches `/admin/blog`, `/admin/blog/{id}` and `/admin/blog{_format}`, but not `/admin/blogger`.
- Both prefixes must start with `/`; trailing slashes are ignored.
- Prefixes must be same-origin paths: a leading `//` or any backslash is rejected. A root target (`'/admin': '/'`) is allowed; redirect targets are always normalised to a single leading `/` (never `//host`).
- The target must differ from the source and must not be nested under it (`/admin` → `/admin/panel` is rejected to avoid redirect loops).
- Route names and URL generation are unaffected (routes keep their names; generated URLs use the new paths).
- With the default empty map, nothing is registered — behavior is identical to 1.8.x.

## Release check

Optional, **off by default**. Shows the installed version as a small button; the dialog lazily asks GitHub whether a newer **public** release exists (no request on page render, no auto-upgrade).

```yaml
# config/packages/nowo_ui_kit.yaml
nowo_ui_kit:
    release_check:
        enabled: true
        github_repo: 'acme/my-app'          # public owner/name
        current_version: '%app.version%'     # clean x.y.z (optional leading v)
        # cache_ttl: 43200                   # seconds (min 60), cache.app
        # default_branch: main               # compare target when already up to date
        # user_agent: nowo-ui-kit-release-check
```

```yaml
# config/routes/nowo_ui_kit.yaml
nowo_ui_kit_release:
    resource: '@NowoUiKitBundle/Resources/config/routes/release_check.yaml'
```

```twig
{% include '@NowoUiKitBundle/partials/_release_version.html.twig' %}
<script src="{{ asset('js/nowo-ui-confirm.js', 'nowo_ui_kit') }}" defer nonce="{{ app.request.attributes.get('csp_nonce') }}"></script>
<script src="{{ asset('js/nowo-ui-release.js', 'nowo_ui_kit') }}" defer nonce="{{ app.request.attributes.get('csp_nonce') }}"></script>
```

| Key | Default | Description |
|-----|---------|-------------|
| `enabled` | `false` | Registers `ReleaseUpdateChecker` (needs `symfony/http-client`; `LogicException` at container build otherwise) |
| `github_repo` | `''` | `owner/name`; anything else (URLs, `..`, extra segments) skips the check at runtime |
| `current_version` | `''` | Installed label; empty / git-describe / pre-release labels → `status: skipped` |
| `cache_ttl` | `43200` | Seconds the GitHub answer is cached per repo + version (`cache.app`) |
| `default_branch` | `main` | Compare target when the installed version is the latest |
| `user_agent` | `nowo-ui-kit-release-check` | Sent to `api.github.com` |

Behaviour:

- Route `nowo_ui_kit_release_status` (`GET /_nowo-ui/release/status`) → JSON `{status, current, latest, updateAvailable, versionsBehind, releaseUrl, compareUrl, checkedAt}` with `Cache-Control: private, no-store`. While disabled the controller still exists and answers **404**, so an imported route never breaks the router.
- At most two GitHub calls per TTL (`releases/latest` + one page of `releases` to count newer non-prerelease tags). Timeouts 5 s / 8 s, no redirects; every failure soft-fails to `status: error` and is logged (`logger` if present). A failed `releases/latest` fetch (rate limit, outage, bad payload) is cached as an error for `ReleaseUpdateChecker::ERROR_CACHE_TTL` (5 minutes, or `cache_ttl` if shorter), so failures are not retried on every hit.
- Only `https://github.com/…` URLs are returned and rendered.
- Partial options: `version`, `status_url`, `id`, `title`, `stimulus` (use peers `confirm-dialog` + `release-status` instead of the IIFEs), `force`, `framework`, `trigger_class`, `wrapper_class`. Labels live in the `NowoUiKitBundle` domain (`release.*`).
- **The endpoint is anonymous by default** and discloses the installed version (useful to an attacker matching known CVEs). Unless the version is public anyway, put it behind your admin firewall, for example by importing it under an admin prefix and adding an access rule:

  ```yaml
  # config/routes/nowo_ui_kit.yaml
  nowo_ui_kit_release:
      resource: '@NowoUiKitBundle/Resources/config/routes/release_check.yaml'
      prefix: /admin

  # config/packages/security.yaml
  security:
      access_control:
          - { path: ^/admin/_nowo-ui/release/status, roles: ROLE_ADMIN }
  ```

  The partial's default `status_url` is `path('nowo_ui_kit_release_status')`, so the prefix is picked up automatically; only include the partial for users who can reach the route.

## Appearance

Since **1.16.0**, off by default. Lets an admin pick the site palette (light and dark), corner style and border
strength, stored by the host; the kit ships the presets, emits the `--nowo-ui-*` tokens and prints them with the CSP
nonce. No entity, no controller: storage stays in the host behind `AppearanceProviderInterface`. Details and the
token list: [THEMING.md — Appearance SPI](THEMING.md#appearance-spi).

```yaml
# config/packages/nowo_ui_kit.yaml
nowo_ui_kit:
    appearance:
        enabled: true
        provider: App\Appearance\DbAppearanceProvider   # service implementing AppearanceProviderInterface; null = `defaults`
        defaults:                    # used by the config provider (and as your "reset" values)
            brand_name: 'Acme'
            brand_eyebrow: 'Operations'
            preset: slate            # light colours from this preset
            preset_dark: midnight    # dark colours from this preset
            corner_style: soft       # sharp | soft | rounded
            border_strength: medium  # subtle | medium | strong
            footer_fixed: false
        presets:                     # host presets (listed first); a built-in id replaces the built-in
            acme:
                mode: light          # which half "applying" the preset replaces
                light: { accent: '#7c3aed', accent_deep: '#5b21b6', danger: '#b42318', warn: '#b54708', paper: '#f7f5ff', ink: '#1e1b4b', surface: '#ffffff' }
                dark:  { accent: '#a78bfa', accent_deep: '#c4b5fd', danger: '#f97066', warn: '#fdb022', paper: '#0b0618', ink: '#ede9fe', surface: '#140d26' }
        builtin_presets: [ocean, slate, sandstone, midnight, obsidian, aurora, ember]   # default: all
        kit_tokens: true             # false = write only the aliases
        token_aliases:               # kit token => extra properties that receive the same value
            '--nowo-ui-primary': ['--brand-primary']
        selectors:                   # the three emitted blocks
            root: ':root'
            light: ':root, [data-theme="light"]'
            dark: '[data-theme="dark"]'
        shadow_rgb_fallback: '15 23 42'   # --nowo-ui-shadow-rgb when the light ink is not hex
```

| Key | Default | Notes |
|-----|---------|-------|
| `enabled` | `false` | Registers the services and Twig functions |
| `provider` | `null` | Service id; null = `nowo_ui_kit.appearance.config_provider` (the `defaults`) |
| `defaults.*` | see above | `preset` / `preset_dark` may name a host preset |
| `presets` | `{}` | Ids: lowercase letters, digits, `-`, `_`; colours validated as CSS colours |
| `builtin_presets` | all 7 | Subset of `ocean`, `slate`, `sandstone`, `midnight`, `obsidian`, `aurora`, `ember` |
| `kit_tokens` | `true` | |
| `token_aliases` | `{}` | Keys must be emitted kit tokens; values `--name` (a single string is accepted) |
| `selectors` | `:root` / `:root, [data-theme="light"]` / `[data-theme="dark"]` | No `{`, `}`, `;`, `<`, `>` |
| `shadow_rgb_fallback` | `15 23 42` | Three channels |

Services (when enabled): `nowo_ui_kit.appearance.provider` (alias of your provider; also
`AppearanceProviderInterface`), `nowo_ui_kit.appearance.config_provider` (`ConfigAppearanceProvider`),
`nowo_ui_kit.appearance.preset_registry` (`AppearancePresetRegistry`), `nowo_ui_kit.appearance.css_emitter`
(`AppearanceCssEmitter`), the Twig extension, and — only when `symfony/form` is installed — the form types
`AppearanceType` / `AppearancePaletteType`. Services implementing `AppearancePresetProviderInterface` are tagged
`nowo_ui_kit.appearance_preset_provider` automatically and add their presets.

## Route links

Since **1.17.0**. A route or route prefix → link map read by the Twig function `nowo_ui_route_link(route = current
route)`, which returns `{url, path, testid, prefix}` or `null`. A key matches the route itself or any route starting
with `<key>_` (`admin_users` matches `admin_users_edit`, not `admin_usersx`); the longest key wins. Typical use: the
"Read this chapter in the manual" page-edge tab (`partials/_edge_tab.html.twig`, see
[USAGE.md](USAGE.md#page-edge-tab-and-route-links)). The function is always registered; with an empty map it returns
`null`.

```yaml
# config/packages/nowo_ui_kit.yaml
nowo_ui_kit:
    route_links:
        base_url: '%env(DOCS_URL)%'          # prepended to relative paths ("/", "//", "http(s)://" are kept as is)
        default_testid: manual-link-admin    # testid of entries without their own (optional)
        routes:
            admin: manual/05-admin.md         # admin, admin_* …
            admin_users: manual/05-admin.md#users
            admin_mailer: { path: manual/10-email.md, testid: manual-link-mailer }
```

| Key | Default | Description |
|-----|---------|-------------|
| `base_url` | `''` | Prefix of relative paths |
| `default_testid` | `null` | `testid` returned when an entry has none |
| `routes` | `{}` | Route / prefix → path string, or `{path, testid}` (`path` required, non-empty) |

## Error pages

Since **2.6.0**. `@NowoUiKit/error/status.html.twig` renders every HTTP error code on `layout_template`; the host keeps
one file, `templates/bundles/TwigBundle/Exception/error.html.twig`:

```twig
{% extends '@NowoUiKit/error/status.html.twig' %}
```

```yaml
nowo_ui_kit:
    error_pages:
        layout_template: '@NowoUiKit/error/layout.html.twig'   # or a host template extending it (head assets, chat dock…)
        translation_domain: NowoUiKitBundle                    # e.g. messages: the app's own error.* texts
        translation_prefix: error                              # <prefix>.<code>.title|lead|hint|image_alt
        home_path: /
        illustrations:                                         # status code (or default) => asset path without extension
            404: illustrations/error-404                       # <picture>: error-404.webp <source> + error-404.png <img>
            503: { path: illustrations/error-503, width: 1536, height: 1024, webp: false, extension: png }
        illustration_width: 1024
        illustration_height: 683
        webp: true
        retry_statuses: [408]                                  # + every 5xx: "Try again" on GET
        back_link_statuses: [404]                              # "Go back" for a same-site referer
        auto_retry_seconds: 600                                # 5xx on GET reloads itself (0 = never; never on POST)
        theme_storage: nowo-ui-theme                           # data-nowo-ui-theme-storage on <html> and the toggle
        theme_toggle: true
        html_attributes: {}                                    # e.g. { data-csp-strict-nonce: true } — no on*
```

| Key | Default | Description |
|-----|---------|-------------|
| `layout_template` | `@NowoUiKit/error/layout.html.twig` | Document the status template extends (Twig global `nowo_ui_error_pages.layout_template`) |
| `translation_domain` | `NowoUiKitBundle` | Domain of the texts; a key missing there falls back to UiKit's `NowoUiKitBundle` `error.*` |
| `translation_prefix` | `error` | Key prefix: `<prefix>.<code>.title\|lead\|hint\|image_alt`, `<prefix>.generic.*`, `<prefix>.home\|retry\|back\|retry_after\|auto_retry\|skip_to_content` |
| `home_path` | `/` | Href of "Back to home" |
| `illustrations` | `{}` | Code (400–599) or `default` → path string or `{path, width, height, webp, extension}` |
| `illustration_width` / `illustration_height` | `1024` / `683` | Default intrinsic size of the `<img>` |
| `webp` | `true` | Print the `<source type="image/webp">` (`<path>.webp`) |
| `retry_statuses` | `[408]` | Statuses besides 5xx with "Try again" (GET only) |
| `back_link_statuses` | `[404]` | Statuses with "Go back" (same-site referer only) |
| `auto_retry_seconds` | `600` | Self-reload countdown of a 5xx on GET (`js/nowo-ui-error-retry.js`; 0 disables) |
| `theme_storage` | `nowo-ui-theme` | localStorage key of the light / dark theme |
| `theme_toggle` | `true` | Theme toggle in the top controls |
| `html_attributes` | `{}` | Extra `<html>` attributes (validated: no `on*`, scalar values) |

Texts (`nowo_ui_error_text(field, code)`): per-code fields of a code nobody translates use `<prefix>.generic.*`; a known
code without its own `title` / `lead` / `image_alt` uses the generic one, without `hint` none. Blocks, slots and the
host migration: [USAGE.md](USAGE.md#error-pages-26), [UPGRADING.md](UPGRADING.md#to-260).

## App shell

Since **2.6.0**. Options of the signed-in app shell templates (`@NowoUiKit/shell/*`, partials `app_shell_open`,
`app_shell_close`, `app_shell`, `app_shell_burger`); Twig global `nowo_ui_kit_shell`.

```yaml
nowo_ui_kit:
    shell:
        kit_styles: true             # false: only the app_* slot classes (host shell CSS / JS)
        sidebar_cookie: null         # e.g. beacon_sidebar: the aside state is rendered from it on the first paint
        sidebar_hidden_value: collapsed
        sidebar_rail_value: rail
        sidebar_storage: null        # localStorage key for nowo-ui-shell.js (kit_styles + rail_mode)
```

| Key | Default | Description |
|-----|---------|-------------|
| `kit_styles` | `true` | Add the kit shell classes (`nowo-ui-shell`, `nowo-ui-header`, `nowo-ui-aside`, `nowo-ui-main`, `nowo-ui-burger`…) painted by `nowo-ui.css` and driven by `nowo-ui-shell.js`, plus `is-aside-collapsed` / `is-aside-rail` |
| `sidebar_cookie` | `null` | Cookie read server-side when the template passes no `collapsed` / `rail` |
| `sidebar_hidden_value` / `sidebar_rail_value` | `collapsed` / `rail` | Cookie values of the hidden aside and the icons-only rail |
| `sidebar_storage` | `null` | `data-nowo-ui-sidebar-storage` of a three-mode (`rail_mode`) kit-styled shell |

## Examples

### Bootstrap 5 (demo default)

```yaml
nowo_ui_kit:
    css_framework: bootstrap5
    icon_set: bootstrap-icons
    row_actions_display: icon
```

Load Bootstrap (+ Bootstrap Icons) in the **host** layout; the kit emits dual classes (`nowo-ui-btn btn btn-primary`).

### Tailwind

```yaml
nowo_ui_kit:
    css_framework: tailwind
    icon_set: svg_inline
    row_actions_display: icon
```

### Foundation

```yaml
nowo_ui_kit:
    css_framework: foundation
    icon_set: none
    row_actions_display: text
```

### Own design system (Beacon-style)

```yaml
nowo_ui_kit:
    css_framework: custom
    icon_set: ux_icon
    row_actions_display: icon_text
```

Host CSS remaps `--nowo-ui-*` ([THEMING.md](THEMING.md)). Markup uses only semantic classes. Include `asset('js/nowo-ui-modal.js', 'nowo_ui_kit')` for modals.
