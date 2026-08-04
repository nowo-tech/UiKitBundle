# Configuration — Nowo UiKit Bundle

## Table of contents

- [Root keys](#root-keys)
- [Examples](#examples)

## Root keys

Alias: **`nowo_ui_kit`**

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `css_framework` | enum | `bootstrap5` | `bootstrap` (alias of `bootstrap5`), `bootstrap4`, `bootstrap5`, `tabler`, `tailwind`, `foundation`, `custom`, `none` |
| `icon_set` | enum | `bootstrap-icons` | `bootstrap-icons`, `tabler-icons`, `ux_icon`, `svg_inline`, `none` — how glyphs are drawn |
| `row_actions_display` | enum | `icon` | Table/list row actions: `icon` (glyph + visually hidden label), `text` (visible label only), `icon_text` (glyph + visible label) |

Parameters / Twig globals:

- `%nowo_ui_kit.css_framework%` → `nowo_ui_kit_css_framework` (normalized: `bootstrap` → `bootstrap5`)
- `%nowo_ui_kit.icon_set%` → `nowo_ui_kit_icon_set`
- `%nowo_ui_kit.row_actions_display%` → `nowo_ui_kit_row_actions_display`

Asset package (always prepended): `nowo_ui_kit` → `/bundles/nowouikit`.

`icon_set` and `row_actions_display` are independent: with `row_actions_display: text`, `_row_actions` does not emit glyphs (regardless of `icon_set`).

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
