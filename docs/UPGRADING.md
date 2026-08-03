# Upgrading

## Table of contents

- [From 1.0.x to 1.1.0](#from-10x-to-110)
- [From nothing to 1.0.0](#from-nothing-to-100)

## From 1.0.x to 1.1.0

Additive release — no breaking Twig/CSS API changes.

1. `composer update nowo-tech/ui-kit-bundle`
2. `php bin/console assets:install` (new `js/nowo-ui-shell.js`)
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
