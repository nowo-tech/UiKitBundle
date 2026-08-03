# Installation

This guide covers installing Ui Kit Bundle in a Symfony application.

## Table of contents

- [Requirements](#requirements)
  - [PHP and Symfony matrix](#php-and-symfony-matrix)
- [Install with Composer](#install-with-composer)
- [Register the bundle](#register-the-bundle)
  - [With Symfony Flex](#with-symfony-flex)
  - [Manual registration](#manual-registration)
- [Assets](#assets)
  - [AssetMapper](#assetmapper)
- [Next steps](#next-steps)

## Requirements

- **PHP** `>=8.1` (`<8.6`). Symfony **8.0** and **8.1** require **PHP 8.4+**.
- **Symfony** `^6.0 || ^7.0 || ^8.0` — CI mandatory minors: **7.4**, **8.0**, **8.1**.
- Twig 3.8+ (via `symfony/twig-bundle`).

### PHP and Symfony matrix

| Symfony | Minimum PHP (bundle) | Minimum PHP (Symfony) |
|---------|----------------------|------------------------|
| 6.x / 7.0–7.3 | 8.1 | as per Symfony |
| 7.4     | 8.2                  | 8.2                    |
| 8.0     | 8.4                  | 8.4                    |
| 8.1     | 8.4                  | 8.4                    |

## Install with Composer

```bash
composer require nowo-tech/ui-kit-bundle
```

Packagist: [nowo-tech/ui-kit-bundle](https://packagist.org/packages/nowo-tech/ui-kit-bundle).

## Register the bundle

### With Symfony Flex

The recipe stub under [`.symfony/recipe/nowo-tech/ui-kit-bundle/1.0/`](../.symfony/recipe/nowo-tech/ui-kit-bundle/1.0/) registers the bundle and copies `config/packages/nowo_ui_kit.yaml`. There are **no routes** (Ui Kit is Twig/CSS/JS only). See [RECIPE.md](RECIPE.md). Until the recipe is published on [symfony/recipes-contrib](https://github.com/symfony/recipes-contrib), register manually as below.

### Manual registration

1. **Register the bundle** in `config/bundles.php`:

```php
<?php

return [
    // ...
    Nowo\UiKitBundle\NowoUiKitBundle::class => ['all' => true],
];
```

2. **Create configuration** `config/packages/nowo_ui_kit.yaml`:

```yaml
nowo_ui_kit:
    css_framework: bootstrap5
    icon_set: bootstrap-icons
```

See [Configuration](CONFIGURATION.md) for all enum values.

## Assets

```bash
php bin/console assets:install
```

This publishes `src/Resources/public` to `public/bundles/nowouikit/` (`css/nowo-ui.css`, `js/nowo-ui-modal.js`, `js/nowo-ui-shell.js`). Templates load them via the `nowo_ui_kit` asset package:

```twig
<link rel="stylesheet" href="{{ asset('css/nowo-ui.css', 'nowo_ui_kit') }}">
<script defer src="{{ asset('js/nowo-ui-modal.js', 'nowo_ui_kit') }}"></script>
<script defer src="{{ asset('js/nowo-ui-shell.js', 'nowo_ui_kit') }}"></script>
```

### AssetMapper

The bundle registers the `nowo_ui_kit` asset package (`base_path: /bundles/nowouikit`). Run `assets:install` once so public files are published. Do **not** hard-code `/bundles/nowouikit/...` paths in host layouts.

Contributors rebuild frontend assets with:

```bash
make assets
```

## Next steps

- [Configuration](CONFIGURATION.md)
- [Usage](USAGE.md)
- [Demo FrankenPHP](DEMO-FRANKENPHP.md)
