# Configuration — Nowo UiKit Bundle

## Table of contents

- [Root keys](#root-keys)
- [Panel path rewrites](#panel-path-rewrites)
- [Release check](#release-check)
- [Appearance](#appearance)
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

Parameters / Twig globals:

- `%nowo_ui_kit.css_framework%` → `nowo_ui_kit_css_framework` (normalized: `bootstrap` → `bootstrap5`)
- `%nowo_ui_kit.icon_set%` → `nowo_ui_kit_icon_set`
- `%nowo_ui_kit.row_actions_display%` → `nowo_ui_kit_row_actions_display`
- `%nowo_ui_kit.panel_path_rewrites%` — the validated rewrite map (no Twig global)
- `%nowo_ui_kit.appearance.enabled%` (no Twig global; use the `nowo_ui_appearance*()` functions)
- `%nowo_ui_kit.release_check.enabled%`, `%nowo_ui_kit.release_check.github_repo%`, `%nowo_ui_kit.release_check.current_version%` → Twig global `nowo_ui_kit_release_check` (`{enabled, current_version}`)

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
