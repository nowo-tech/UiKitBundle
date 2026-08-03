# Nowo UiKit Bundle

[![CI](https://github.com/nowo-tech/UiKitBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/UiKitBundle/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/ui-kit-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/ui-kit-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/ui-kit-bundle.svg)](https://packagist.org/packages/nowo-tech/ui-kit-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-6%20%7C%207.4%20%7C%208.0%20%7C%208.1%2B-000000?logo=symfony)](https://symfony.com) [![GitHub stars](https://img.shields.io/github/stars/nowo-tech/UiKitBundle.svg?style=social&label=Star)](https://github.com/nowo-tech/UiKitBundle) [![Coverage](https://img.shields.io/badge/Coverage-100%25-brightgreen)](#tests-and-coverage)

> ⭐ **Found this useful?** [Install from Packagist](https://packagist.org/packages/nowo-tech/ui-kit-bundle) · Give it a **star** on [GitHub](https://github.com/nowo-tech/UiKitBundle) so more developers can find it.

Canonical **admin UI kit** for Nowo Symfony bundles and host apps: Twig macros, semantic `nowo-ui-*` CSS, pagination/tabs/modals, shell chrome (aside / burger / avatar / user menu / footer), and multi-framework class helpers (`bootstrap5`, `tailwind`, `foundation`, `custom`, …) per **REQ-UI-001**.

**Compatible with Symfony 6.x, 7.4+, and 8.x** (PHP 8.1+; Symfony 8 requires PHP 8.4+).

![FrankenPHP Friendly Worker Mode](docs/images/frankenphp-friendly.png)

This bundle is **FrankenPHP worker mode friendly**.

## Table of contents

- [What this is / is not](#what-this-is--is-not)
- [Features](#features)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Demos](#demos)
- [Documentation](#documentation)
- [Tests and coverage](#tests-and-coverage)
- [License](#license)
- [Contributing](#contributing)

## What this is / is not

| Is | Is not |
|----|--------|
| Buttons, toolbars, tables, lists, pagination, tabs, flashes, empty states, modal shell, icons, **aside / burger / avatar / user menu / footer** | DashboardMenu / BreadcrumbKit navigation chrome |
| Shared `nowo-ui-*` look-and-feel | Domain CRUD pages / manage routes |
| Multi-framework class macros | Form widget themes (use FormKit) |

## Features

- Twig macros (`@NowoUiKitBundle/macros/ui.html.twig`) with optional per-call `framework` override
- Semantic stylesheet `css/nowo-ui.css` (asset package `nowo_ui_kit`)
- Modal + shell TypeScript → built IIFEs (`js/nowo-ui-modal.js`, `js/nowo-ui-shell.js`)
- Config: `css_framework`, `icon_set`; i18n domain `NowoUiKitBundle` (`en`, `es`, `it`, `fr`, `pt`, `de`, `nl`)
- Twig namespace overrides under `templates/bundles/NowoUiKitBundle/`

## Installation

```bash
composer require nowo-tech/ui-kit-bundle
php bin/console assets:install
```

With Flex, the recipe creates `config/packages/nowo_ui_kit.yaml`. See [docs/INSTALLATION.md](docs/INSTALLATION.md) and [docs/RECIPE.md](docs/RECIPE.md).

## Quick start

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

## Demos

FrankenPHP demos (Caddy + PHP). Runtime mode: **`FRANKENPHP_MODE`** (`worker` default; `classic` for per-request PHP). See [docs/DEMO-FRANKENPHP.md](docs/DEMO-FRANKENPHP.md).

| Demo | Port | Notes |
|------|------|--------|
| [demo/symfony8](demo/symfony8/) | 8092 | Bootstrap 5 kitchen sink |
| [demo/symfony8-tailwind](demo/symfony8-tailwind/) | 8093 | Tailwind kitchen sink |

```bash
make -C demo up-symfony8
# open http://localhost:8092/
```

## Documentation

Developer-facing docs are **English only** ([Contributing — Language policy](docs/CONTRIBUTING.md#language-policy)).

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Flex recipe](docs/RECIPE.md)
- [FrankenPHP demos](docs/DEMO-FRANKENPHP.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Security](docs/SECURITY.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Coverage](docs/COVERAGE.md)
- [GitHub Actions CI](docs/GITHUB_CI.md)
- [Spec-driven development](docs/SPEC-DRIVEN-DEVELOPMENT.md)
- [Spec Kit](docs/SPEC-KIT.md)
- [Roadmap](docs/ROADMAP.md)

## Tests and coverage

| Suite | Coverage |
|-------|----------|
| PHP (PHPUnit) | 100% lines (fail under 99% in CI) |
| TypeScript (Vitest) | ~96.7% lines (threshold ≥90%) |
| Python | N/A |

```bash
composer install
make test
make test-coverage
make test-ts
pnpm install && pnpm run build
```

See [docs/COVERAGE.md](docs/COVERAGE.md).

## License

MIT — see [LICENSE](LICENSE).

## Contributing

See [docs/CONTRIBUTING.md](docs/CONTRIBUTING.md). Install hooks with `make setup-hooks` (REQ-GIT-001).
