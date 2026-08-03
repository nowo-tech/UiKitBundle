# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/ui-kit-bundle`  
**Last audited**: 2026-08-03

This file proves that **every production source artifact** under `src/` is referenced by the baseline specification. Test-only files (`*.test.ts`) are listed under Tests. Demo trees are out of Packagist scope.

## PHP classes (`src/**/*.php`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `NowoUiKitBundle.php` | Bundle entry | FR-BUNDLE-001 |
| `DependencyInjection/Configuration.php` | Config tree | FR-CFG-001 |
| `DependencyInjection/NowoUiKitExtension.php` | DI extension | FR-CFG-002 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Twig namespace | FR-TWIG-001 |
| `Enum/CssFramework.php` | Framework enum | FR-ENUM-001 |
| `Enum/IconSet.php` | Icon set enum | FR-ENUM-001 |

## Twig / translations / CSS / JS (`src/Resources/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/services.yaml` | Service wiring | FR-CFG-002 |
| `Resources/views/macros/ui.html.twig` | UI macros | FR-MACRO-001 |
| `Resources/views/components/_icon.html.twig` | Icons | FR-ICON-001 |
| `Resources/views/demo/kitchen_sink.html.twig` | Kitchen sink demo template | FR-MACRO-001 |
| `Resources/views/partials/_aside.html.twig` | Shell chrome | FR-PARTIAL-001 |
| `Resources/views/partials/_avatar.html.twig` | Shell chrome | FR-PARTIAL-001 |
| `Resources/views/partials/_burger.html.twig` | Shell chrome | FR-PARTIAL-001 |
| `Resources/views/partials/_empty.html.twig` | Empty state | FR-PARTIAL-001 |
| `Resources/views/partials/_flashes.html.twig` | Flashes | FR-PARTIAL-001 |
| `Resources/views/partials/_footer.html.twig` | Shell chrome | FR-PARTIAL-001 |
| `Resources/views/partials/_modal_shell.html.twig` | Modal shell | FR-PARTIAL-001 |
| `Resources/views/partials/_page_header.html.twig` | Page header | FR-PARTIAL-001 |
| `Resources/views/partials/_pagination.html.twig` | Pagination | FR-PARTIAL-001 |
| `Resources/views/partials/_row_actions.html.twig` | Row actions | FR-PARTIAL-001 |
| `Resources/views/partials/_shell.html.twig` | Composed shell | FR-PARTIAL-001 |
| `Resources/views/partials/_tabs.html.twig` | Tabs | FR-PARTIAL-001 |
| `Resources/views/partials/_user_menu.html.twig` | User menu | FR-PARTIAL-001 |
| `Resources/public/css/nowo-ui.css` | Semantic CSS | FR-CSS-001 |
| `Resources/public/js/nowo-ui-modal.js` | Built modal IIFE | FR-JS-001 / FR-BUILD-001 |
| `Resources/public/js/nowo-ui-shell.js` | Built shell IIFE | FR-JS-001 / FR-BUILD-001 |
| `Resources/translations/NowoUiKitBundle.en.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoUiKitBundle.es.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoUiKitBundle.it.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoUiKitBundle.fr.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoUiKitBundle.pt.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoUiKitBundle.de.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoUiKitBundle.nl.yaml` | i18n | FR-I18N-001 |

## TypeScript sources (`src/Resources/assets/src/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/assets/src/nowo-ui-modal.ts` | Modal runtime source | FR-JS-001 / FR-BUILD-001 |
| `Resources/assets/src/nowo-ui-shell.ts` | Shell runtime source | FR-JS-001 / FR-BUILD-001 |

## Tests (not Packagist runtime units)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/assets/src/nowo-ui-modal.test.ts` | Vitest | FR-TEST-001 |
| `Resources/assets/src/nowo-ui-shell.test.ts` | Vitest | FR-TEST-001 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| PHP classes | 6 | 6 |
| Twig / CSS / JS / i18n / services | 27 | 27 |
| TypeScript sources | 2 | 2 |
| Vitest (excluded from Packagist “production units”) | 2 | 2 |
| **Total production sources (excl. tests)** | **35** | **35** |
