# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/ui-kit-bundle`  
**Last audited**: 2026-08-04 (1.4.0 surfaces)

This file proves that **every production source artifact** under `src/` is referenced by the baseline specification. Test-only files (`*.test.ts`) are listed under Tests. Demo *apps* under `demo/` are out of Packagist scope; the kitchen sink Twig template inside the bundle is included.

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
| `Resources/views/components/_icon_ux.html.twig` | Optional UX Icons | FR-ICON-001 |
| `Resources/views/demo/kitchen_sink.html.twig` | Kitchen sink demo template | FR-MACRO-001 / FR-ORB-001 |
| `Resources/views/partials/_aside.html.twig` | Shell chrome | FR-PARTIAL-001 |
| `Resources/views/partials/_aside_nav_items.html.twig` | Nested aside nav | FR-PARTIAL-001 |
| `Resources/views/partials/_avatar.html.twig` | Shell chrome | FR-PARTIAL-001 |
| `Resources/views/partials/_brand.html.twig` | Brand | FR-PARTIAL-001 |
| `Resources/views/partials/_burger.html.twig` | Shell chrome | FR-PARTIAL-001 |
| `Resources/views/partials/_card.html.twig` | Card / panel | FR-PARTIAL-001 |
| `Resources/views/partials/_confirm.html.twig` | Confirm dialog | FR-PARTIAL-001 |
| `Resources/views/partials/_empty.html.twig` | Empty state | FR-PARTIAL-001 |
| `Resources/views/partials/_filters.html.twig` | Filters | FR-PARTIAL-001 |
| `Resources/views/partials/_flashes.html.twig` | Flashes | FR-PARTIAL-001 |
| `Resources/views/partials/_footer.html.twig` | Shell chrome | FR-PARTIAL-001 |
| `Resources/views/partials/_kebab.html.twig` | Overflow menu | FR-PARTIAL-001 |
| `Resources/views/partials/_locale_switcher.html.twig` | Locale switcher | FR-PARTIAL-001 |
| `Resources/views/partials/_modal_shell.html.twig` | Modal shell | FR-PARTIAL-001 |
| `Resources/views/partials/_page_header.html.twig` | Page header | FR-PARTIAL-001 |
| `Resources/views/partials/_page_loader.html.twig` | Page loader | FR-PARTIAL-001 / FR-ORB-002 |
| `Resources/views/partials/_pagination.html.twig` | Pagination | FR-PARTIAL-001 |
| `Resources/views/partials/_row_actions.html.twig` | Row actions | FR-PARTIAL-001 |
| `Resources/views/partials/_shell.html.twig` | Composed shell | FR-PARTIAL-001 |
| `Resources/views/partials/_tabs.html.twig` | Tabs | FR-PARTIAL-001 |
| `Resources/views/partials/_theme_toggle.html.twig` | Theme toggle | FR-PARTIAL-001 |
| `Resources/views/partials/_thinking_orb.html.twig` | Thinking orb | FR-PARTIAL-001 / FR-ORB-001 |
| `Resources/views/partials/_toasts.html.twig` | Toast stack | FR-PARTIAL-001 |
| `Resources/views/partials/_user_menu.html.twig` | User menu | FR-PARTIAL-001 |
| `Resources/views/partials/_width_toggle.html.twig` | Main width toggle | FR-PARTIAL-001 |
| `Resources/public/css/nowo-ui.css` | Semantic CSS | FR-CSS-001 |
| `Resources/public/js/nowo-ui-modal.js` | Built modal IIFE | FR-JS-001 / FR-BUILD-001 |
| `Resources/public/js/nowo-ui-shell.js` | Built shell IIFE | FR-JS-001 / FR-BUILD-001 |
| `Resources/public/js/nowo-ui-toast.js` | Built toast IIFE | FR-JS-001 / FR-BUILD-001 |
| `Resources/public/js/nowo-ui-confirm.js` | Built confirm IIFE | FR-JS-001 / FR-BUILD-001 |
| `Resources/public/js/nowo-ui-page-loader.js` | Built page-loader IIFE | FR-JS-001 / FR-BUILD-001 |
| `Resources/public/js/nowo-ui-theme.js` | Built theme IIFE | FR-JS-001 / FR-BUILD-001 |
| `Resources/public/js/nowo-ui-orb.js` | Built orb IIFE | FR-JS-001 / FR-ORB-001 / FR-BUILD-001 |
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
| `Resources/assets/src/nowo-ui-modal.ts` | Modal runtime | FR-JS-001 / FR-BUILD-001 |
| `Resources/assets/src/nowo-ui-shell.ts` | Shell runtime | FR-JS-001 / FR-BUILD-001 |
| `Resources/assets/src/nowo-ui-toast.ts` | Toast runtime | FR-JS-001 / FR-BUILD-001 |
| `Resources/assets/src/nowo-ui-confirm.ts` | Confirm runtime | FR-JS-001 / FR-BUILD-001 |
| `Resources/assets/src/nowo-ui-page-loader.ts` | Page loader runtime | FR-JS-001 / FR-BUILD-001 |
| `Resources/assets/src/nowo-ui-theme.ts` | Theme runtime | FR-JS-001 / FR-BUILD-001 |
| `Resources/assets/src/nowo-ui-orb.ts` | Orb auto-mount | FR-JS-001 / FR-ORB-001 / FR-BUILD-001 |
| `Resources/assets/src/orb/types.ts` | Orb types | FR-ORB-001 |
| `Resources/assets/src/orb/theme.ts` | Orb theme helpers | FR-ORB-001 |
| `Resources/assets/src/orb/mount.ts` | Orb mount API | FR-ORB-001 |
| `Resources/assets/src/orb/presets.ts` | Orb presets (vendored) | FR-ORB-001 |
| `Resources/assets/src/orb/engine/types.ts` | Engine contracts | FR-ORB-001 |
| `Resources/assets/src/orb/engine/core.ts` | Engine paint core | FR-ORB-001 |
| `Resources/assets/src/orb/engine/profiles.ts` | Engine profiles | FR-ORB-001 |
| `Resources/assets/src/orb/engine/registry.ts` | Mode registry | FR-ORB-001 |
| `Resources/assets/src/orb/engine/orbits.ts` | Mode: orbits | FR-ORB-001 |
| `Resources/assets/src/orb/engine/lattice.ts` | Mode: globe/rubik/wave | FR-ORB-001 |
| `Resources/assets/src/orb/engine/web.ts` | Mode: web | FR-ORB-001 |
| `Resources/assets/src/orb/engine/braid.ts` | Mode: braid | FR-ORB-001 |
| `Resources/assets/src/orb/engine/ribbon.ts` | Mode: ribbon/ring | FR-ORB-001 |
| `Resources/assets/src/orb/engine/morph.ts` | Mode: morph | FR-ORB-001 |

## Tests (not Packagist runtime units)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/assets/src/nowo-ui-modal.test.ts` | Vitest | FR-TEST-001 |
| `Resources/assets/src/nowo-ui-shell.test.ts` | Vitest | FR-TEST-001 |
| `Resources/assets/src/nowo-ui-toast.test.ts` | Vitest | FR-TEST-001 |
| `Resources/assets/src/nowo-ui-confirm.test.ts` | Vitest | FR-TEST-001 |
| `Resources/assets/src/nowo-ui-page-loader.test.ts` | Vitest | FR-TEST-001 |
| `Resources/assets/src/nowo-ui-theme.test.ts` | Vitest | FR-TEST-001 |
| `Resources/assets/src/nowo-ui-orb.test.ts` | Vitest | FR-TEST-001 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| PHP classes | 6 | 6 |
| Twig / CSS / JS / i18n / services | 45 | 45 |
| TypeScript sources (excl. tests) | 21 | 21 |
| Vitest (excluded from Packagist “production units”) | 7 | 7 |
| **Total production sources (excl. tests)** | **72** | **72** |
