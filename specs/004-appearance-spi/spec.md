# Feature Specification: Site appearance SPI

**Feature Branch**: `004-appearance-spi`  
**Created**: 2026-10-13  
**Status**: Implemented (1.16.0)  
**Input**: Symfony Beacon spec 138 (`kit-medium-extractions`) **FR-005** — "opt-in `nowo_ui_kit.appearance`:
`Appearance` value object, `AppearanceProviderInterface` (host storage), preset registry (generic presets in the kit,
host presets by config or tag), corner and border style maps, an emitter of `--nowo-ui-*` tokens plus optional
`token_aliases`, a Twig function printing it with the CSP nonce, optional `AppearanceType` form. No entity, no
controller."

**Related docs**: [`docs/THEMING.md`](../../docs/THEMING.md#appearance-spi), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md#appearance),
[`../002-theming-tokens/spec.md`](../002-theming-tokens/spec.md)

---

## Requirements

- **FR-001**: `nowo_ui_kit.appearance.enabled` (default false). Disabled = no service, no Twig function, no output.
- **FR-002**: `Appearance` (immutable): brand name / eyebrow, light + dark `AppearancePalette` (accent, accent deep,
  danger, warn, paper, ink, surface), `themeId` / `themeIdDark` (`custom` when no preset matches), corner style,
  border strength, footer fixed; `toArray()` / `fromArray()` for forms and storage.
- **FR-003**: `AppearanceProviderInterface::getAppearance()` is the host storage SPI (`appearance.provider`); the
  default `ConfigAppearanceProvider` serves `appearance.defaults`.
- **FR-004**: `AppearancePresetRegistry`: config presets, then tagged `AppearancePresetProviderInterface` services
  (`nowo_ui_kit.appearance_preset_provider`, autoconfigured), then the built-ins ocean, slate, sandstone, midnight,
  obsidian, aurora, ember (palettes identical to Beacon's), filterable with `builtin_presets`; a host preset may
  replace a built-in id. Mode-tagged `apply()`, `matchLightId()` / `matchDarkId()`.
- **FR-005**: `CornerStyle` (card / control radii) and `BorderStrength` (ink mix light / dark, width) with the values
  of Beacon's `AppearanceCornerStyles` / `AppearanceBorderStyles`.
- **FR-006**: `AppearanceCssEmitter` writes a root block and a light / dark block (configurable selectors) of
  `--nowo-ui-*` tokens including Beacon's colour-mix derivatives, radii, border width and shadow channels;
  `token_aliases` adds host property names with the same literal value, `kit_tokens: false` writes only the aliases.
  With the documented alias map the output equals Beacon's `SiteAppearanceProvider::getCssOverrides()` for every
  corner / border combination (test). Colours are sanitised; selectors, alias names and fallback channels are
  validated in config.
- **FR-007**: Twig `nowo_ui_appearance()`, `nowo_ui_appearance_css()`, `nowo_ui_appearance_style(nonce)` (request
  `csp_nonce` by default), `nowo_ui_appearance_presets(mode)`.
- **FR-008**: `AppearanceType` / `AppearancePaletteType` registered only when `symfony/form` is installed; colour
  constraint only with `symfony/validator`. Both packages are `suggest`ed; the bundle installs with Twig deps only.

## Out of scope

Persistence (entity, migrations), the admin controller / templates, config export / import.
