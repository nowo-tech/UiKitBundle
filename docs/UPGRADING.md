# Upgrading

## Table of contents

- [Unreleased](#unreleased)
- [To 1.5.0](#to-150)
- [From 1.3.x to 1.4.0](#from-13x-to-140)
- [From 1.2.x to 1.3.0](#from-12x-to-130)
- [From 1.2.0 to 1.2.1](#from-120-to-121)
- [From 1.1.x to 1.2.0](#from-11x-to-120)
- [From 1.0.x to 1.1.0](#from-10x-to-110)
- [From nothing to 1.0.0](#from-nothing-to-100)


## Unreleased

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
