# Upgrading

## Table of contents


- [From 1.8.2 to 1.8.3](#from-182-to-183)
- [Unreleased](#unreleased)
- [To 1.8.2](#to-182)
- [To 1.8.1](#to-181)
- [To 1.8.0](#to-180)
- [To 1.6.0](#to-160)
- [To 1.5.1](#to-151)
- [To 1.5.0](#to-150)
- [From 1.3.x to 1.4.0](#from-13x-to-140)
- [From 1.2.x to 1.3.0](#from-12x-to-130)
- [From 1.2.0 to 1.2.1](#from-120-to-121)
- [From 1.1.x to 1.2.0](#from-11x-to-120)
- [From 1.0.x to 1.1.0](#from-10x-to-110)
- [From nothing to 1.0.0](#from-nothing-to-100)

## From 1.8.2 to 1.8.3

Review the [CHANGELOG](CHANGELOG.md) entry. PHP **8.2+** may now be required.

```bash
composer update nowo-tech/ui-kit-bundle
```

## From 1.8.2 to 1.8.3

Review the [CHANGELOG](CHANGELOG.md) entry. PHP **8.2+** may now be required.

```bash
composer update nowo-tech/ui-kit-bundle
```


## Unreleased

## To 1.8.2

From **1.8.1** — No application upgrade steps.

```bash
composer update nowo-tech/ui-kit-bundle
```

## To 1.8.1

From **1.8.0** — No application upgrade steps. **Demos only:** Hot Reload Bundle `^1.4` (FrankenPHP Mercure/`hot_reload`, `dev`/`test`).

```bash
composer update nowo-tech/ui-kit-bundle
```

## To 1.8.0

From **1.7.x** — **Additive** clipboard/tabs IIFEs and optional Stimulus peer TypeScript sources. No breaking Twig/CSS API changes. Contributors: `pnpm run build` emits **nine** IIFEs (adds `clipboard`, `tabs`). Optional Stimulus peers under `src/Resources/assets/stimulus-peers/`. See [STIMULUS.md](STIMULUS.md).

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console assets:install
```

### New optional scripts

```twig
<script src="{{ asset('js/nowo-ui-clipboard.js', 'nowo_ui_kit') }}" defer></script>
<script src="{{ asset('js/nowo-ui-tabs.js', 'nowo_ui_kit') }}" defer></script>
```

### Stimulus peers (optional)

If the host uses Symfony UX Stimulus, you may import peers from:

`vendor/nowo-tech/ui-kit-bundle/src/Resources/assets/stimulus-peers/`

and **omit** the matching IIFE for that behaviour. See [STIMULUS.md](STIMULUS.md).

### Beacon hosts

Prefer `data-nowo-ui-*` contracts. Legacy Beacon attrs (`data-confirm-dialog-close`, `data-tab-id`, Stimulus value attrs) remain documented for migration; delete duplicated host controllers once re-exported from the vendor peers.

## To 1.6.0

From **1.5.x** — **Row action display** (`icon` / `text` / `icon_text`) and the expanded canonical `_row_actions` API. Default display remains **icon-only** (same as 1.4/1.5).

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console cache:clear
php bin/console assets:install
```

### Row actions display (REQ-UI-001)

Optional additive config:

```yaml
nowo_ui_kit:
    row_actions_display: text   # or icon | icon_text
```

`_row_actions.html.twig` also accepts a per-include `display` argument and the full action hash (`tag`, `method`, `confirm_id`, …). See [ROW_ACTIONS.md](ROW_ACTIONS.md) and [USAGE.md](USAGE.md).

### Default `ui.action` variants

`ui.action(kind)` defaults: `edit` / most kinds → **secondary**; `delete` → danger; `create`/`save` → primary. Pass a third macro arg `variant` to override.

## To 1.5.1

From **1.5.0** — CI/test-only fix for Thinking Orb TypeScript types. No host migration.

```bash
composer update nowo-tech/ui-kit-bundle
```

## To 1.5.0

From **1.4.0** — Adds required Twig Extra (REQ-TWIG-004) and Twig-CS-Fixer. Register TwigExtraBundle if Flex did not.

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console cache:clear
```

### Twig Extra Bundle (REQ-TWIG-004)

Hosts that render this bundle's Twig templates must install:

```bash
composer require twig/extra-bundle twig/string-extra
```

and enable `Twig\Extra\TwigExtraBundle\TwigExtraBundle`. Flex recipes usually register it automatically.

### Twig-CS-Fixer (maintainers)

Package maintainers: `composer twig:lint` / `composer twig:fix` use `.twig-cs-fixer.php` over `src/` (and `templates/` when present).


## From 1.3.x to 1.4.0

**Additive** Twig/CSS/JS API — no breaking changes for existing macros/partials.

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console assets:install
```

Optional:

- Include new scripts (`nowo-ui-toast.js`, `nowo-ui-confirm.js`, `nowo-ui-page-loader.js`, `nowo-ui-theme.js`, `nowo-ui-orb.js`) where you adopt those partials.
- Page loader: pass `visual: 'orb'` to use Thinking Orbs instead of the CSS spinner (default remains `spinner`).
- Shell: include `_width_toggle.html.twig` for full ↔ content main width (persisted in `localStorage`).
- Migrate host/feature UI toward kit partials ([ADOPTION.md](ADOPTION.md), [STIMULUS.md](STIMULUS.md)).
- Contributors: `pnpm run build` now emits **seven** IIFEs (adds `orb`). See [THIRD_PARTY.md](THIRD_PARTY.md) for the Thinking Orbs MIT notice.

`ui.badge('success')` sets a variant; `ui.badge('tailwind')` remains a framework override (unchanged BC).

If `icon_set: ux_icon`, install `symfony/ux-icons` — the Twig call lives in `_icon_ux.html.twig` so other icon sets compile without that package.

## From 1.2.x to 1.3.0

**No breaking Twig/CSS/JS API changes** for application consumers.

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console assets:install
```

Optional:

- Adopt Flex recipe defaults in `config/packages/nowo_ui_kit.yaml` if you previously copied config by hand ([RECIPE.md](RECIPE.md)).
- Contributors: use `make setup-hooks`, Docker `make test` / `make coverage-check`, and FrankenPHP demos (`make -C demo up-symfony8`).

## From 1.2.0 to 1.2.1

No consumer-facing changes. CI-only fix for `pnpm/action-setup` vs `packageManager`.

```bash
composer update nowo-tech/ui-kit-bundle
```

## From 1.1.x to 1.2.0

Additive for consumers of published `js/*.js` paths (same asset URLs).

Maintainers / contributors building from source:

```bash
pnpm install
pnpm run build
```

Hand-edited `src/Resources/public/js/*.js` is no longer the source of truth — edit TypeScript under `src/Resources/assets/src/` and rebuild.

## From 1.0.x to 1.1.0

Additive release — no breaking Twig/CSS API changes.

1. `composer update nowo-tech/ui-kit-bundle`
2. `php bin/console assets:install` (rebuild assets with `pnpm run build` if developing from git)
3. Optionally adopt chrome partials:

```twig
<script src="{{ asset('js/nowo-ui-shell.js', 'nowo_ui_kit') }}" defer></script>
{% include '@NowoUiKitBundle/partials/_shell.html.twig' with { … } %}
```

## From nothing to 1.0.0

Initial release — no upgrade path.

### Install

```bash
composer require nowo-tech/ui-kit-bundle:^1.0
php bin/console assets:install
```

```yaml
# config/packages/nowo_ui_kit.yaml
nowo_ui_kit:
    css_framework: bootstrap5   # or: tailwind | foundation | custom | …
    icon_set: bootstrap-icons
```

Twig namespace: `@NowoUiKitBundle/…`  
Overrides: `templates/bundles/NowoUiKitBundle/<subpath>`  
Asset package: `nowo_ui_kit` (`asset('css/nowo-ui.css', 'nowo_ui_kit')`).

See [USAGE.md](USAGE.md) and [CONFIGURATION.md](CONFIGURATION.md).
