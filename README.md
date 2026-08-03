# Nowo UiKit Bundle

[![CI](https://github.com/nowo-tech/UiKitBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/UiKitBundle/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/ui-kit-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/ui-kit-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/ui-kit-bundle.svg)](https://packagist.org/packages/nowo-tech/ui-kit-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-6%20%7C%207.4%20%7C%208.0%20%7C%208.1%2B-000000?logo=symfony)](https://symfony.com) [![GitHub stars](https://img.shields.io/github/stars/nowo-tech/UiKitBundle.svg?style=social&label=Star)](https://github.com/nowo-tech/UiKitBundle) [![Coverage](https://img.shields.io/badge/Coverage-100%25-brightgreen)](#tests-and-coverage)

Canonical **admin UI kit** for Nowo Symfony bundles and host apps: Twig macros, semantic `nowo-ui-*` CSS, pagination/tabs/modals, and multi-framework class helpers (`bootstrap5`, `tailwind`, `foundation`, `custom`, …) per **REQ-UI-001**.

## Quick start

```bash
composer require nowo-tech/ui-kit-bundle
php bin/console assets:install
```

```yaml
# config/packages/nowo_ui_kit.yaml
nowo_ui_kit:
    css_framework: bootstrap5   # or: tailwind | foundation | custom
    icon_set: bootstrap-icons
```

```twig
{% import '@NowoUiKitBundle/macros/ui.html.twig' as ui %}

<link rel="stylesheet" href="{{ asset('css/nowo-ui.css', 'nowo_ui_kit') }}">

<button type="button" class="{{ ui.btn('primary') }}">Save</button>
```

See [docs/USAGE.md](docs/USAGE.md) and [docs/CONFIGURATION.md](docs/CONFIGURATION.md).

## Documentation

- [Installation / quick start](#quick-start) (this README)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Upgrading](docs/UPGRADING.md)
- [Changelog](docs/CHANGELOG.md)
- [Release](docs/RELEASE.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)

## What this is / is not

| Is | Is not |
|----|--------|
| Buttons, toolbars, tables, lists, pagination, tabs, flashes, empty states, modal shell, icons | DashboardMenu / BreadcrumbKit navigation chrome |
| Shared `nowo-ui-*` look-and-feel | Domain CRUD pages |
| Multi-framework class macros | Form widget themes (use FormKit) |

## Demo

```bash
cd demo/symfony8
composer install
php -S 127.0.0.1:8080 -t public
# open http://127.0.0.1:8080/
```

## Tests and coverage

```bash
composer install
composer test
composer test-coverage
```

## License

MIT — see [LICENSE](LICENSE).

Found this useful? Star the repo on [GitHub](https://github.com/nowo-tech/UiKitBundle).
