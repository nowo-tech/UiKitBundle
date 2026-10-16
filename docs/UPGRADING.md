# Upgrading

## Table of contents

- [Unreleased](#unreleased)
- [To 1.17.4](#to-1174)
- [To 1.17.3](#to-1173)
- [To 1.17.2](#to-1172)
- [To 1.17.1](#to-1171)
- [To 1.17.0](#to-1170)
- [To 1.16.0](#to-1160)
- [To 1.15.0](#to-1150)
- [To 1.14.0](#to-1140)
- [To 1.13.2](#to-1132)
- [To 1.13.1](#to-1131)
- [To 1.13.0](#to-1130)
- [To 1.12.0](#to-1120)
- [To 1.11.0](#to-1110)
- [To 1.10.1](#to-1101)
- [To 1.10.0](#to-1100)
- [To 1.9.2](#to-192)
- [To 1.9.1](#to-191)
- [To 1.9.0](#to-190)
- [To 1.8.5](#to-185)
- [From 1.8.3 to 1.8.4](#from-183-to-184)
- [From 1.8.2 to 1.8.3](#from-182-to-183)
- [To 1.8.2](#to-182)
- [To 1.8.1](#to-181)
- [To 1.8.0](#to-180)
  - [New optional scripts](#new-optional-scripts)
  - [Stimulus peers (optional)](#stimulus-peers-optional)
  - [Beacon hosts](#beacon-hosts)
- [To 1.6.0](#to-160)
  - [Row actions display (REQ-UI-001)](#row-actions-display-req-ui-001)
  - [Default `ui.action` variants](#default-uiaction-variants)
- [To 1.5.1](#to-151)
- [To 1.5.0](#to-150)
  - [Twig Extra Bundle (REQ-TWIG-004)](#twig-extra-bundle-req-twig-004)
  - [Twig-CS-Fixer (maintainers)](#twig-cs-fixer-maintainers)
- [From 1.3.x to 1.4.0](#from-13x-to-140)
- [From 1.2.x to 1.3.0](#from-12x-to-130)
- [From 1.2.0 to 1.2.1](#from-120-to-121)
- [From 1.1.x to 1.2.0](#from-11x-to-120)
- [From 1.0.x to 1.1.0](#from-10x-to-110)
- [From nothing to 1.0.0](#from-nothing-to-100)
  - [Install](#install)

## Unreleased

**Template contract (REQ-UI-003, new — no breaking changes).** Everything below is additive; existing partials and
macros keep their 1.x inputs and output.

- New layouts: `@NowoUiKit/layout/document.html.twig`, `layout/admin.html.twig`, `layout/guest.html.twig`
  (blocks `nowo_ui_document_title`, `nowo_ui_head`, `nowo_ui_styles`, `nowo_ui_scripts`, `nowo_ui_body`,
  `nowo_ui_navigation` (admin), `nowo_ui_page_header`, `nowo_ui_flashes`, `nowo_ui_content`, `nowo_ui_modals`).
  Point `nowo_ui_kit.layouts.admin` / `guest` at your layout or a one-file bridge that renders these blocks.
- New scaffolds `@NowoUiKit/page/{list,show,form,settings,dashboard,confirm,wizard,auth}.html.twig` (variable
  `page`, data blocks `nowo_ui_list_head`, `nowo_ui_list_row`, `nowo_ui_list_after`, `nowo_ui_show_sections`,
  `nowo_ui_show_after`, `nowo_ui_form_body`, `nowo_ui_form_aside`, `nowo_ui_settings_section`,
  `nowo_ui_dashboard_cards`, `nowo_ui_wizard_step`, `nowo_ui_auth_fields`, `nowo_ui_auth_actions`,
  `nowo_ui_auth_footer`).
- New view models under `Nowo\UiKitBundle\View\` and theme config `nowo_ui_kit.theme` (classes, attributes,
  partials, tokens, tokens_dark, bundles) with the Twig functions `nowo_ui_class()`, `nowo_ui_attrs()`,
  `nowo_ui_partial()`, `nowo_ui_theme_tokens()`. See [`PAGE_CONTRACT.md`](PAGE_CONTRACT.md).
- Hosts that set `theme.tokens` and render the UiKit document layout get `data-nowo-ui-host-tokens` on `<html>`:
  the kit palette then never beats a host declaration, so `!important` remaps of `--nowo-ui-*` can be removed.
  Hosts with their own layout add the attribute themselves and print `{{ nowo_ui_theme_tokens() }}` after
  `nowo-ui.css`.
- Hosts that override `_pagination`, `_toasts`, `_flashes`, `_page_header`, `_row_actions`, `_confirm`, `_empty`,
  `_shell` or `_burger` keep working; to pick up view-model inputs, re-sync from the bundle (or replace the fork with
  `theme.classes` / `theme.attributes` / `theme.partials`).

## To 1.17.4

From **1.17.3** — nothing to change for hosts: same runtime code and CSS (maintainer release gate only). Prefer 1.17.4
over 1.17.3.

## To 1.17.3

From **1.17.2** — CSS fix only, nothing to change. Responsive tables hide columns again before splitting words: above
30rem cell text wraps at spaces only (`overflow-wrap: break-word`), mid-word breaks (`anywhere`) are back to narrow
screens (≤ 30rem). Host workarounds for "Symf ony"-style breaks in responsive tables can be removed.

## To 1.17.2

From **1.17.1** — CSS fixes only, nothing to change. Kit dialogs (`.nowo-ui-confirm`, row detail) centre under
Tailwind preflight; in Responsive table mode `nowrap` content inside cells wraps. Hosts that worked around either
(their own `dialog { margin: auto }`, `.whitespace-nowrap` overrides in responsive tables) can drop the workaround.
Cell content that must never wrap in Responsive mode needs a host rule with higher specificity and `!important`.

## To 1.17.1

From **1.17.0** — patch, nothing to change. Optional: pass `attr` to `_responsive_table.html.twig` for extra root
attributes, and show your error toast from `responsive-table:save-failed` using `event.detail.message` (the
`labels.save_error` text, default `table_mode.save_error`) instead of subclassing the peer:

```ts
document.addEventListener('responsive-table:save-failed', (event) => {
  showToast({ title: (event as CustomEvent<{ message: string }>).detail.message, tone: 'error' });
});
```

## To 1.17.0

From **1.16.0** — host chrome: responsive tables, foldable filter cards, corner / edge tabs, thin scrollbars,
optional morphicons. Everything is opt-in (new partials, classes, peers, config key).

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console assets:install
```

- No breaking changes; no new Composer dependency. New optional **JS** peer dependencies `morphicons` (^1.7) and
  `lucide` (^1.0), only for hosts that import `assets/morphicons`.
- Responsive tables: embed `partials/_responsive_table.html.twig`, add `data-column` (+ `data-required="1"`) to the
  header cells, `data-column` to the row cells, `_row_detail_head` / `_row_detail_cell` at the end of each row, and
  register the `responsive-table` peer. Persisting the mode needs a host endpoint (JSON `{"mode"}`, `X-CSRF-TOKEN`).
- Foldable filters: `partials/_filters_collapse.html.twig` as the first child of the filter form (or
  `collapsible: true` on `_filters.html.twig`) + the `filters-collapse` peer.
- Animated icons: call `installMorphiconPeers()` from `assets/morphicons` once in the Stimulus bootstrap and pass
  `morph: true` to the partials.
- Thin scrollbars: `data-nowo-ui-thin-scrollbars` on `<html>` (or `.nowo-ui-thin-scroll`, or the SCSS mixin).
- Route links: `nowo_ui_kit.route_links` + `nowo_ui_route_link()` with `partials/_edge_tab.html.twig`.
- Hosts that override `components/_icon.html.twig` should add the new canonical kinds (`filter_off`,
  `scroll_horizontal`, `rows`, `chevron_up`, `chevron_down`, `chevron_right`, `book`) or pass `icons` to the new
  partials.

## To 1.16.0

From **1.15.0** — optional appearance SPI. Nothing changes unless `nowo_ui_kit.appearance.enabled` is set.

```bash
composer update nowo-tech/ui-kit-bundle
```

- No breaking changes; no new required dependency (`symfony/form` / `symfony/validator` are only suggested for the optional form types).
- To adopt: enable `appearance`, point `appearance.provider` at a service implementing `AppearanceProviderInterface` (map your stored settings to an `Appearance`), register your own presets in `appearance.presets` (or a tagged `AppearancePresetProviderInterface`), and print `{{ nowo_ui_appearance_style() }}` after `nowo-ui.css`. Hosts that already emit their own variables can keep the names with `token_aliases` (+ `kit_tokens: false` for an identical stylesheet) — see THEMING.md — Appearance SPI.

## To 1.15.0

From **1.14.0** — icon filter actions, pre-paint theme boot, sidebar rail, three Stimulus peers. Everything is opt-in.

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console assets:install
```

- No breaking changes. New options render nothing until used.
- `_burger.html.twig` now honours `expanded: false` (`aria-expanded="false"`); shells rendered with `collapsed: true` get the correct state.
- `nowo-ui-theme.js` also reads `data-nowo-ui-theme-storage` from `<html>` (the toggle attribute still wins) and writes the optional `data-nowo-ui-theme-cookie`.
- To use the theme boot, print your preference attributes on `<html>` and include `@NowoUiKitBundle/partials/_theme_boot.html.twig` first in `<head>` (see USAGE.md — Pre-paint theme boot). Hosts with their own boot script can keep it: the output attributes are the same (`data-theme`, `data-ui-density`, `data-motion`, `data-font-scale`, `data-contrast`).
- The `datatable` peer needs `datatables.net-dt` (^2.3) and `datatables.net-responsive-dt` (^3.0) in the host, plus their stylesheets; the other peers have no extra dependency.
- Moving from a host sidebar that stores `collapsed`: set `data-nowo-ui-sidebar-hidden-value="collapsed"` and keep your storage / cookie names through the `data-nowo-ui-sidebar-*` attributes, so stored state and server rendering keep working.

## To 1.14.0

From **1.13.2** — Symfony >=7.4, host translator settings respected, release-check failures cached, vitest 4.

```bash
composer update nowo-tech/ui-kit-bundle
```

- **Symfony floor raised to `^7.4 || ^8.0`** (was `^6.0 || ^7.0 || ^8.0`; only 7.4 / 8.x were ever tested in CI). Apps on Symfony 6.x or 7.0–7.3 stay on 1.13.x until they upgrade Symfony; Composer will not install this version for them.
- The bundle no longer prepends `framework.translator` (`paths` + `fallbacks: [en]`). Its `NowoUiKitBundle` catalogue is still auto-discovered by FrameworkBundle; your own `fallbacks` order is now respected (`en` is no longer forced first). If you relied on the bundle to turn the translator on (rare: only setups where FrameworkBundle does not enable it by default), set `framework.translator.enabled: true` yourself.
- Release check: failed GitHub fetches are now cached for 5 minutes (`status: error`) instead of being retried on every request. The `/_nowo-ui/release/status` endpoint is still anonymous by default and reveals the installed version — import it behind your admin firewall (see CONFIGURATION.md — Release check).

## To 1.13.2

From **1.13.1** — security: attribute-safe row-action confirm handler, panel path rewrite hardening.

```bash
composer update nowo-tech/ui-kit-bundle
```

- Hosts that override `_row_actions.html.twig` should apply the same `|e('html_attr')` fix to the `confirm()` handler.
- `panel_path_rewrites` entries starting with `//` or containing a backslash now fail config validation.

## To 1.13.1

From **1.13.0** — dependency refresh and repository maintenance.

```bash
composer update nowo-tech/ui-kit-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.13.0

From **1.12.0** — theming tokens; no removed `nowo-ui-*` classes, macro arguments or partial variables.

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console assets:install
php bin/console cache:clear
```

- **Default look is unchanged** in light mode for every stack (same base token values). Visible differences are limited to new interaction states (`:focus-visible` rings, hover on kit-painted buttons / pagination / inactive tabs / plain table rows), dark-mode defaults for warning / info and for `.nowo-ui-input`, and the pagination list laid out as a row.
- **Tailwind hosts:** macros no longer emit palette / radius / divide utilities (`bg-blue-600`, `border-slate-300`, `rounded-md`, `divide-y`, …). If your CSS targeted those classes inside kit output (e.g. `.nowo-ui-btn--primary.bg-blue-600`, `.text-slate-500`, `.hover\:bg-blue-500:hover`, `.divide-slate-200 > * + *`), replace the rules with tokens — see [THEMING.md](THEMING.md). If you relied on them being present in markup (tests, JS selectors), select the `nowo-ui-*` class instead.
- **Theme with tokens instead of selector remaps:** move `--nowo-ui-*` remaps to one `html:root { … }` block (beats the kit's `:root` and `[data-theme="dark"]` regardless of load order) and delete colour overrides of `.nowo-ui-btn*`, `.nowo-ui-input`, `.nowo-ui-table*`, `.nowo-ui-tabs__item*`, `.nowo-ui-pagination__link*`, `.nowo-ui-list*`, `.nowo-ui-badge*`. Remaining host CSS should only be product layout (heights, paddings, spacing).
- **Bootstrap hosts:** nothing changes unless you add `class="nowo-ui-bs-tokens"` on an ancestor; then Bootstrap's components follow the `--nowo-ui-*` tokens.
- `ui.btn('outline')` now maps to `btn-outline-primary` on Bootstrap stacks (was the generic `btn-outline-secondary`) and to the new `.nowo-ui-btn--outline` elsewhere.

## To 1.12.0

From **1.11.0** — new optional features only; no breaking changes.

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console assets:install
php bin/console cache:clear
```

- **No action required.** The new CSS classes (`.nowo-ui-filter-bar*`, `.nowo-ui-data-table*`), partials and script only apply when used.
- `?new=1` create-in-modal deep links: include `_open_modal_on_load.html.twig` (`modal`, `trigger`) and load `js/nowo-ui-open-modal.js` (or call `openModalOnLoad()` from `assets/src/nowo-ui-open-modal-core.ts`). Hosts with their own island (e.g. Beacon `#beacon-open-modal-on-load`) switch to the kit id `nowo-ui-open-modal-on-load`. See [USAGE.md](USAGE.md#open-a-modal-on-load-deep-link).
- Hosts with a local `auto-refresh` Stimulus controller can register the kit peer instead (same identifier, values, targets and region attribute). See [STIMULUS.md](STIMULUS.md#auto-refresh-stimulus-peer-only).
- Filter bars / list tables: add `.nowo-ui-filter-bar` / `.nowo-ui-data-table` and `_filter_actions.html.twig`. See [USAGE.md](USAGE.md#filter-bar-and-data-table-opt-in).

## To 1.11.0

From **1.10.1** — new optional page loader visuals and page transitions; no breaking changes.

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console assets:install
php bin/console cache:clear
```

- **No action required.** `visual: spinner` / `orb` render exactly as before.
- Veil loaders driven by the `page-loader` peer now fade out (`.nowo-ui-page-loader.is-leaving`) instead of disappearing at once; override that rule if you relied on the instant hide.
- Hosts with their own minimal loader / View Transitions CSS can switch to `visual: …` + `_page_transition.html.twig` ([USAGE.md](USAGE.md#page-loader-and-page-transitions)).

## To 1.10.1

From **1.10.0** — `page-loader` Stimulus peer fixes only; no breaking changes.

```bash
composer update nowo-tech/ui-kit-bundle
```

- Rebuild host assets that import the peer (`page_loader_controller.ts`).
- Hosts that worked around the early dismissal (children animated with keyframes only, never transitions) can keep it or go back to transitions.

## To 1.10.0

From **1.9.2** — new optional features only; no breaking changes.

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console assets:install
php bin/console cache:clear
```

- **No action required.** `release_check` is disabled by default and registers only an inert controller.
- To show the installed version + GitHub “newer release?” dialog: `composer require symfony/http-client` (if missing), set `nowo_ui_kit.release_check` (`enabled: true`, `github_repo`, `current_version`), import `@NowoUiKitBundle/Resources/config/routes/release_check.yaml`, include `@NowoUiKitBundle/partials/_release_version.html.twig` and load `js/nowo-ui-confirm.js` + `js/nowo-ui-release.js` (or render with `stimulus: true` and register the `confirm-dialog` + `release-status` peers). See [CONFIGURATION.md](CONFIGURATION.md#release-check).
- Apps keeping copies of `confirm_submit`, `confirm_dialog`, `tabs`, `toast_stack`, `clipboard_copy`, `page_loader` (or a `release_status`) controllers can re-export the vendor peers instead ([STIMULUS.md](STIMULUS.md#stimulus-peers--import-examples)). The peers are supersets of the Beacon/podologiapriego copies: legacy `data-confirm-dialog-close`, `data-tab-id` and `data-toast-stack-target="toast"` keep working.
- Migrating an app-level release checker: map `app.version` → `current_version`, `app.release_github_repo` → `github_repo`, `app.update_check` → `enabled`. The JSON payload keys are unchanged; the route becomes `nowo_ui_kit_release_status` and translations move to the `NowoUiKitBundle` domain (`release.*`). The compare target when up to date is `default_branch` (default `main`).

## To 1.9.2

From **1.9.1** — dependency updates only.

```bash
composer update nowo-tech/ui-kit-bundle
```

No breaking changes. **No application upgrade steps.**

## To 1.9.1

From **1.9.0** — legacy panel redirect runs before the router (priority 64).

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console cache:clear
```

- Remove any host workaround that re-tagged `LegacyPanelPathRedirectSubscriber` above priority 32; the kit now listens at 64.

## To 1.9.0

From **1.8.5** — optional `panel_path_rewrites` (route path rewriting + legacy 301 redirects).

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console cache:clear
```

- **No action required.** The default (`panel_path_rewrites: {}`) registers nothing and behaves exactly like 1.8.5.
- To adopt it, add a prefix map (see [CONFIGURATION.md](CONFIGURATION.md#panel-path-rewrites)). If you already have a host-side routing loader decorator and redirect subscriber doing the same, remove them to avoid rewriting twice.

## To 1.8.5

From **1.8.4** — REQ-CS-008 Igor FrankenPHP worker audit (igor-php require-dev, igor.json, make igor).

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console cache:clear
```

- No application upgrade steps for require-dev Igor tooling (REQ-CS-008). Consumers do not pull `igor-php/igor-php` transitively.

## From 1.8.3 to 1.8.4

**Documentation / QA only** — no Twig/CSS/JS API changes.

```bash
composer update nowo-tech/ui-kit-bundle
```

Optional reading: [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md) if you run FrankenPHP worker without kernel reset.

## From 1.8.2 to 1.8.3

Review the [CHANGELOG](CHANGELOG.md) entry. PHP **8.2+** may now be required.

```bash
composer update nowo-tech/ui-kit-bundle
```

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
