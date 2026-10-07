# Configuration — Nowo UiKit Bundle

## Table of contents

- [Root keys](#root-keys)
- [Panel path rewrites](#panel-path-rewrites)
- [Examples](#examples)

## Root keys

Alias: **`nowo_ui_kit`**

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `css_framework` | enum | `bootstrap5` | `bootstrap` (alias of `bootstrap5`), `bootstrap4`, `bootstrap5`, `tabler`, `tailwind`, `foundation`, `custom`, `none` |
| `icon_set` | enum | `bootstrap-icons` | `bootstrap-icons`, `tabler-icons`, `ux_icon`, `svg_inline`, `none` — how glyphs are drawn |
| `row_actions_display` | enum | `icon` | Table/list row actions: `icon` (glyph + visually hidden label), `text` (visible label only), `icon_text` (glyph + visible label) |
| `panel_path_rewrites` | map | `{}` | Legacy path prefix → new prefix (e.g. `/admin/blog: /panel/blog`). Empty = feature disabled (since 1.9.0) |

Parameters / Twig globals:

- `%nowo_ui_kit.css_framework%` → `nowo_ui_kit_css_framework` (normalized: `bootstrap` → `bootstrap5`)
- `%nowo_ui_kit.icon_set%` → `nowo_ui_kit_icon_set`
- `%nowo_ui_kit.row_actions_display%` → `nowo_ui_kit_row_actions_display`
- `%nowo_ui_kit.panel_path_rewrites%` — the validated rewrite map (no Twig global)

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
- The target must differ from the source and must not be nested under it (`/admin` → `/admin/panel` is rejected to avoid redirect loops).
- Route names and URL generation are unaffected (routes keep their names; generated URLs use the new paths).
- With the default empty map, nothing is registered — behavior is identical to 1.8.x.

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

Host CSS remaps `--nowo-ui-*`. Markup uses only semantic classes. Include `asset('js/nowo-ui-modal.js', 'nowo_ui_kit')` for modals.
