# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## Table of contents

- [[Unreleased]](#unreleased)
- [[2.17.4] - 2026-10-30](#2174---2026-10-30)
- [[2.17.3] - 2026-10-29](#2173---2026-10-29)
- [[2.17.2] - 2026-10-28](#2172---2026-10-28)
- [[2.17.1] - 2026-10-28](#2171---2026-10-28)
- [[2.17.0] - 2026-10-28](#2170---2026-10-28)
- [[2.16.1] - 2026-10-28](#2161---2026-10-28)
- [[2.16.0] - 2026-10-28](#2160---2026-10-28)
- [[2.15.4] - 2026-10-27](#2154---2026-10-27)
- [[2.15.3] - 2026-10-26](#2153---2026-10-26)
- [[2.15.2] - 2026-10-26](#2152---2026-10-26)
- [[2.15.1] - 2026-10-26](#2151---2026-10-26)
- [[2.15.0] - 2026-10-26](#2150---2026-10-26)
- [[2.14.0] - 2026-10-25](#2140---2026-10-25)
- [[2.13.2] - 2026-10-25](#2132---2026-10-25)
- [[2.13.1] - 2026-10-25](#2131---2026-10-25)
- [[2.13.0] - 2026-10-24](#2130---2026-10-24)
- [[2.12.1] - 2026-10-24](#2121---2026-10-24)
- [[2.12.0] - 2026-10-24](#2120---2026-10-24)
- [[2.11.0] - 2026-10-24](#2110---2026-10-24)
- [[2.10.1] - 2026-10-23](#2101---2026-10-23)
- [[2.10.0] - 2026-10-23](#2100---2026-10-23)
- [[2.9.0] - 2026-10-23](#290---2026-10-23)
- [[2.8.0] - 2026-10-23](#280---2026-10-23)
- [[2.7.0] - 2026-10-23](#270---2026-10-23)
- [[2.6.1] - 2026-10-21](#261---2026-10-21)
- [[2.6.0] - 2026-10-21](#260---2026-10-21)
- [[2.5.3] - 2026-10-20](#253---2026-10-20)
- [[2.5.2] - 2026-10-19](#252---2026-10-19)
- [[2.5.1] - 2026-10-19](#251---2026-10-19)
- [[2.5.0] - 2026-10-19](#250---2026-10-19)
- [[2.4.0] - 2026-10-18](#240---2026-10-18)
- [[2.3.1] - 2026-10-18](#231---2026-10-18)
- [[2.3.0] - 2026-10-18](#230---2026-10-18)
- [[2.2.0] - 2026-10-18](#220---2026-10-18)
- [[2.1.0] - 2026-10-18](#210---2026-10-18)
- [[2.0.1] - 2026-10-18](#201---2026-10-18)
- [[2.0.0] - 2026-10-17](#200---2026-10-17)
- [[1.17.5] - 2026-10-17](#1175---2026-10-17)
- [[1.17.4] - 2026-10-16](#1174---2026-10-16)
- [[1.17.3] - 2026-10-16](#1173---2026-10-16)
- [[1.17.2] - 2026-10-16](#1172---2026-10-16)
- [[1.17.1] - 2026-10-16](#1171---2026-10-16)
- [[1.17.0] - 2026-10-16](#1170---2026-10-16)
- [[1.16.0] - 2026-10-13](#1160---2026-10-13)
- [[1.15.0] - 2026-10-13](#1150---2026-10-13)
- [[1.14.0] - 2026-10-13](#1140---2026-10-13)
- [[1.13.2] - 2026-10-13](#1132---2026-10-13)
- [[1.13.1] - 2026-10-13](#1131---2026-10-13)
- [[1.13.0] - 2026-10-13](#1130---2026-10-13)
- [[1.12.0] - 2026-10-12](#1120---2026-10-12)
- [[1.11.0] - 2026-10-09](#1110---2026-10-09)
- [[1.10.1] - 2026-10-09](#1101---2026-10-09)
- [[1.10.0] - 2026-10-09](#1100---2026-10-09)
- [[1.9.2] - 2026-10-09](#192---2026-10-09)
- [[1.9.1] - 2026-10-07](#191---2026-10-07)
- [[1.9.0] - 2026-10-07](#190---2026-10-07)
- [[1.8.5] - 2026-09-27](#185---2026-09-27)
- [[1.8.4] - 2026-09-25](#184---2026-09-25)
- [[1.8.3] - 2026-08-24](#183---2026-08-24)
- [[1.8.2] - 2026-08-19](#182---2026-08-19)
- [[1.8.1] - 2026-08-18](#181---2026-08-18)
- [[1.8.0] - 2026-08-15](#180---2026-08-15)
- [[1.7.0] - 2026-08-05](#170---2026-08-05)
- [[1.6.0] - 2026-08-04](#160---2026-08-04)
- [[1.5.1] - 2026-08-04](#151---2026-08-04)
- [[1.5.0] - 2026-08-04](#150---2026-08-04)
- [[1.4.0] - 2026-08-04](#140---2026-08-04)
- [[1.3.0] - 2026-08-03](#130---2026-08-03)
- [[1.2.1] - 2026-08-03](#121---2026-08-03)
- [[1.2.0] - 2026-08-03](#120---2026-08-03)
- [[1.1.0] - 2026-08-03](#110---2026-08-03)
- [[1.0.0] - 2026-08-03](#100---2026-08-03)

## [Unreleased]

## [2.17.4] - 2026-10-30

Page contract without Twig deprecations

### Fixed

- **Page contract** (`PageContractTestCase::assertExtendsScaffoldOrUnique()`): the inheritance chain no longer calls
  `Twig\Environment::mergeGlobals()` (deprecated since Twig 3.14) nor `TemplateWrapper::unwrap()` without arguments
  (deprecated since Twig 3.30). It renders the page with Twig's profiler and reads each parent from its profile, so a
  bundle can run its contract test with `failOnDeprecation="true"` without `ignoreIndirectDeprecations="true"`. Same
  verdicts as before: an error inside a block is still ignored, a dynamic `extends` that fails is still thrown. A
  regression test proves the helper triggers no deprecation.

## [2.17.3] - 2026-10-29

Burger tap target on phones

### Fixed

- **Burger** (`.nowo-ui-burger`, app shell and `_burger` partials): `flex-shrink: 0` and a 32×32px minimum, so a
  crowded phone header (a flex row with `min-width: 0`) no longer squeezes it to its 20px glyph. A regression test also
  pins the skip link to the theme's surface / text pair, which keeps it readable in dark themes.

## [2.17.2] - 2026-10-28

Dependency update

### Changed

- **Dependencies updated** within their constraints: Symfony 8.1.9 patch releases, PHPStan 2.3.3, Rector 2.8.1 (development); Vite 8.3.5 and Vitest 4.1.12 (asset build). No change in the bundle's behaviour or configuration.

## [2.17.1] - 2026-10-28

### Fixed

- **Tab icons with a prefixed name** (`tabler:bug`, `bi:gear`) render only with an icon set that has them: `ux_icon`,
  or the matching font set (`tabler-icons` / `bootstrap-icons`) without the prefix. With any other set the tab shows
  its label only, instead of a broken `bi bi-tabler:bug` class (bundles pass Tabler names to every host).

## [2.17.0] - 2026-10-28

Icons on area navigation and settings section tabs

### Added

- **`AreaNavItem::$icon`** and **`SettingsSection::$icon`** (optional, last constructor argument): the page header's
  area navigation and the settings scaffold's section tabs pass them to the tabs partial, so a bundle's own tabs
  follow the host's tab style (`data-tab-style`, 2.16) like any other tab row. Without an icon nothing changes.

## [2.16.1] - 2026-10-28

### Fixed

- **Icon-only tabs**: the tooltip of the first (and last) tab of a row starts at the tab's outer edge, so it no longer
  runs off the left (right) side of the page; the edge rules were less specific than the centring rule.

## [2.16.0] - 2026-10-28

Tab icons and the host's tab style (text, icon + text, icon only)

### Added

- **Tab icons** (`partials/_tabs.html.twig`): an item may carry `icon` (a canonical kind or an icon name such as
  `tabler:bug`, rendered by `components/_icon`). It then renders `<span class="nowo-ui-tabs__icon">` and
  `<span class="nowo-ui-tabs__label">`, and `data-rail-label` with the label. Items without an icon, and every item
  with `icon_set: none`, render exactly as before.
- **Tab style** (`data-tab-style` on any ancestor, usually `<html>`): absent or `text` shows the label only (the
  default, no visual change); `icon-text` shows icon and label; `icon` shows the icon, keeps the label as the
  accessible name (visually hidden) and visible on the current tab, and shows it as a tooltip on hover and keyboard
  focus. Under 30rem, `icon-text` rows of five or more tabs behave like `icon`, so they stay on one line.

## [2.15.4] - 2026-10-27

Bootstrap theme, form rows, file inputs, light-only palette tokens and back link fixes

### Fixed

- **back_link**: with `icon_set: none` the label is printed once. That set renders an icon as its label text, so the
  partial printed "Dashboard Dashboard"; it now prints no icon span at all.
- **Bootstrap form rows**: the `form_row` and `form_row_check` slots default to `mb-3` on Bootstrap (`bootstrap`,
  `bootstrap5`, `bootstrap4`, `tabler`), so text rows of the UiKit form theme are spaced like checkbox rows and like
  `bootstrap_5_layout`. A class the row already has (`row_attr`, FormKit's `mb-3` fallback) is not added twice, the
  form flex gap of `nowo-ui.css` already gives that rem back, and list filter grid fields drop the margin (the grid gap
  spaces them). Tailwind / Foundation / none keep no row class (the form flex gap spaces rows there).
- **File inputs**: the UiKit form theme gives `<input type="file">` the `input` slot (Bootstrap `form-control`, plus
  `input_invalid` on errors); it was skipped.
- **Bootstrap dark mode**: the theme boot, the theme toggle and OS colour-scheme changes set `data-bs-theme` together
  with `data-theme`, so Bootstrap 5.3 components follow the kit theme. It is set on every stack (no other stack reads
  it); `<html data-nowo-ui-bs-theme="off">` leaves it to the host. The toggle peer now also follows OS
  `prefers-color-scheme` changes live while no theme is stored or server-preferred.
- **`theme.tokens` without `tokens_dark`**: host / bundle `tokens` for a palette token that has a UiKit dark default
  (`text`, `surface`, `border`, `primary`, `muted`, the status colours, …: `Theme::LIGHT_ONLY_TOKENS`) are emitted
  for light mode only (`:root:where(:not([data-theme=dark]))`, same specificity as `:root`). Under
  `data-nowo-ui-host-tokens` the UiKit dark defaults have zero specificity, so a plain `:root` value also won in dark
  mode (dark text on a white surface). Dark mode now keeps the UiKit dark defaults unless `tokens_dark` sets the token;
  other tokens (radii, fonts, metrics, component tokens) still apply in both modes. See
  [UPGRADING](UPGRADING.md#unreleased).

## [2.15.3] - 2026-10-26

The date-range panel is repositioned when its size changes

### Fixed

- **date-range**: the open panel is repositioned whenever its size changes (`ResizeObserver`). Cally renders its months after the panel is first measured, so 2.15.2 placed a still-short panel below the control and the grown calendar overflowed the viewport again.

## [2.15.2] - 2026-10-26

The date-range popover always stays whole inside the viewport

### Fixed

- **Date-range panel out of the viewport** (2.15.0, `date-range` peer): when the popover fitted neither below nor
  above the control it was placed below and its bottom overflowed the viewport (e.g. a two-month calendar at
  1280×720 with the field in the 4th row of a filter grid), so the lower days could not be reached with the pointer.
  The panel is now moved up until it is whole inside the viewport (it may cover the control); its height is capped
  to the viewport minus the margins (inline `max-height`, so a taller panel scrolls inside), and the left edge is
  clamped between both margins. Placement below / above and keyboard behaviour are unchanged.

## [2.15.1] - 2026-10-26

Date-range filters submit a nameless form whose request carries only a range; dev dependency bumps (assets unchanged); twig include fixes; worktree-friendly co-author check.

### Fixed

- **Date range in a nameless GET form** (2.15.0): Symfony only submits a form without a root name (FormKit's GET
  filter forms, `getBlockPrefix() === ''`) when one of its children's names is in the request, and a
  `DateRangeType` field is submitted under the host's `from_name` / `to_name`, not its own name. A URL carrying only
  a range (`?min_receipt_issue_date=2026-01-01`) filtered the host's list while the form stayed unsubmitted and the
  field rendered empty. New `Form\DateRangeRequestHandler` decorates the FrameworkBundle request handler
  (`form.type_extension.form.request_handler`): for a nameless form whose request carries a range's from / to
  parameter and no other child, it hands the inner handler a copy of the request with an empty entry under the
  range's name, so the form is submitted as for any present field and `DateRangeSubmitExtension` maps the two days
  onto the range. Named forms, other methods and other requests are passed through unchanged.
- `partials/_listing_head.html.twig` / `_listing_cells.html.twig`: the row-detail head / cell use the `include()`
  function instead of the `include` tag (twig-cs-fixer); same output.
- `.scripts/check-no-cursor-coauthor.sh` accepts a git worktree (`.git` file).

### Changed

- Dev dependencies lucide 1.61.0, sass 1.106.1 and happy-dom 21.0.4: the shipped assets (`src/Resources/public`,
  `views/inline/nowo-ui-theme-boot.js`) rebuild byte-identical, sass prints no deprecation and the Vitest suite
  passes unchanged.

## [2.15.0] - 2026-10-26

Date-range filter field (one labelled control, lazy Cally range calendar, native fallback); multi-select keeps chips and input on one line longer.

### Added

- **Date-range filter field** (`Form\DateRangeType`, block prefix `nowo_ui_date_range`): one labelled field per from /
  to pair of days. Its two days are submitted as two plain parameters whose names the host chooses (`from_name` /
  `to_name`, default `<name>_from` / `<name>_to`, siblings of the field), so existing query names keep working; data
  `['from' => ?string, 'to' => ?string]` (`Y-m-d`). Options `placeholder`, `from_label` / `to_label`,
  `from_options` / `to_options`, `picker` (`controller`, `months`, `first_day_of_week`, `locale`, `labels`; `false`
  for two native inputs only). `Form\DateRangeSubmitExtension` maps the two parameters onto the field on submit.
- Form theme `form/nowo_ui_date_range.html.twig` (only `nowo_ui_date_range_*` blocks), prepended to
  `twig.form_themes` and used by `form/nowo_ui_layout.html.twig`: a `role="group"` named by the placeholder with two
  labelled native `<input type="date">` ("From" / "To" visible) — the whole control without JavaScript.
- Stimulus peer `date-range` (`stimulus-peers/date_range_controller.ts`): one control as tall as the other filter
  cells showing the range in the viewer's locale (`Intl.DateTimeFormat`), a clear button and a range calendar in a
  popover (Popover API, positioned inside the viewport; inline without it). Calendar: [Cally](https://wicky.nillia.ms/cally/)
  web components (optional peer dependency `cally` ^0.9, MIT, imported the first time a panel opens). The native
  inputs move into the panel (typed entry) and keep their names. Keyboard and screen reader: trigger button with
  `aria-haspopup="dialog"` / `aria-expanded`, ArrowDown opens, Escape closes and returns the focus, Cally's grid
  keyboard. Events `date-range:change`, `date-range:ready`, `date-range:unavailable`.
- `date_range.*` translations (`from`, `to`, `clear`, `previous`, `next`, `done`) in every shipped locale; theming
  tokens `--nowo-ui-date-range-z`, `--nowo-ui-date-range-shadow`, `--nowo-ui-date-range-day-size`,
  `--nowo-ui-date-range-range-bg`.
- `Listing\ListingField::$queryKeys` / `queryKeys()`: the query parameters of a filter that are not just its key (a
  date range's two days); a hidden filter drops all of them (`ListingLayout::filterRequest()`) and is reported by
  `ignoredFilters()` when any carries a value.

### Changed

- `filters-collapse` counts the fields inside one `[data-nowo-ui-filter-group]` element once (a date range's two days
  are one active filter); the server-side count of `_filters_collapse.html.twig` already counted the range's array
  value once.

### Fixed

- Multi-select: with chips, the search input's minimum width drops from 4rem to 1.5rem, so the chips and the input
  stay on one line longer.

## [2.14.0] - 2026-10-25

Multi-select filter field (Tom Select chips, progressive enhancement) for listings; the folded filter summary counts multiple choices.

### Added

- **Multi-select filter field** (upstream note U-007-2 from core-nowo-v2): a `ChoiceType` with `multiple: true`
  shown as chips with a remove button and a search inside the control, keyboard accessible, as core-nowo's
  autocomplete multi-selects (policy status, policy type). Progressive: without JavaScript the native
  `<select multiple>` stays and submits the same GET query (`status[]=…`).
  - ChoiceType option `nowo_ui_multi_select` (`Nowo\UiKitBundle\Form\MultiSelectTypeExtension`, registered when
    symfony/form is installed; EntityType / EnumType inherit it): `true` or `{controller, options, labels,
    dropdown_parent}`; writes the class `nowo-ui-multi-select`, `data-controller` and the peer's values with the new
    `multi_select.remove` / `multi_select.no_results` translations (every shipped locale). Expanded choices are
    left alone.
  - Stimulus peer `multi-select` (`stimulus-peers/multi_select_controller.ts`): Tom Select imported on demand
    (`tom-select` ^2.3 as an optional peer dependency, checked against 2.6.2), `remove_button`, the select's own
    classes kept off the wrapper, the dropdown attached to `<body>` (not clipped by a card with `overflow: hidden`;
    inline inside a `<dialog>`), destroy on disconnect keeping the chosen values; events `multi-select:ready` /
    `multi-select:unavailable`.
  - Standalone skin `css/nowo-ui-multi-select.css` (no Tom Select stylesheet needed): input height when collapsed
    (filter-bar control height), wrapping chips, kit tokens for light / dark, focus ring, invalid state, reduced
    motion; `nowo-ui.css` keeps the native fallback of a filter multi-select to about four rows.
  See [USAGE](USAGE.md#multi-select-filter-214) and [STIMULUS](STIMULUS.md#multi-select-stimulus-peer-only).

### Fixed

- `filters_collapse`: the server-side active filter count (`filter_form`) now counts a multiple choice, as the
  `filters-collapse` peer does: a multi-select once when anything is chosen, a checkbox list once per checked box
  (arrays were ignored, so a folded card read "No active filters" with chosen statuses).

## [2.13.2] - 2026-10-25

Page-edge tabs stack in eight slots (0–7). See [UPGRADING](UPGRADING.md#to-2132).

### Changed

- Page-edge tabs stack in **8 slots (0–7)** instead of 4: a listing with its customizer, tour and several manual tabs no longer needs host CSS for the fifth tab (`edge_tab` `slot`, `listing_customizer` `slot`, `tour` `tab_slot`).

## [2.13.1] - 2026-10-25

Peers type-check under strict host tsconfigs; product tour records seen on every close (driver.js 1.8–1.10). See [UPGRADING](UPGRADING.md#to-2131).

### Fixed

- Stimulus peers type-check under a stricter host `tsconfig`: `noUncheckedIndexedAccess`, `exactOptionalPropertyTypes`,
  `noImplicitOverride`, `noImplicitReturns`, `noFallthroughCasesInSwitch`, `isolatedModules` and
  `verbatimModuleSyntax` (ES2022 target). Lifecycle methods and static `targets` / `values` carry `override`;
  `ConfirmOptions` (`ask_confirm.ts`) accepts explicit `undefined` values; index reads in `listing_customizer_controller.ts`
  and `tabs_controller.ts` are typed. New `tsconfig.strict-host.json`, checked by `pnpm run typecheck` (CI), keeps the
  peers' sources on those options. Hosts no longer need to hide the peers from `tsc` behind a declaration file.
- Product tour (`product-tour` peer): closing a tour (close button, Esc, overlay, Done, `product-tour#stop`) records it
  as seen and dispatches `product-tour:ended` itself, instead of waiting for driver.js's `onDestroyed`. driver.js
  (1.8 to 1.10) never calls `onDestroyed` for a tour destroyed before its first highlight settled, so a tour closed
  during its opening animation stayed unseen (it auto-started again on every visit) and kept `data-tour-active`. The
  end runs once whichever path comes first; a disconnect still tears down without marking seen, and a tour destroyed
  because another driver.js instance started (driver.js 1.10 reason `replaced`) is cleaned up without being marked
  seen. Checked against driver.js 1.8 and 1.10 hook semantics (unit tests with both, plus a browser probe of
  1.8.0 / 1.9.0 / 1.10.2); dev dependency `driver.js` 1.10.2.

## [2.13.0] - 2026-10-24

Listing customizer (cookie preferences) and guided product tour, ported from Symfony Beacon. See [UPGRADING](UPGRADING.md#to-2130).

### Added

- Listing customizer (ported from symfony-beacon): the viewer chooses, reorders, hides and stacks the columns of a listing table and shows / hides / reorders its filters, with a reset to the defaults. PHP API under `Nowo\UiKitBundle\Listing`: `ListingDefinitionInterface` (autoconfigured tag `nowo_ui_kit.listing_definition`), `ListingField`, `ListingFieldState`, `ListingLayout` (stacked column groups, `filterRequest()` drops hidden filters and a sort on a hidden column, `ignoredFilters()`, `withoutColumns()`), `ListingLayoutMerger` (lenient merge of stored layouts, strict `normalize()` / `normalizeLayout()` of submitted ones), `ListingRegistry`, `ListingTableMode`, `ListingCustomizerView` and `ListingCustomizerViewBuilder::forRequest()` (services `nowo_ui_kit.listing.registry`, `nowo_ui_kit.listing.view_builder`). Preferences live in the browser: port `ListingPreferenceStoreInterface` with the default `CookieListingPreferenceStore` (cookie `nowo_ui_listing_<key>`, JSON, size-guarded, lenient parse) so the server renders the chosen columns on first paint; a host aliases the port to a server store and passes `save_url`. See [USAGE](USAGE.md#listing-customizer).
- Partials `listing_customizer` (page-edge tab + `dialog` side panel with Columns / Filters tabs, drag and keyboard reorder, visibility toggles, "stack under the previous column", reset; hidden-filters notice), `listing_head`, `listing_cells` (`cell_<key>` blocks, stacked cells as `[data-cell-part]` lines, row detail cell), `listing_filter_fields`; theme slots `listing_customizer`, `listing_customizer_toggle`, `listing_customizer_tabs`, `listing_customizer_tab`, `listing_customizer_panel`, `listing_customizer_list`, `listing_customizer_actions`, `listing_notice`; canonical icon kinds `sliders`, `grip`, `stack`; `listing.*` translations in every shipped locale.
- Stimulus peer `listing-customizer` (`stimulus-peers/listing_customizer_controller.ts`) and the cookie helpers `stimulus-peers/listing_cookie.ts`: Apply writes the cookie (path `/`, SameSite=Lax, one year) and reloads; with a `url` value it sends the layout to the host instead (CSRF, token refresh on 403); events `listing-customizer:saved` / `listing-customizer:save-failed`.
- Responsive table `save_cookie` option (peer value `cookie`): without `save_url`, the table mode toggle keeps its choice in the listing cookie (`ListingCustomizerView::tableOptions()` passes it with `table_mode`). Hosts passing `save_url` are unchanged.
- **Guided tour** (D-055, ported from Symfony Beacon's per-screen tours): `Nowo\UiKitBundle\Tour\Tour`,
  `TourStep` (selector or centred, `Label` or string title / description, side / align, `TourStep::anchor()` for
  `[data-tour="…"]`) and the immutable `TourBuilder` (`welcome()`, `step()`, `add()`, `build()`); the `tour` partial
  (`partials/_tour.html.twig`, `nowo_ui_partial('tour')`): a "Tour this page" page-edge tab plus the steps as a
  Stimulus value (no inline script, CSP-safe); the Stimulus peer `product-tour`
  (`stimulus-peers/product_tour_controller.ts`): driver.js imported on demand, steps without their anchor skipped,
  reduced motion honoured, closed `<details>` opened for a highlight. The "seen" state is kept in the browser
  (localStorage `nowo-ui-tour-seen`, D-056): auto-start once per tour key, replay from the tab; an optional
  `seen_url` also posts `{"key": …}` with the CSRF header for hosts that persist it server-side. `tour.*`
  translations in every locale, the `tour` icon in every icon set, `driver.js` as an optional peer dependency.
  [USAGE](USAGE.md#guided-tour) and [STIMULUS](STIMULUS.md#product-tour-stimulus-peer-only).
- **Page-edge tab** `button: true` (a `<button>` instead of the link); the 2.12.1 `attr` option also applies to the
  inline link and the button.

## [2.12.1] - 2026-10-24

Edge tab attr option (open a page in the side panel). See [UPGRADING](UPGRADING.md#to-2121).

### Added

- Edge tab `attr` option (`partials/_edge_tab.html.twig`): extra attributes on the tab link (bare `true`, omitted `false` / `null`, `on*` handlers dropped), e.g. `data-nowo-ui-dialog-open` + `data-nowo-ui-dialog-src` to open the linked page in a side panel (dialog `placement: 'end'`, 2.12) with `href` as the no-JS fallback.

## [2.12.0] - 2026-10-24

Side-panel placement for the native dialog. See [UPGRADING](UPGRADING.md#to-2120).

### Added

- Dialog `placement: 'end' | 'start'` (`partials/_dialog.html.twig`): a full-height side panel stuck to that edge that slides in (`@starting-style`, none with `prefers-reduced-motion`); `size` sets its width (sm 22 rem, md 32 rem, lg 48 rem, xl 64 rem, capped at the viewport) and the body scrolls. Combined with the opener's `data-nowo-ui-dialog-src` it is the side-panel pattern for help, manuals and customizers. Unknown placements keep the centred dialog. 

## [2.11.0] - 2026-10-24

### Added

- **Host layout bridge blocks** (re-audit OVR-09 from Symfony Beacon): `layout/_bridge.html.twig`, for `{% use %}` in a
  host layout that extends the host's own shell — `nowo_ui_bridge_title` (document title + optional
  `nowo_ui_document_title_suffix`), `nowo_ui_bridge_assets`, `nowo_ui_bridge_body` (the new `host_page` slot wrapper,
  `nowo-ui-host-page--<page type>`, `data-nowo-ui-bundle`, with navigation, page header, flashes and content),
  `nowo_ui_bridge_flashes` and `nowo_ui_bridge_end`; pages keep overriding the canonical blocks. New `page_width` slot:
  a container's width per page type through `theme.pages`. See [PAGE_CONTRACT](PAGE_CONTRACT.md#example-host-integration).
- **Density tokens** (re-audit OVR-11): button, compact button (square icon buttons), input, badge (with per-tone
  colours), table head, pagination and tab metrics as optional `--nowo-ui-*` tokens, and the `nowo-ui-tabs--pill`
  modifier. Unset, every component keeps its metrics. See [THEMING](THEMING.md#density-tokens-211).

### Changed

- `--nowo-ui-control-height` now also sets the minimum height of `.nowo-ui-btn` and `.nowo-ui-input`, and the table
  cell tokens also apply to `.nowo-ui-table`.

### Fixed

- Responsive tables in card mode (phones): the actions cell keeps the full width and its actions wrap (they collapsed
  into a narrow column drawn over the previous cell), and a labelled cell with several children no longer spills into
  the label column (the label is positioned; an inline indentation, e.g. DashboardMenu's tree depth, is overridden).

## [2.10.1] - 2026-10-23

### Fixed

- Checkbox / radio rows of the UiKit form theme take the new `form_row_check` slot instead of `form_row`: a host whose
  `form_row` classes stack a column (`flex flex-col`) centred its checkbox rows (Symfony Beacon's *Remember me*).

## [2.10.0] - 2026-10-23

### Added

- **Form rows and labels from the theme** (re-audit OVR-06 from Symfony Beacon): the UiKit form theme
  (`form/nowo_ui_layout.html.twig`) gives rows the `form_row` slot classes (also on checkbox rows; a class already in
  `row_attr` is not repeated) and labels the `form_label` slot (both empty by default), and the new
  `theme.form_errors_after_widget` option prints a field's errors after its widget (before the help on checkbox rows)
  instead of Symfony's order. Only `form_row_render` is themed, so Symfony's `aria-describedby` / `aria-invalid` stay.
  An app can style its AuthKit pages (whose default form theme is this one) without a form theme of its own.
  [PAGE_CONTRACT](PAGE_CONTRACT.md) (*Form theme*) and [THEME_SLOTS](THEME_SLOTS.md).

## [2.9.0] - 2026-10-23

### Added

- **Toasts a host can extend** (re-audit OVR-02 from Symfony Beacon): `partials/_toasts.html.twig` options
  `toast_roles` (false: no role per toast — the stack is the live region, no double announcement), `timeouts` (merged
  over `{error: 8000, warning: 8000, default: 5000}`; 0 keeps a toast until dismissed), `toast_classes` (`toast`,
  `content`, `title`, `message`, `dismiss`), `toast_type_classes` (per flash type, `default`) and
  `toast_flash_slots` (false: the `flash_<type>` slots stay on inline flashes only), and the blocks
  `toast_attributes`, `toast_text` and `toast_dismiss_attributes`. Defaults render as before.
  [USAGE](USAGE.md#toasts-in-a-host).

## [2.8.0] - 2026-10-23

### Added

- **Confirm in the kit dialog** (re-audit OVR-03 from Symfony Beacon): the `confirm-submit` peer gains a `dialog` mode
  (`data-confirm-submit-mode-value="dialog"` or `createConfirmSubmitController({ mode: 'dialog' })`) that asks in a
  native `<dialog>` instead of `window.confirm()` — focus on Cancel, Escape / backdrop cancel, `danger` value for a
  destructive Confirm — and replays the held submit with its submitter (or the click) on Confirm. New module
  `stimulus-peers/ask_confirm.ts`: `askConfirm()` (Promise), `pageConfirmLabels()` (`<html data-confirm-ok /
  data-confirm-cancel>`), `KIT_CONFIRM_CLASSES`; host classes through the factory's `classes`. `native` stays the
  default. See [STIMULUS](STIMULUS.md#confirm-submit-stimulus-peer-only).
- **The locale switcher keeps the page `#fragment`** (`nowo-ui-shell.js`, `keepLocaleHash()`): a link or a form inside
  `[data-nowo-ui-locale-switcher]` (partials/_locale_switcher.html.twig) gets the current fragment, so a language
  change reopens the same section (e.g. a manual chapter anchor).

## [2.7.0] - 2026-10-23

"Back to …" control for page headers. See [UPGRADING](UPGRADING.md#to-270).

### Added

- `View\BackLink` and `PageHeader::$back` (last constructor argument, kept by `withActions()` / `withTabs()`): a compact
  "Back to …" link with the `prev` icon above the title.
- Theme partial `back_link` (`partials/_back_link.html.twig`: `href`, `label`, `icon`, `class`, `testid`) for pages that
  do not build a `PageHeader`, and theme slot `back_link` (`.nowo-ui-back-link`, framework defaults for Bootstrap,
  Tailwind and Foundation).

## [2.6.1] - 2026-10-21

Burger keeps the menu glyph in the icons rail and on phones before morph-icon loads, compact pagination without a stray per_page, nowo-ui-confirm-dialog--plain modifier. See [UPGRADING](UPGRADING.md#to-261).

### Fixed

- **App shell burger before morph-icon loads:** the static X was chosen from `aria-expanded`, which is also `true` in the
  icons rail and on the (initially closed) phone drawer, so the menu glyph disappeared there; the layered `!important`
  could not be undone from host CSS. The burger now renders `data-nowo-ui-burger-glyph="close|open"` server side (close
  only for a fully expanded aside), the X shows only on desktop (≥ 901px) and phones start with the menu glyph.
- **`pagination_compact`:** links no longer append `per_page=<n>` when no per-page select is shown; it is kept when the
  select is shown or the request already chose a page size.
- Static burger glyphs and the burger `<morph-icon>` use `stroke-width="2"` (Tabler's), was 1.75.

### Added

- `nowo-ui-confirm-dialog--plain` modifier (pass it as `dialog_modifier`): drops the kit dialog box (background, border,
  radius, shadow, padding, size, overflow) for hosts whose `<dialog>` is a transparent frame around their own panel.

[2.6.1]: https://github.com/nowo-tech/UiKitBundle/compare/v2.6.0...v2.6.1

## [2.6.0] - 2026-10-21

Kit error pages, confirm dialog partial, app shell scaffold and compact pagination (hosts drop their copies); responsive table measures with the detail buttons shown. See [UPGRADING](UPGRADING.md#to-260).

Kit templates that replace the apps' near-identical copies: branded HTTP error pages, the confirm dialog wrapper, the
signed-in app shell and a compact pagination variant; the responsive table no longer ends one button too wide.
[UPGRADING](UPGRADING.md#to-260).

### Added

- **Error pages** (`@NowoUiKit/error/layout.html.twig` + `@NowoUiKit/error/status.html.twig`, REQ-ERROR-001 item 10):
  one template for every status code — the host keeps a one-line `templates/bundles/TwigBundle/Exception/error.html.twig`
  (`{% extends '@NowoUiKit/error/status.html.twig' %}`). Code, title, lead, hint, illustration (`<picture>` with a
  WebP `<source>` and a PNG fallback, intrinsic width / height), "Back to home", "Try again" on 408 / 5xx GET, "Go back"
  for a same-site referer (404), the 429 `Retry-After` wait and the 10-minute self-reload of a 5xx on GET (never on
  POST; no live region on the ticking counter). Self-sufficient document: skip link to `#main-content` (REQ-A11Y-002),
  pre-paint theme boot and the light / dark toggle, UiKit CSS + tokens, every `<script>` / `<style>` with the request
  `csp_nonce`, no inline handlers. Blocks `title`, `error_illustration`, `error_title`, `error_lead`, `error_hint`,
  `error_actions`, `error_extra_actions`, `error_extra`; layout blocks `nowo_ui_error_head`, `nowo_ui_error_styles`,
  `nowo_ui_error_skip_link`, `nowo_ui_error_before`, `nowo_ui_error_top`, `nowo_ui_error_content`,
  `nowo_ui_error_body_end`, `nowo_ui_error_scripts`. Twig: `nowo_ui_error_page(status_code, exception)`
  (`View\ErrorPage`, `View\ErrorIllustration`), `nowo_ui_error_text(field, code, params)` (host domain / key prefix,
  falling back to UiKit's `error.*` texts), global `nowo_ui_error_pages`.
- **`nowo_ui_kit.error_pages`** config: `layout_template` (host chrome: a template extending the kit layout),
  `translation_domain` / `translation_prefix` (`<prefix>.<code>.title|lead|hint|image_alt`), `illustrations`
  (`{404: 'illustrations/error-404'}` or `{path, width, height, webp, extension}`, plus `default`),
  `illustration_width` / `illustration_height`, `webp`, `home_path`, `retry_statuses`, `back_link_statuses`,
  `auto_retry_seconds` (600), `theme_storage`, `theme_toggle`, `html_attributes`.
- **Error texts** in `NowoUiKitBundle` (en, es, fr, de, it, nl, pt): `error.skip_to_content|home|retry|back|retry_after|auto_retry`,
  `error.generic.*` and title + lead for 400, 401, 403, 404, 405, 408, 410, 413, 422, 429, 500, 502, 503, 504.
- **`nowo-ui-error-retry.js`** runtime (`[data-nowo-ui-error-retry]`, loaded by the error layout) and the Stimulus peer
  **`error_retry_countdown`** (`error-retry-countdown`, the apps' controller), both on `nowo-ui-error-retry-core.ts`.
- **`confirm_dialog` partial** (`partials/_confirm_dialog.html.twig`): the apps' `shared/_confirm_dialog.html.twig`
  (Stimulus `confirm-dialog` wrapper, blocks `trigger` / `form`, options `only_dialog`, `wrapper`, `dialog_modifier`,
  `open_on_connect`, `expected_value`, `dialog_id`, `dialog_testid`, `aria_labelledby`, `trigger_*`, `attr`,
  `dialog_attr`). With a `dialog_id` it also opens without Stimulus through `nowo-ui-confirm.js`. Slots
  `confirm_dialog_root`, `confirm_dialog`, `confirm_dialog_trigger`; `.nowo-ui-confirm-dialog` (+ `--sm|--lg|--xl`)
  paints a native dialog box.
- **Signed-in app shell** (`@NowoUiKit/shell/_open.html.twig`, `_close.html.twig`, `_shell.html.twig`,
  `_burger.html.twig`; partials `app_shell_open`, `app_shell_close`, `app_shell`, `app_shell_burger`): the apps'
  `layouts/shell/*` — halves that keep the page `{% block body %}` uncaptured, or one template with the blocks
  `nowo_ui_shell_brand`, `nowo_ui_shell_header_actions`, `nowo_ui_shell_aside`, `nowo_ui_shell_main`,
  `nowo_ui_shell_footer`. Brand, header actions (search, toggles, user menu), aside HTML or items, the aside state
  rendered server-side from a cookie (`collapsed` / `rail`), two- or three-mode burger with next-action labels
  (`label_attr_names` for host scripts), `svg` or `morph` glyphs (`<morph-icon data-sidebar-morph>` + static menu / X).
  Slots `app_shell`, `app_header`, `app_header_start`, `app_header_end`, `app_brand`, `app_burger`, `app_burger_icon`,
  `app_burger_morph`, `app_aside`, `app_aside_inner`, `app_aside_label`, `app_aside_nav`, `app_main`, `app_backdrop`.
- **`nowo_ui_kit.shell`** config (Twig global `nowo_ui_kit_shell`): `kit_styles` (true: the kit shell classes and
  `nowo-ui-shell.js` hooks; false: host shell CSS / JS only), `sidebar_cookie`, `sidebar_hidden_value`,
  `sidebar_rail_value`, `sidebar_storage`.
- **Compact pagination**: `View\Pagination::$variant` (`full` | `compact`; `withVariant()`, `isCompact()`,
  `fromTotal()` / `fromRequest()` / `fromArray()` take it) and the `pagination_compact` partial — previous /
  "Page n of m" / next and a per-page `<select>` in a GET form that keeps the filters and goes back to page 1. The
  `pagination` partial (and the list scaffold) switch to it for a compact model or `variant: 'compact'`. Slots
  `pagination_compact`, `pagination_page`, `pagination_per_page`, `pagination_select`; texts `pagination.page`,
  `pagination.apply`.
- **`nowo-ui-autosubmit.js`** runtime (in the `scripts` partial): a control with `data-nowo-ui-autosubmit` submits its
  form on change (delegated listener, CSP-safe; a `<noscript>` button without JavaScript).
- **`skip_link` slot** (`nowo-ui-skip-link`): off-screen until focused.
- Demo: `/kit/shell` (app shell, confirm dialog, compact pagination), `/kit/errors/{code}` previews, the one-line
  `templates/bundles/TwigBundle/Exception/error.html.twig`, WebP screenshots `kit-error-404`, `kit-error-500`,
  `kit-app-shell`, `kit-confirm-dialog`.

### Fixed

- **Responsive table one button too wide** (`responsive_table` Stimulus peer): phase 2 of `fit()` measured the row
  detail column while its buttons were still `hidden` (layered `display: none !important`), then `markRows()` showed
  them, so the table ended one button wider than its scroller (19px at 390px in WebKit / Firefox). The buttons are now
  shown before the phase-2 measurement (their final layout); hosts can drop stand-in CSS such as a 2rem
  `::before` box during `data-rt-measuring`.

[2.6.0]: https://github.com/nowo-tech/UiKitBundle/compare/v2.5.3...v2.6.0

## [2.5.3] - 2026-10-20

Locale tab strip without the page tabs margin (new theme slot locale_tabs_nav), kebab button items keep the item font size and hover background, php-cs-fixer ParallelConfig(2). See [UPGRADING](UPGRADING.md#to-253).

### Fixed

- **Space under the locale tab strip**: the `locale_tabs` partial printed its tab list with the `tabs` slot, so the
  page-level tabs margin (`mb-3` by default, `mb-6` and similar in host themes; Bootstrap's `mb-3` is `!important`)
  added to `--nowo-ui-locale-tabs-gap`. The list now uses the new slot **`locale_tabs_nav`**
  (`nowo-ui-tabs nowo-ui-locale-tabs__nav`; Bootstrap `nav nav-tabs`, Tailwind `flex flex-wrap gap-2 border-b`,
  Foundation `tabs`): the `tabs` look without the margin, spaced by the gap token only.
- **Kebab `<button>` items**: a POST action rendered as `button.nowo-ui-kebab__item` lost the item font size (the
  popover rule's `font: inherit`, Tailwind preflight and the UA button font) and, in popover menus, its hover
  background. `button.nowo-ui-kebab__item` now sets `font: inherit` then `font-size: 0.875rem` in the `nowo-ui` layer
  (above Tailwind `base`), plus the hover / current background, in both menu modes.

### Changed

- `.php-cs-fixer.dist.php`: `new ParallelConfig(2)` (REQ-CS-001).

[2.5.3]: https://github.com/nowo-tech/UiKitBundle/compare/v2.5.2...v2.5.3

## [2.5.2] - 2026-10-19

Language names with polyfill-intl-icu (no ext-intl) and locale tabs legends hidden by the tabs runtime itself. See [UPGRADING](UPGRADING.md#to-252).

### Fixed

- **`nowo_ui_locale_name()` / `locale_tabs` partial with `symfony/polyfill-intl-icu`**: `LocaleNames` now checks
  `extension_loaded('intl')` instead of `class_exists(\Locale::class)` (true with the polyfill, whose
  `Locale::getDisplayName()` throws) and falls back to the upper-cased code when the display-name lookup throws.
  Hosts without ext-intl but with the polyfill no longer crash. The constructor takes an optional display-name
  resolver (tests, custom names).
- **Locale tabs legends without `nowo-ui.css`**: `nowo-ui-tabs.js` and the Stimulus `tabs` peer make the panel legends
  (`legend.nowo-ui-locale-tabs__legend`, new `[data-nowo-ui-tabs-legend]`) visually hidden themselves — class
  `visually-hidden`, `data-nowo-ui-visually-hidden` and CSSOM properties (allowed by strict CSP; no style attribute in
  the markup) — so hosts that load the tabs runtime without the UiKit CSS no longer show large "English EN★" legends.
  They stay in the accessibility tree (no `hidden` / `aria-hidden`). Non-progressive partials render the legend with
  `visually-hidden` server-side.

[2.5.2]: https://github.com/nowo-tech/UiKitBundle/compare/v2.5.1...v2.5.2

## [2.5.1] - 2026-10-19

Locale tabs partial without the twig 3.15 ?? precedence deprecation. See [UPGRADING](UPGRADING.md#to-251).

### Fixed

- `locale_tabs` partial: no twig/twig 3.15 deprecation ("`??` infix operator will change its precedence"); the first
  invalid panel is still found the same way.

[2.5.1]: https://github.com/nowo-tech/UiKitBundle/compare/v2.5.0...v2.5.1

## [2.5.0] - 2026-10-19

Locale tabs partial and accessible tabs runtime, one gap for form rows with form spacing tokens. See [UPGRADING](UPGRADING.md#to-250).

### Added

- **`locale_tabs` partial** (`partials/_locale_tabs.html.twig`): one in-page panel per locale behind a WAI-ARIA tab
  list — the shared building block for per-locale CRUD fields (FormKit 2.13 renders its `locale_tabs` option and
  `TranslatableMapType` through it). `panels: [{locale, content, label?, name?, invalid?, required?, dir?, attr?}]`,
  `id`, `label`, `default_locale` (★, listed first), `required_locales` (*), `active_locale` (default: the request
  locale), `activate_invalid`, markers, `legend`, `progressive`, `stimulus`, `attr`, `bundle`. Without JavaScript every
  panel is a visible stacked `<fieldset><legend>`; `lang` / `dir` per panel; errors as `!` plus screen-reader text in
  the tab name; no inline script / style. Translations `locale_tabs.*` in every shipped locale.
- Theme slot **`tab_panel`** (`nowo-ui-tabs__panel`); tokens `--nowo-ui-locale-tabs-gap`,
  `--nowo-ui-locale-tabs-default`.
- Twig functions **`nowo_ui_locale_name(locale, display_locale = null)`** (ext-intl display name in the request
  locale, else the upper-cased code) and **`nowo_ui_locale_dir(locale)`** (`rtl` / `ltr`); `Twig\LocaleNames`.
- **`nowo-ui-tabs.js` / Stimulus `tabs` peer**: WAI-ARIA keyboard support for `role="tab"` triggers (roving
  `tabindex`, `ArrowLeft` / `ArrowRight` mirrored in RTL, `ArrowUp` / `ArrowDown` for vertical lists, `Home` /
  `End`); an `invalid` control in a hidden panel opens it (and its parent panels) so browser validation can focus it;
  the bubbling **`nowo-ui:tabs:change`** event (`detail: {tabId, previousTabId, trigger, panel, reason}`) for editors
  that mount on a visible panel; progressive roots (`data-nowo-ui-tabs-upgrade`, `data-nowo-ui-tabs-list`,
  `data-nowo-ui-tabs-ready`); trigger class swap (`data-nowo-ui-tabs-tab-class` / `-tab-active-class`); re-sync after
  DOM morphs (Live Component re-render, Turbo morph, inserted content) that keeps the tab the user chose. The peer
  gains the `list` target and the same behaviour standalone, and delegates to the script when both are present.
- Kitchen sink demo: a locale tabs form (server error on one locale, required locales, an RTL panel).
- **Form spacing tokens**: `--nowo-ui-form-actions-gap` (last row → actions row), `--nowo-ui-form-label-gap`
  (`.form-label` / `.nowo-ui-form__label` → control), `--nowo-ui-form-column-gap` / `--nowo-ui-form-column-min` and the
  new **`form_columns`** slot (`nowo-ui-form__columns`: side-by-side fields on an auto-fit grid).

### Changed

- **Form rows use one gap**: `form.nowo-ui-form` (and forms in dialog bodies) is a flex column with
  `gap: var(--nowo-ui-form-row-gap)` instead of a `margin-top` between sibling rows, so a gap the host puts on the
  `form` slot replaces the kit spacing instead of adding to it (Beacon login: 32px between fields but 16px before the
  button). Bare buttons / links directly in the form keep their width (`align-self: flex-start`); rows of the
  Bootstrap form theme (`mb-3`) do not add to the gap; the last descendants' bottom margins that used to collapse
  into the row margin are trimmed, so the scaffolds keep the 2.4 look at default tokens. Inside `@layer nowo-ui` and in
  `nowo-ui.unlayered.css`.
- The `form_actions` slot dropped its `mt-3` (Bootstrap) / `mt-4` (Tailwind) default: the space above the actions row is
  `--nowo-ui-form-actions-gap` (default `1rem`), also for an actions row rendered after `</form>`.

### Fixed

- `nowo-ui-tabs.js` drove every trigger / panel under a root, including those of a nested tab set: each root now only
  drives its own (the nearest `[data-nowo-ui-tabs]`).

[2.5.0]: https://github.com/nowo-tech/UiKitBundle/compare/v2.4.0...v2.5.0

## [2.4.0] - 2026-10-18

Pagination::fromTotal(), popover row / overflow menus, segmented partial. See [UPGRADING](UPGRADING.md#to-240).

### Added

- **`Pagination::fromTotal()` / `fromRequest()`**: a clamped pagination from a row count and the requested page /
  per-page — page clamped to 1..totalPages (an empty list has one page), per-page restricted to `$allowedPerPage`
  (default `[10, 25, 50, 100]`; an unknown value falls back to `$defaultPerPage`, default 25, or the first allowed
  value) — plus `offset()` / `limit()` for the query (`$pagination['offset']` / `['limit']` too) and
  `allowedPerPage`. Same results as the `PagePagination::fromTotal()` helper the apps copy, so they can switch. The
  constructor and the other factories are unchanged (new optional last argument `allowedPerPage`).
- **Per-page links** in the `pagination` partial: `per_page_choices: [...]`, or `per_page_selector: true` with a
  `fromTotal()` pagination (`pagination.per_page` translated in every locale). Off by default.
- **Popover menus**: `popover: true` on the `menu` partial and `_kebab`, `menu: true` (+ `menu_label`, `popover`) on
  `row_actions`, or `theme.popover_menus: true` (default `false`): the panel is a native `[popover]` in the browser top
  layer, so table scrollers and cards never clip it. New runtime `nowo-ui-popover.js` (ported from symfony-beacon's
  `popover_menu` controller): places the panel under the toggle (flips above without room, stays in the viewport),
  `aria-expanded`, closes on scroll / resize / item choice, Escape closes the menu before a surrounding dialog.
  `Theme::popoverMenus()`; `menu` passes `confirm_external` to its actions.
- **`segmented` partial** (slots `segmented`, `segmented_option`): a two-option radio group backed by one checkbox
  (FormView or plain input; Live Component `data-model` friendly). New runtime `nowo-ui-segmented.js` (ported from
  symfony-beacon's `checkbox_segment` controller): click, arrow keys, Home / End select and sync the checkbox with a
  bubbling `change`.
- The `scripts` partial (`ThemeSlots::SCRIPTS`) also loads `popover` and `segmented` (small, delegated, idle until
  their markup is on the page). The kitchen sink demo shows a popover row menu, a popover kebab, the segmented control
  and per-page links.

### Changed

- Dev dependencies `symfony/dom-crawler` and `symfony/css-selector` (tests only).

[2.4.0]: https://github.com/nowo-tech/UiKitBundle/compare/v2.3.1...v2.4.0

## [2.3.1] - 2026-10-18

State-hide rules win over unlayered host CSS; nowo_ui_kit.breadcrumbs.enabled drops the page-header trail. See [UPGRADING](UPGRADING.md#to-231).

### Fixed

- **State-hide rules win over host CSS**: `nowo-ui.css` ships inside `@layer nowo-ui`, so a hiding rule such as the
  folded filters (`form:has(> .nowo-ui-filters-collapse.is-collapsed) > :not(.nowo-ui-filters-collapse)`) lost to any
  unlayered host rule or utility class (`.d-flex`, `.grid`…) on the hidden element, which stayed visible. Every
  state-dependent hide is now `display: none !important` — folded filters, `[hidden]` filters summary and row-detail
  button, `.nowo-ui-dialog:not([open])`, closed aside groups, rail-mode aside labels / chevrons, theme and width toggle
  icons, morph / disclosure / fold icons — and the state rules that show the paired element are `!important` too, so
  the kit's own state switches keep their order. `nowo-ui.unlayered.css` and the generated `nowo-ui.css` carry the
  same rules. `StateHideCssTest` checks each rule in both files and fails on any new state-hide rule without
  `!important`.

### Added

- `nowo_ui_kit.breadcrumbs.enabled` (default `true`): `false` drops the breadcrumb trail (BreadcrumbKit and
  `PageHeader::breadcrumbs`) from every page header — hosts no longer need an empty `breadcrumbs` partial. Exposed in
  the `nowo_ui_breadcrumbs` global. Purely additive (default unchanged), hence a patch release.

[2.3.1]: https://github.com/nowo-tech/UiKitBundle/compare/v2.3.0...v2.3.1

## [2.3.0] - 2026-10-18

Filters translate each text once, opt-in strict missing-message contract test, auth scaffold form themes/attributes/root errors/body blocks, form error slots, menu partial, public page-header include, content padding and link/code token fixes. See [UPGRADING](UPGRADING.md#to-230).

UiKit 2.3: the backlog of the phase-2 migrations (Pwa, ChatKit, SearchKit, AuthKit).
[UPGRADING](UPGRADING.md#template-contract-23).

### Added

- `|nowo_ui_translatable(params = {})` and `Twig\TranslatableText`: a `Label` (or literal) handed to a form theme that
  pipes it through `|trans` (`attr.placeholder`, choice `placeholder`) is translated exactly once in its own domain.
- `PageContractTestCase::ignoredMissingTranslations()` (accepted missing messages: `domain:id`, `id`, `/regex/`) and
  `strictMissingTranslations()` (opt-in strict rule: every missing message fails, catching double translations;
  default false = the 2.2 dotted-id rule; strict becomes the default in 3.0).
- `AuthPage::$formThemes`, `$formAttributes`, `$documentTitle`; `FormPage::$formAttributes` (extra `<form>`
  attributes, `class` appended to the `form` slot).
- Auth scaffold: root form errors in the new `form_errors` slot (skippable: `AuthPage::$rootErrors = false` or
  `nowo_ui_auth_root_errors = false` in the extending template); data blocks `nowo_ui_auth_body` and
  `nowo_ui_auth_after_form`.
- Slots `form_error`, `form_errors`, `input_invalid`; the UiKit form theme marks invalid widgets and renders field
  errors in `form_error`; PasswordToggle bridge (`input` slot on `toggle_password_widget`'s input).
- Token `--nowo-ui-auth-panel-width`.
- `nowo_ui_page_header(header, bundle, extra_actions, header_actions)` and the public include
  `page/_page_header.html.twig` (`ThemeExtension::PAGE_HEADER_TEMPLATE`): the scaffolds' page header for unique pages.
- Token `--nowo-ui-content-padding-block`; `--nowo-ui-link` declared next to the primary.
- Floating widgets: `menu` partial (native `<details>` disclosure, CSP-safe; items `Link` / `Action` / hashes, or
  free `content` in a `popover` panel), slots `menu`, `menu_toggle`, `menu_panel`, `menu_item`, `popover`, tokens
  `--nowo-ui-menu-z-index`, `--nowo-ui-menu-min-width`, `--nowo-ui-menu-max-width`; light dismiss (outside click,
  Escape) in `nowo-ui-dialog.js`.
- `nowo_ui_class(slot, bundle, defaults: false)` / `Theme::classFor(…, defaults: false)`: host-only mode (semantic +
  host / bundle classes, never the UiKit framework defaults).

### Changed

- Demo (REQ-DEMO-013): export menu on the dashboard (host `menu_toggle` class), sign-in with root form errors
  (`?failed=1`), document title and form attributes; e2e for 2.3; screenshots `page-contract-menu`,
  `page-contract-auth-errors`, re-captured `page-contract-dashboard` and `page-contract-default-list` (no band above
  the navbar).
- `PageContractTestCase` collects **all** missing messages through the `DataCollectorTranslator` and fails on any.

### Fixed

- 1.x `page_header` partial: the title uses the full `page_title` resolution (Bootstrap `h3 mb-1`), not only the host
  classes, so Bootstrap hosts do not get a full-size `<h1>`.
- No padding band above host navbars: the vertical padding moved from `<body class="nowo-ui-body">` to the content
  region.
- Inline `code` pink under Bootstrap (`--bs-code-color` follows the code text token; Bootstrap 4 `text-body`).
- `page/auth` called `form_start()` (symfony/form) even without a form: the form functions moved to
  `page/_auth_form.html.twig`, included only with `page.form`.
- Auth panel: centred `min(28rem, 100%)` in host guest layouts, full-width fields, footer link list reset hardened
  against an unlayered Bootstrap reboot.
- List filters: `FilterField::$placeholder` labels and the "Any" option (`filters.any`) were translated twice (by
  `nowo_ui_trans` / `|trans`, then by the form theme in the field's domain).

[2.3.0]: https://github.com/nowo-tech/UiKitBundle/compare/v2.2.0...v2.3.0

## [2.2.0] - 2026-10-18

Default-theme polish (confirm dialog, tables, filters grid, badges, tabs levels, one primary colour) and the page-contract backlog from the phase-1/2 migrations (scripts partial, table partial, status layout, remote dialogs, progress, form theme, breadcrumbs modes, section actions). See [UPGRADING](UPGRADING.md#to-220).

UiKit 2.2: default-theme polish and the backlog found after phase 1 of the page-contract migration (REQ-UI-003).

### Added

- **Confirm dialogs**: close ✕ in the header of 2.0 confirms (slot `confirm_close`, `dismissible`, `close_label`),
  `Confirm::$size` (`sm` | `md` | `lg`) and the `size` partial input, `Confirm::delete($message, $title)` to name the
  item in the title; slot `btn_danger_solid` (solid destructive button).
- **Dialogs**: `dialog` partial `title_html` (rich title), `title_attr` / `body_attr`; `Action::$dialogId` (opener
  button with `aria-haspopup="dialog"`; with an `href`, remote content fetched into the dialog body through
  `data-nowo-ui-dialog-src`, same origin, `nowo-ui:dialog-loaded` event, href fallback); `data-nowo-ui-dialog-replace`
  (swap dialogs, focus-return target handed over).
- **Tables**: `View\Table` + `table` partial (the list scaffold's table, reusable; blocks `nowo_ui_table_head` /
  `nowo_ui_table_row` through embed), `ListPage::$table`, `ListPage::$actionsPosition` (`start` | `end`),
  `ListPage::$captionVisible`, slot `table_caption`.
- **Tabs**: slot `tabs_secondary` and `tabs` partial `level: 'secondary'` (section tabs under an area nav).
- **Settings**: `SettingsSection::$actions` (inline after the section submit button).
- **Breadcrumbs**: `PageHeader::$breadcrumbsMode` (`auto` | `kit` | `own`).
- **Filters**: "Any" placeholder (`filters.any`, 7 locales) for optional single selects without one; text widgets get
  the `input` slot classes.
- **Badges**: slot `badge_primary`; `DetailItem::$badge` (show values such as a status printed as a badge).
- **Forms / wizards / progress**: `FormPage` body-only mode (`form: null` / `formTag: false`), `WizardPage::$guest`,
  `progress` partial + slots `progress` / `progress_meter` (`progress.label`, 7 locales).
- **Forms**: UiKit form theme `@NowoUiKit/form/nowo_ui_layout.html.twig` (form_div_layout + `input` slot on widgets,
  checkboxes / radios inside a `<label>` with the new `check` / `check_input` slots) for bundles on
  css_framework none / custom / tailwind, so bundles stop shipping their own checkbox theme.
- **Tables**: accessible name — the caption, else `aria-label` from `Table::$label` (the list scaffold passes the page
  title).
- **Actions**: `Action::$title` (translatable tooltip, kept on disabled actions through `nowo-ui-action__tip`).
- **Scripts**: `scripts` partial + `nowo_ui_script_names()` / `ThemeSlots::SCRIPTS` (hosts with their own script
  list include the partial); `AuthPage::$scripts` / `nowo_ui_scripts_enabled` for JS-free pages (no runtime script,
  no theme boot).
- **Layouts**: `layout/status.html.twig`, a self-contained status document (inline critical CSS with the CSP nonce).
- **Flashes**: `flash_messages` input of the `flashes` partial.
- **CSS**: `css/nowo-ui-tom-select.css` (Tom Select chips / focus from the primary token, optional); tokens listed in
  docs/THEMING.md "Default-theme polish tokens (2.2)".
- **Contract test**: the stub `page_header` override renders `header.tabs`; `inlineTemplates()` can replace stub
  templates.
- Docs: PAGE_CONTRACT "UiKit 2.2 additions" (scripts, JS-free pages, status layout, remote / swapped dialogs, Tom Select
  inside a modal `<dialog>`), MIGRATING_BUNDLES (name the form of a POST action), THEME_SLOTS "Badges".

### Changed

- **Default theme**: confirm dialog with a fixed width (centred with long messages), body text in the text colour,
  solid destructive confirm button (dialog and full-page confirm); table header row hierarchy (small uppercase
  semibold muted labels, stronger bottom border), numeric / end-aligned header cells follow `Column::align`, zebra rows
  painted on the cells (also under Bootstrap), styled caption, spacing around the pagination; list filters on a
  responsive grid with the actions bottom-aligned after the last field; pagination ellipsis like the pills; optional KPI
  top accent drawn as an inset shadow that follows the radius (`--nowo-ui-kpi-accent*`); form help text muted with top spacing and regular spacing
  between stacked fields (also in dialogs); plain links, `info` / `primary` badges and the second tab level derive
  from the primary token; wizard step links without underline.
- **Slot defaults** (template contract): badges are one subtle set without margin utilities (Bootstrap
  `bg-*-subtle text-*-emphasis border border-*-subtle`; `badge_info` follows the primary colour); `table` adds
  `table-striped` on Bootstrap; `filters` is a grid (`d-grid gap-3 mb-3` / `grid gap-3 mb-4`; Foundation: semantic
  only).
- The row-actions column header is visible by default (`ListPage::$actionsLabelVisible` defaults to true).
- The `section_form` partial prints `extra_actions` (documented but not rendered before).
- `_breadcrumbs`: BreadcrumbKit's output is wrapped without the `breadcrumbs` slot host classes / attributes (the
  kit's own `<nav>` carries them); an empty kit trail falls back to `PageHeader::$breadcrumbs`.
- The UiKit document layout prints its scripts through the `scripts` partial (same tags).

### Fixed

- `_action_form_view` always prints `value` on hidden fields (empty values included).
- `flashes` partial: a 1.x `flashes` page variable no longer replaces the session flashes inside page scaffolds.
- The auth scaffold prints its `<footer>` only when it has content.
- With `icon_set: none`, `icon_text` actions (toolbar, row, filter submit) printed their label twice; icon-only actions
  printed the label as the "icon": every action is text-only without an icon set.
- `.nowo-ui-cell--end` / `--center` lost to `.nowo-ui-table th, td { text-align: left }` (header and numeric cells).
- Row actions stacked in narrow cells: the actions column is `width: 1%; white-space: nowrap` and its row actions
  no longer wrap.
- With css_framework none, inline `code` had the surface colour: own tint (`--nowo-ui-code-bg` default
  `color-mix(text 7%, surface)`) plus a 1px inset border (`--nowo-ui-code-border`).

[2.2.0]: https://github.com/nowo-tech/UiKitBundle/compare/v2.1.0...v2.2.0

## [2.1.0] - 2026-10-18

Page-contract follow-ups from migrating HttpLog, Workflow, CookieConsent and RoutingKit (REQ-UI-003 phase 1). Additive; see [UPGRADING](UPGRADING.md#to-210).

### Fixed

- **`PageContractTestCase` resets collection prototypes between renders**: `vars.prototype` FormViews
  (`CollectionType` with `prototype: true`) are marked not rendered too, so the second render of a fixture keeps its
  `data-prototype` (it rendered empty or threw "already rendered" with some form themes).
- **`PageContractTestCase` no longer flags the `modal_shell` structure**: `modal`, `fade`, `modal-dialog`,
  `modal-content`, `modal-header`, `modal-title`, `btn-close`, `modal-body`, `modal-footer` are a documented structural
  exception (`FrameworkClasses::MODAL_SHELL`, like the pagination list), allowed for every `css_framework`; the partial
  keeps its 1.x output, so bundles drop them from `allowedClasses()`. Page-contract bundles should prefer the new
  framework-free `dialog` partial.
- **Header actions backed by a `FormView` keep their CSRF token** when a host `page_header` partial override renders
  `actions` itself (REQ-UI-003): the scaffolds pre-render the 1.x `toolbar`, which marked the form's fields rendered,
  so the override's `form_end` printed no `_token`. The `FormView` branch of the action partial now prints the hidden
  leaf fields of the form on every render (other children still render once through `form_end`); this also covers one
  CSRF `FormView` shared by every row.

### Added

- **docs/MIGRATING_BUNDLES.md**: FormKit placeholder / help convention vs the contract test's raw-key check
  (translate every field, `convention_requires_translation: true` or explicit `help: false`), collection pages need
  2.1, header `FormView` actions, form themes, area navigation, `dialog` vs `modal_shell`, new slot mappings.
- **Wide-form and muted-dialog slots**: `input_wide` / `input_full` (complete input class lists with the semantic
  modifiers `nowo-ui-input--wide` — max width 36rem — and `nowo-ui-input--full`; Tailwind defaults `max-w-xl` / no
  max width), `dialog_body_muted` (`nowo-ui-dialog__body--muted`) and the `nowo-ui-confirm__body--muted` modifier:
  muted text and wide inputs that do not depend on Bootstrap CSS.
- **Generic native dialog**: partial `dialog` (`partials/_dialog.html.twig`; header / title / close button, body,
  footer; `size`, `body_muted`, `dismissible`, `backdrop_close`, `attr`), slots `dialog`, `dialog_header`,
  `dialog_title`, `dialog_body`, `dialog_footer`, and `js/nowo-ui-dialog.js` (CSP-safe opener
  `data-nowo-ui-dialog-open="<id>"`, `data-nowo-ui-dialog-close`, backdrop close, focus return;
  `window.nowoUiOpenDialog` / `nowoUiCloseDialog`; loaded by the UiKit document layout). The framework-free
  alternative to `modal_shell` (kept for BC) for page-contract bundles. Demo: restock dialog on the dashboard.
- **No-JS fallback for confirm actions**: `Action::$fallbackHref` (last constructor argument, kept by
  `withConfirmScope()`, `fallback_href` in `toArray()`) prints a `<noscript>` link (class `nowo-ui-action__fallback`)
  to e.g. a `page/confirm` page next to the dialog toggle, in toolbars and row actions.
- **`theme.bundles.<Bundle>.pages.<type>`** (`classes`, `attributes`, `framework_defaults`): per-bundle variation by
  page type. Precedence: bundle page slot > bundle slot > bundle page > bundle > page-type slot > page type > host
  slot > host; attributes merge host → page type → bundle → bundle page type. Validated by the configuration and by
  `Theme`.
- **Per-page form themes**: `FormPage::$formThemes` and `SettingsSection::$formThemes` (lists of template names) are
  applied with `form_theme` inside the scaffold before the form renders (settings: before the
  `nowo_ui_settings_section` block), so bundles keep their form theme without overriding `nowo_ui_content`.
- **Area navigation** (`PageHeader::$tabs`, `View\AreaNav` / `View\AreaNavItem`, `PageHeader::withTabs()`): a tab bar
  between the sections of a bundle area, rendered by the `page_header` partial through the `tabs` partial (region
  `area-nav`, row `nowo-ui-page-header__nav`). **`Navigation\AreaNavProviderInterface`** (autoconfigured tag
  `nowo_ui_kit.area_nav_provider`), **`Navigation\AreaNavRegistry`** (service `nowo_ui_kit.area_nav_registry`) and the
  Twig function `nowo_ui_area_nav(area, context)` let other bundles (DashboardMenu) consume an area's navigation.
- **`_tabs` root `attr`**: attributes of the `<nav>` (e.g. `{'data-turbo': 'false'}` kept from a bundle's 1.x tab
  bar), merged over the `tabs` slot attributes (call site wins), also without the ThemeExtension; 1.x output unchanged.
- **Theme slots `code` and `code_block`** (REQ-UI-003): inline `<code>` (identifiers, keys) and a `<pre>` block for
  payloads / JSON / logs. Semantic classes `nowo-ui-code` / `nowo-ui-code-block` (monospace, muted surface; the block
  has a border, scrolls both ways above `--nowo-ui-code-block-max-height`, `nowo-ui-code-block--wrap` wraps lines);
  framework defaults Bootstrap `border p-3` (block), Tailwind `font-mono text-sm` (+ `p-3 border` on the block). New
  optional tokens `--nowo-ui-font-mono`, `--nowo-ui-code-bg`, `--nowo-ui-code-text`, `--nowo-ui-code-border`,
  `--nowo-ui-code-radius`, `--nowo-ui-code-block-max-height` (docs/THEME_SLOTS.md, docs/THEMING.md). Demo: webhook
  payload on the page contract show page.
- **`PageContractTestCase`**: the stub host's `page_header` override renders the header `actions` itself (after the
  scaffold's pre-rendered `toolbar`), and a seventh test per fixture, `testPageActionFormsKeepTheirHiddenFields`
  (helper `assertActionFormsKeepHiddenFields()`, `RenderedPage::$actionForms` / `hiddenFieldValues()`), fails when a
  rendered form of a `FormView`-backed `Action` of the fixture lacks (or duplicates) a hidden field of that form.
- Demo: the page contract show page has a `FormView`-backed header action (`Reopen`); the Playwright e2e checks its
  CSRF token through the host override and the default partial.

[2.1.0]: https://github.com/nowo-tech/UiKitBundle/compare/v2.0.1...v2.1.0

## [2.0.1] - 2026-10-18

Port of the 1.17.5 fix to 2.x.

### Fixed

- Responsive tables never hid a column on narrow screens (also in 2.0.0): under 30rem the cells and their descendants had `overflow-wrap: anywhere`, so the table could always shrink by breaking words at any character; the `responsive-table` peer's fit (`scrollWidth <= clientWidth`) never saw an overflow, every column stayed and words split letter by letter. The peer now measures under `data-rt-measuring` (CSS: `overflow-wrap: normal !important; word-break: normal !important` on cells and descendants, `white-space: normal` kept), decides the hidden columns, then removes the state; `overflow-wrap: anywhere` only applies after the fit as a last resort, under `[data-rt-overflow]:not([data-rt-measuring])` (set when the required columns still overflow), keeping 320px free of horizontal scroll. Source edited in `nowo-ui.unlayered.css`, `nowo-ui.css` regenerated (`pnpm run build:css`).
- Regression tests ported: Vitest with mocked layout widths (fails on 2.0.0), PHPUnit CSS assertions (measuring rule, `anywhere` only under `data-rt-overflow`, row detail column rule).
- Demo: `demo/symfony8-tailwind/config/reference.php` regenerated for the 2.0 configuration (layouts, breadcrumbs, theme).

[2.0.1]: https://github.com/nowo-tech/UiKitBundle/compare/v2.0.0...v2.0.1

## [2.0.0] - 2026-10-17

**Standard page contract (REQ-UI-003).** Canonical layouts, page scaffolds (list, show, form, settings, dashboard, confirm, wizard, auth), view models, one global theme (classes, attributes, partials, tokens, per-page-type and per-bundle exceptions) and `PageContractTestCase` for UI bundles — validated in a real host (symfony-beacon). 1.x partials and macros keep their inputs and output; `nowo-ui.css` now ships inside `@layer nowo-ui` (use `nowo-ui.unlayered.css` for the 1.x cascade). See [UPGRADING](UPGRADING.md#to-200) and [PAGE_CONTRACT.md](PAGE_CONTRACT.md).

### Added

- **Standard page contract (REQ-UI-003, phase 0 core)** — opt-in; nothing changes for hosts that do not use it. Summary of the API in [`PAGE_CONTRACT.md`](PAGE_CONTRACT.md).
  - Configuration: `nowo_ui_kit.layouts.{admin,guest}` (defaults `@NowoUiKit/layout/admin.html.twig` / `guest`), `nowo_ui_kit.breadcrumbs.{breadcrumb_kit,collection}` and the global theme `nowo_ui_kit.theme.{classes,attributes,partials,tokens,tokens_dark,bundles}` (unknown slot / partial, `on*` attributes, unsafe token values and bad bundle keys are rejected with explicit messages).
  - Theme: `Theme\ThemeSlots` (slot vocabulary + framework defaults per `css_framework`, identical to the `ui.*` macros) and `Theme\Theme` (semantic + framework + host classes, per-bundle exceptions, attributes, partial overrides, token CSS).
  - Twig: `nowo_ui_class()`, `nowo_ui_attrs()`, `nowo_ui_partial()`, `nowo_ui_theme_tokens()` (CSP nonce from the `csp_nonce` request attribute), `nowo_ui_theme_css()`, `nowo_ui_breadcrumb_kit()` / `nowo_ui_breadcrumb_kit_available()` (BreadcrumbKit bridge), filter `|nowo_ui_trans`; globals `nowo_ui_layout`, `nowo_ui_theme`, `nowo_ui_breadcrumbs`.
  - View models (`Nowo\UiKitBundle\View\`): `Label`, `PageHeader`, `Action`, `Confirm`, `Crumb`, `Link`, `Pagination` (`fromArray` / `fromPagerfanta` / `fromKnp`, duck typed), `Column`, `EmptyState`, `DetailItem`, `DetailSection`, `KpiTile`, `SettingsSection`, `WizardStep`, `ListPage`, `ShowPage`, `FormPage`, `SettingsPage`, `DashboardPage`, `ConfirmPage`, `WizardPage`, `AuthPage`.
  - Layouts `layout/{document,admin,guest}.html.twig` (canonical `nowo_ui_*` blocks, UiKit asset package only) and scaffolds `page/{list,show,form,settings,dashboard,confirm,wizard,auth}.html.twig` with the REQ-UI-003 data blocks.
  - New partials `_action` (one view-model action: link, POST form, confirm `<dialog>`, modal), `_action_post`, `_breadcrumbs`.
  - Upgraded partials accept view models and theme slot hooks: `_pagination` (`Pagination` object, page / per-page parameter names), `_page_header` (`header`), `_row_actions` (`Action` objects), `_confirm` (`confirm` + `action`), `_empty` (`empty`), `_toasts` / `_flashes` (`trans_domain`, slot attributes), `_shell` / `_burger` (`root_class`, `label_attr_names`, `attr`, theme partial for the burger). 1.x inputs render as in 1.17.4 (fixture-based BC tests).
  - CSS: the base palette drops to zero specificity when `<html data-nowo-ui-host-tokens>` is set (UiKit layout + `theme.tokens`), so host tokens win without `!important`; semantic styles for breadcrumbs, detail lists, KPI tiles, cards grid, wizard steps, auth panel, danger zone.
  - Translations `action.{confirm,back,next,submit,import}`, `confirm.delete_{title,message}`, `breadcrumbs.nav`, `table.actions`, `wizard.{steps,step_of}`, `form.danger_zone` (7 locales).
  - Contract test for UI bundles: `Nowo\UiKitBundle\Test\PageContractTestCase` (dev dependency — bundles add `phpunit/phpunit`, and `symfony/form` for form pages, to `require-dev`; listed in `suggest`). Bundles return `PageFixture`s (template + context); each page is rendered with a real Twig environment against a stub host layout and a stub theme (`StubHost`) and checked for: scaffold or unique page declared in `docs/TEMPLATES.md`, canonical blocks rendered and filled, no `<html>` / CDN assets / inline `on*=` / `confirm(` / `javascript:`, no framework utility classes outside theme slots (`FrameworkClasses` allow-lists per `css_framework` derived from `ThemeSlots`), stub theme classes / attributes / `page_header` partial reflected, no raw or missing translation keys. Hooks for template paths, catalogues, form themes, Twig extensions and allowed classes; assertion helpers reusable. Self-tested on UiKit's scaffolds for every framework, with one negative fixture per assertion.
  - Host-theme demo (`demo/symfony8`, `/page-contract`): an "Acme Shop" back office using every scaffold with a non-default host theme (host layout bridges, tokens, class map, table attribute, `page_header` partial override) and a default-theme view (`/page-contract/default/…`); the demo templates run the contract test; Playwright e2e (`make test-e2e`) and WebP screenshots of every scaffold (`make demo-screenshots`, `docs/images/demo/page-contract-*.webp`).
  - Theme `framework_defaults`: hosts can drop UiKit's framework defaults (e.g. Bootstrap `badge text-bg-success`, `btn btn-primary`, `card`) globally (`theme.framework_defaults: false`), per bundle (`theme.bundles.<B>.framework_defaults`) or per slot (`theme.classes.<slot>: {value: '…', framework_defaults: false}`, same in `theme.bundles.<B>.classes`); most specific wins, the semantic `nowo-ui-*` class always stays. `nowo_ui_class()` and the 1.x partials honour it (`Theme::macroClasses()`, `Theme::frameworkDefaults()`); the plain string / list form is unchanged.
  - `_card` reads the theme slots `card`, `card_header`, `card_body`, `card_footer` (new slot) and `cards`, and accepts `Label` titles / intros, `actions` (`View\Action` list), `id`, `attr`, `cards` (grid of cards through the `card` partial) and `bundle`; 1.x inputs render as in 1.17.4 (added to the legacy fixtures).
  - Docs: [`PAGE_CONTRACT.md`](PAGE_CONTRACT.md) (full guide), [`THEME_SLOTS.md`](THEME_SLOTS.md) (slot vocabulary with defaults per framework, partials, tokens, per-bundle exception semantics), [`MIGRATING_BUNDLES.md`](MIGRATING_BUNDLES.md) (bundle recipe).

- **Per-page-type theme variation** `theme.pages.<list|show|form|settings|dashboard|confirm|wizard|auth>.{classes,attributes,framework_defaults}` (validated; precedence bundle slot > bundle > page-type slot > page type > host slot > host). Scaffolds set `nowo_ui_page_type` and pass it to their partials; `nowo_ui_class()` / `nowo_ui_attrs()` gain an optional `page` argument (default: the context's `nowo_ui_page_type`); new `nowo_ui_html_attrs(map)` and `Theme::renderAttributeMap()`; `ThemeSlots::PAGE_TYPES`.

- **Confirm dialogs:** `_confirm` cancel / confirm buttons read the `btn_secondary` / `btn_<variant>` slots (and `framework_defaults`) instead of the bare `ui.btn()` macro (1.x output unchanged without host classes). Type-to-confirm `View\Confirm::expectedValue` (+ `expectedLabel`, `expectedPattern()`): input with `required` + `pattern` joined to the POST form, confirm button disabled until the value matches (`nowo-ui-confirm.js`: `syncExpected()`, `resetExpected()`, `syncAllExpected()`; CSP-safe), also on the full-page `ConfirmPage`. Translation `confirm.type_to_confirm` (7 locales); `_action_post` / `_action_form_view` accept `form_id`.
- **Row-action variant slots** `action_primary`, `action_secondary`, `action_danger`: host classes per resolved variant in row context (objects and 1.x hashes), e.g. a red delete on Tailwind.

- **Template contract — partial resolution, 1.x shapes, regions:** every scaffold / partial include resolves through the theme (`_page_header` → `breadcrumbs` / `action`; `_empty`, `_card`, `_row_actions` → `action`; settings tabs through the `tabs` partial, which now reads the `tabs` / `tab` / `tab_active` slots and `Label` labels); new partial names `list_filters`, `detail_list`, `section_form`, `wizard_form`. Scaffolds pass the 1.x shapes next to the view models (`page_header`: `title` / `intro` / pre-rendered `toolbar`; `pagination`: `Pagination::toArray()` + `pagination_model`; `row_actions`: 1.x hashes + `view`, row confirm dialogs rendered once after the table); `Pagination` and `Action` implement read-only `ArrayAccess` with the 1.x keys. `data-nowo-ui-region` on the standard regions and `attributes` on every page model (scaffold root). Legacy BC fixture for `_tabs`.

- **List filters:** data block `nowo_ui_list_filters`; `View\FilterField` (placeholder, width, widget / row classes and attributes) via `ListPage::filterFields`; `filtersSubmit` (label / icon / variant of the apply button, icon through the icon partial), `filtersClear` and `filterActions` as `Action`s; slot `filters_field`.
- **New slots** `table_head_row`, `table_head_cell`, `table_actions_head` (+ `ListPage::actionsLabel` / `actionsLabelVisible`), `kpi_label`, `kpi_value`, `kpi_hint` (+ `KpiTile::linkTile`), `form_panel` (form scaffold wrapper), `danger_zone_title`, `danger_zone_intro` (+ `FormPage::dangerZoneTitle` / `dangerZoneIntro`).
- **Responsive list tables (CSS only):** `ListPage::responsive` (`cards` default, `scroll`) — the table wrap is a size container, `Column::priority` 2 / 3 hide below 48rem / 60rem, below 40rem rows become labelled cards (`td[data-label]`).
- **Form secondary actions in the page header:** `FormPage::secondaryActionsInHeader` (default `true`; `false` keeps them below the form).

- **Action display modes everywhere** (validation round 3): `Action::display` (`icon` | `text` | `icon_text`, `Action::DISPLAYS`) is honoured in toolbar / header / card / filter context too (icon-only: visually-hidden label + `aria-label` / `title`, kind icon fallback), the `action` partial takes a `display` context option, `ListPage::filtersDisplay` sets it for the apply / clear / extra filter buttons, and the new slot `btn_icon` (`nowo-ui-btn--icon`) marks icon-only toolbar buttons.
- **List primary actions in the filter row:** `ListPage::headerActionsInFilters` (null = the new theme option `theme.pages.list.header_actions_in_filters`, default `false`) renders the page-header actions at the end of the filter row (`nowo-ui-filters__header-actions`, region `header-actions`) instead of the page header; `Theme::listHeaderActionsInFilters()`, `PageHeader::withActions()`, `list_filters` partial input `header_actions`, scaffold macro `page_header(…, header_actions)` (the page header then receives a `header` without actions).
- **Internal slots** (validation round 3): `card_title`, `card_intro` (`_card`, show / settings section cards); `detail_item`, `detail_term`, `detail_value` (+ `nowo-ui-detail-list--stacked` modifier, label over value); `confirm_panel`, `confirm_header`, `confirm_title`, `confirm_body`, `confirm_message`, `confirm_expect_label`, `confirm_footer` (dialog and full-page confirm); `auth_footer`, `auth_links`; `kpi_link` (whole-tile KPI link only).
- **Actions column, neutral variant, card mode** (validation round 3): `ListPage::actionsAlign` (`start` | `center` | `end`, default `end`) + slot `table_actions_cell` replace the hard-coded `nowo-ui-cell--end` (the action cluster follows the alignment); variant `neutral` (`Action::resolvedVariant()` default for kinds other than delete / create / save) + slot `action_neutral`, so an explicit `secondary` is distinguishable; card-mode CSS keeps inline cell content (badges) at its own width and `nowo-ui-table-wrap--flush` (via the `table_wrap` slot) drops the per-card border inside a bordered panel.
- **Demo (round 3):** the host-theme list puts its primary actions in the filter row with an icon-only export and clear button, show pages use stacked detail lists, the KPI link tile and the confirm title use the new slots; e2e covers per-row confirm dialogs, filters kept by pagination links, the actions column alignment and card-mode badges; list / show / dashboard / confirm / default-list screenshots regenerated.
- **Token precedence** `nowo_ui_tokens(nonce)`: theme tokens (host defaults) then the appearance SPI style (user choice); the document layout prints it after `nowo-ui.css`.
- **Confirm and show hooks** (final Beacon validation): slot `confirm_expect` on the type-to-confirm wrapper (`nowo-ui-confirm__expect`, dialog and page); slot `detail_list_meta` on the show scaffold's meta list (`nowo-ui-detail-list--meta`, added to `detail_list`); a 1.x `body` passed to `_confirm` is wrapped in a `<div>` with the `confirm_message` slot when the theme sets classes / attributes for it (1.x markup unchanged with an empty theme); `Confirm::messageInHeader` (null = the new `theme.confirm_message_in_header`, default `false`; `Theme::confirmMessageInHeader()`) renders the message under the title inside the header (dialog and page); `confirm_message` / `confirm_expect` slot attributes are now rendered.
- **Minor slots for kit class maps** (final Beacon validation): `btn_link` (toolbar `Action` variant `link`, defaults = `ui.btn('link')`), `muted` (defaults = `ui.muted()`; on the `—` placeholder of empty detail-list values), `modal_panel` / `modal_header` / `modal_body` / `modal_footer` (+ the `modal` slot, host classes and attributes in `_modal_shell`, which also accepts `bundle`; 1.x markup unchanged with an empty theme, legacy fixture added), `breadcrumb_link` / `breadcrumb_separator` (plain trail; the separator is now an `aria-hidden` element, the CSS `::before` stays for overrides without it), `table_cell_muted` (default list cells without a value print a muted `—`), `row_action_disabled` (disabled row actions; objects and 1.x hashes), `sortable_header` / `sort_link` (list sortable headers).
- **Demo (final validation):** the host theme hides closed dialogs through the `confirm` slot (`.acme-dialog:not([open]) { display: none }`) while the full-page confirm uses `confirm_page`, styles the show meta list (`detail_list_meta`) and puts a Tailwind child variant in `sort_link`; e2e covers the confirm page staying visible, the dialogs opening, the escaped arbitrary variant and the header actions right after the filter actions. Screenshots refreshed (list, show, settings, confirm).

### Changed

- **Template contract — theme dark tokens** are emitted on `[data-theme="dark"]` (was `:root[data-theme="dark"]`): same specificity as the appearance SPI, so the appearance printed after them wins.
- **Template contract — CSS cascade layer:** `nowo-ui.css` ships inside `@layer nowo-ui` with the order statement `@layer theme, base, nowo-ui, components, utilities;` so Tailwind v4 utilities placed in theme slots beat the kit while the kit stays above Tailwind's preflight (dialogs). `nowo-ui.unlayered.css` keeps the 1.x unlayered stylesheet for hosts that relied on the kit beating unlayered framework CSS. `nowo-ui.css` is generated from it (`pnpm run build:css`, checked by `LayeredCssTest`).
- **Template contract — theme class lists accept any printable character** (final Beacon validation): `"`, `'`, `<`, `>` (and `&`) are no longer rejected in `theme.classes`, `theme.pages.<type>.classes` and `theme.bundles.<B>.classes`, so Tailwind arbitrary and child variants (`[&>li]:mt-1`, `content-['·']`, `data-[state=open]:…`) can live in the theme. Class lists are always printed HTML-escaped inside their quoted `class` attribute (`ClassEscapingTest` renders every scaffold and partial with a break-out payload in every slot, on the host, per page type and per bundle: no attribute / element injection, no double escaping). Control characters (other than whitespace) and invalid UTF-8 are still refused (`class lists must be valid UTF-8 without control characters.`).
- **Template contract — full-page confirm root slot** (final Beacon validation): `page/confirm.html.twig` printed the dialog root slot `confirm` (classes + attributes, and UiKit's own `.nowo-ui-confirm` `<dialog>` box rules) on its `<section>`, so host dialog CSS such as `.confirm-dialog:not([open]) { display: none }` hid the page. The page now prints the new slot **`confirm_page`** (`nowo-ui-confirm-page`) next to `card`; `confirm` stays on the `<dialog>` only.
- **Template contract — list header actions in the filter row** sit right after the filter actions (same `filters_actions` group): the group no longer grows (`flex: 1 1 auto` dropped) and `nowo-ui-filters__header-actions` no longer pushes itself to the row end (`margin-inline-start: auto` dropped). PAGE_CONTRACT documents the filter row layout and `flex-none` on `filters_actions` for themes whose fields grow.

### Fixed

- **Confirm dialog ids of row actions** (REQ-UI-003 validation round 3): `Action::confirmId()` hashed only kind / href / route / params, so two row actions with the same target but a different `Confirm` (or the same static action on every row) printed duplicate dialog ids and every toggle opened the first dialog. The derived id now covers the whole `Confirm` content, the CSRF token and a scope (`Action::$confirmScope`, `withConfirmScope()`, `confirmKey()`); `ListPage::rowActions()` scopes actions by row key and the list scaffold renders one dialog per id (`ListPage::confirmDialogs()`; an explicit `Confirm::$id` shared by different content throws).
- **List pagination keeps filters in 1.x overrides** (validation round 3): the list scaffold passed only the `pagination` array, so an untouched 1.x `_pagination` override (which reads the top-level `route`, `route_params`, `query`) lost the filters and sort. It now also passes `route`, `route_params` and `query` (current request query without the page parameters, merged with `Pagination::query`) plus `page_param` / `per_page_param`; the default partial accepts `page_param` / `per_page_param` as variables and, through the scaffold, now keeps the request's filters across pages too.
- Show scaffold: the meta detail list (`nowo-ui-detail-list--meta`) gets a bottom margin too, so content of `nowo_ui_show_after` no longer touches it.
- Badge contrast on Bootstrap 5 / Tabler: the `ui.badge()` macro and the `badge*` slot defaults used `bg-success` / `bg-danger` (background with `!important`) without a text utility, so the semantic `nowo-ui-badge--*` text colour (dark green / red) landed on the solid Bootstrap fill. They now use `text-bg-{success,warning,danger,info,light}` (Bootstrap 5.2+; text and background set together); on 5.0 / 5.1 the semantic palette paints the badge. `nowo-ui.css` also clears the tinted border on solid badges and gives 1.x `bg-*` badges without a text utility Bootstrap's badge text colour.

[2.0.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.17.4...v2.0.0

## [1.17.5] - 2026-10-17

Released from the `1.x` branch (`main` is 2.x).

### Fixed

- Responsive tables never hid a column on narrow screens (1.17.2–1.17.4): under 30rem the cells and their descendants had `overflow-wrap: anywhere`, so the table could always shrink by breaking words at any character; the peer's fit (`scrollWidth <= clientWidth`) never saw an overflow, every column stayed and words split letter by letter (e.g. at 390px). Root-cause fix:
  - the `responsive-table` peer measures under a `data-rt-measuring` state (CSS: `overflow-wrap: normal !important; word-break: normal !important` on cells and descendants, `white-space: normal` kept), so a cell's min-content is its longest word; it decides the hidden columns, then removes the state;
  - `overflow-wrap: anywhere` is no longer tied to the viewport width: it only applies AFTER the fit, as a last resort, when the required columns still overflow — the peer sets `data-rt-overflow` on the root and the visible cells may then break words (long URLs / ids), keeping 320px free of horizontal scroll with the required columns and the row detail button.
- Regression tests: Vitest with mocked layout widths where the table "fits" by breaking words outside the measuring state (fails on 1.17.4); CSS assertions that `anywhere` only exists under `[data-rt-overflow]:not([data-rt-measuring])` and that the row detail column shows under `[data-rt-has-hidden]`.

[1.17.5]: https://github.com/nowo-tech/UiKitBundle/compare/v1.17.4...v1.17.5

## [1.17.4] - 2026-10-16

Supersedes 1.17.3: same runtime code and CSS, release gate hardened so the tagged commit is exactly what was checked.

### Changed

- `make release-check` is check-only: it starts and ends with the new `check-clean-tree` target (fails on any `git status --porcelain` output), runs `cs-check` instead of `cs-fix` + `cs-check`, and `validate-phpdoc` no longer writes files.
- `.scripts/complete-public-phpdoc.php` (`make validate-phpdoc`) is a read-only gate: it fails when a documented public method returning `array` / `iterable` lacks `@return`, and no longer adds redundant `@return` tags that php-cs-fixer removes (the two tools used to fight and leave the tree dirty).
- Tests: php-cs-fixer quote style in `HostChromePartialsTest` (no behaviour change).

[1.17.4]: https://github.com/nowo-tech/UiKitBundle/compare/v1.17.3...v1.17.4

## [1.17.3] - 2026-10-16

### Fixed

- Responsive table mode (regression in 1.17.2): `overflow-wrap: anywhere` on cells and descendants at every width broke words mid-word ("Symf ony") instead of hiding columns — mid-word breaks lower the min-content width, so the table always "fit" and the row detail button never appeared (e.g. at 600px). Above 30rem cells now use `overflow-wrap: break-word` (no effect on min-content: the two-phase column fit hides columns before any word splits); `overflow-wrap: anywhere` (cells + descendants) only applies under 30rem, as in 1.17.1. `white-space: normal !important` on descendants (the 1.17.2 nowrap fix) is kept.

[1.17.3]: https://github.com/nowo-tech/UiKitBundle/compare/v1.17.2...v1.17.3

## [1.17.2] - 2026-10-16

### Fixed

- `.nowo-ui-confirm` dialogs (confirm, row detail) opened at the top-left under a `<dialog>` margin reset such as Tailwind's preflight: they now set `margin: auto` and `max-height: calc(100dvh - 2rem)` (next to the existing `max-width`), so they centre whatever the host reset.
- Responsive table mode: descendants of cells with `white-space: nowrap` (Tailwind `.whitespace-nowrap`, Bootstrap `.text-nowrap`) kept the table wider than its scroller (horizontal scroll on phones). In `[data-table-mode='responsive']` cells and their descendants now wrap (`white-space: normal !important`, `overflow-wrap: anywhere`) — part of the "no horizontal scroll at 320px and above" contract.

[1.17.2]: https://github.com/nowo-tech/UiKitBundle/compare/v1.17.1...v1.17.2

## [1.17.1] - 2026-10-16

### Added

- `partials/_responsive_table.html.twig`: `attr` — extra attributes on the root element (hash, attribute names escaped); `labels.save_error` (default: the existing `table_mode.save_error` translation) rendered as `messages.saveError`.
- `responsive-table` peer: `messages.saveError`; the `responsive-table:save-failed` event detail now carries `message` (the save error text, or empty) next to `mode` / `previous`, so hosts show their toast from a listener. `saveFailed(mode, previous)` stays protected and overridable.

### Changed

- `NowoUiKitExtension` accepts an optional `\Closure(): bool` (whether `symfony/http-client` is installed) so the "release check needs symfony/http-client" error path is tested; PHP line coverage back to 100 %. No behaviour change.

[1.17.1]: https://github.com/nowo-tech/UiKitBundle/compare/v1.17.0...v1.17.1

## [1.17.0] - 2026-10-16

### Added

- **Responsive table** (opt-in; Symfony Beacon spec 148, kit spec 005): `partials/_responsive_table.html.twig` (embed, block `table`), `_row_detail_head.html.twig`, `_row_detail_cell.html.twig` and the Stimulus peer `responsive-table` — Scroll ↔ Responsive toggle (corner tab or bar, static icons before JS), Responsive mode hides the last non-required columns until the table fits (two-phase, on resize; `data-required="1"` never hides), no horizontal scroll at 320px and above, row detail `<dialog>` (confirm chrome) that moves the cell contents in and back (forms and listeners intact), row title from the first two columns, tap-on-row on coarse pointers, default responsive on phones. Persistence is host-owned (`save_url`, `save_token`, `save_token_url`, `save_method`; JSON `{"mode"}` + `X-CSRF-TOKEN`, one token refresh on 403); a failed save reverts and dispatches `responsive-table:save-failed`.
- **Foldable filter card**: `partials/_filters_collapse.html.twig` + peer `filters-collapse` — corner tab under the fields, "N active filters" line (FormView count or `active_count`), state in a cookie read server-side (no flash), values kept while folded. `partials/_filters.html.twig`: `collapsible` / `collapse` options.
- **Card corner tab** `.nowo-ui-corner-tab` (`--under`, `.nowo-ui-corner-host`, `partials/_corner_tab.html.twig`) and tokens `--nowo-ui-corner-tab-{width,height,icon,accent}`.
- **Disclosure chevron** `partials/_disclosure_icon.html.twig` + peer `disclosure-icon` (follows `aria-expanded` or `<details open>`).
- **Page-edge tab** `partials/_edge_tab.html.twig` (slots, inline variant) and the Twig function `nowo_ui_route_link()` fed by the new `nowo_ui_kit.route_links` config (`base_url`, `default_testid`, `routes`; longest prefix wins).
- **Thin scrollbars**: `.nowo-ui-thin-scroll`, `html[data-nowo-ui-thin-scrollbars]` (page + kit scrollers), tokens `--nowo-ui-scrollbar-{size,thumb,thumb-hover}`; SCSS mixin `assets/styles/_thin-scrollbars.scss` for host selector lists.
- **Morphicons module** `assets/morphicons/index.ts` (optional peer dependencies `morphicons` ^1.7 and `lucide` ^1.0): `prepareMorphHost`, `asMorphIcon`, `syncMorphIcon` and sync helpers for theme, content width, sidebar, password, listing customizer, table mode, filters collapse, disclosure, search (magnifier ↔ X ↔ loader) and support (lifebuoy ↔ X); honours `data-motion="reduce"`. Icon state bridge `stimulus-peers/icon_state.ts` + `installMorphiconPeers()`.
- Canonical icon kinds `filter_off`, `scroll_horizontal`, `rows`, `chevron_up`, `chevron_down`, `chevron_right`, `book` in every icon set; recommended icon-only Filter / Clear pair (`tabler:filter` / `tabler:filter-off`, or `filter` / `filter_off`) documented.
- Translations `table_mode.*`, `filters_collapse.*`, `edge_tab.manual` (7 locales). Kitchen sink: *Host chrome* section; demos enable the thin scrollbars.
- Vitest suites for the new peers, the bridge, the morphicons module and the SCSS mixin (gated); PHPUnit for the partials, icons, CSS hooks, translations, configuration and Twig function.

[1.17.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.16.0...v1.17.0

## [1.16.0] - 2026-10-13

### Added

- **Appearance SPI** (opt-in `nowo_ui_kit.appearance`; Symfony Beacon spec 138 FR-005, kit spec 004): `Appearance` value object (brand name / eyebrow, light and dark `AppearancePalette`, theme ids, corner style, border strength, fixed footer; `with*()`, `toArray()` / `fromArray()`), `AppearanceProviderInterface` (host storage; default `ConfigAppearanceProvider` from `appearance.defaults`), `AppearancePresetRegistry` (config `presets`, tagged `AppearancePresetProviderInterface` services, built-ins `ocean`, `slate`, `sandstone`, `midnight`, `obsidian`, `aurora`, `ember`; `apply()`, `matchLightId()` / `matchDarkId()`), `CornerStyle` / `BorderStrength` enums (radius / border maps).
- `AppearanceCssEmitter`: `:root` + light + dark blocks of `--nowo-ui-*` tokens (radius, card radius, border width, primary / deep / hover / contrast, danger, warning, paper, text, surface, border, accent-soft, surface-muted, paper-accent, paper-ink, muted, brand-mark, shadow-rgb), colour sanitising, configurable `selectors`, `token_aliases` (extra property names with the same literal value) and `kit_tokens: false` (aliases only) — enough to reproduce a host emitter's legacy variable names exactly.
- Twig functions `nowo_ui_appearance()`, `nowo_ui_appearance_css()`, `nowo_ui_appearance_style(nonce)` (CSP nonce from the `csp_nonce` request attribute by default), `nowo_ui_appearance_presets(mode)`.
- Optional forms `AppearanceType` / `AppearancePaletteType` when `symfony/form` is installed (colour validation with `symfony/validator`); translations (7 locales). `symfony/form` and `symfony/validator` are only suggested (dev requirements for the test suite).
- Docs: THEMING.md — Appearance SPI (token table, storage / presets, legacy aliases); CONFIGURATION.md — Appearance.

[1.16.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.15.0...v1.16.0

## [1.15.0] - 2026-10-13

### Added

- **Icon filter actions** (`partials/_filter_actions.html.twig`): `submit_icon` / `clear_icon` (rendered through `components/_icon.html.twig` and `icon_set`) and `icon_only` (visually-hidden label + `title` / `aria-label`, square `.nowo-ui-filter-bar__action--icon-only`). Defaults unchanged.
- **Pre-paint theme boot**: `partials/_theme_boot.html.twig` (inline script with the CSP nonce, or `inline: false` → `js/nowo-ui-theme-boot.js`). Reads `data-nowo-ui-pref-{theme,density,motion,font-scale,contrast,sidebar}` (alias `data-user-*`) on `<html>`, falls back to the theme cookie / localStorage / OS and sets `data-theme`, `data-ui-density`, `data-motion`, `data-font-scale`, `data-contrast` before first paint; `window.nowoUiPrefs`, `window.nowoUiThemeBoot()`.
- Theme storage keys shared by the boot and the toggle: `data-nowo-ui-theme-storage` is also read from `<html>`, new optional `data-nowo-ui-theme-cookie` (`assets/src/nowo-ui-theme-core.ts`).
- **Sidebar rail** (opt-in `data-nowo-ui-sidebar-rail` on the shell, `_shell.html.twig` `sidebar_rail` / `sidebar_mode`): expanded / icons-only rail / hidden, burger ping-pong through the rail, phones keep the drawer, localStorage + optional cookie sync with configurable keys (`data-nowo-ui-sidebar-storage`, `-cookie`, `-hidden-value`, `-default`, `-rail-labels`), rail tooltips and initials, `nowo-ui:sidebar-mode` event, `window.nowoUiSetSidebarMode()`. CSS: `.nowo-ui-shell.is-aside-rail` (desktop only), `--nowo-ui-aside-rail-width`. Side-effect-free helpers for host shells: `assets/src/nowo-ui-sidebar-core.ts`. `_burger.html.twig` `rail: true` prints the next-action labels (`nav.sidebar_expand`, `nav.sidebar_rail`, `nav.sidebar_hide`; 7 locales).
- **Stimulus peers** `temporary-reveal` (masked secret, timed reveal, clear-on-hide purge), `collapse-panel` (persistent panels; storage key and server default as values) and `datatable` (responsive DataTables with server-side paging / sorting; `datatables.net-dt` and `datatables.net-responsive-dt` are optional peer dependencies). Ported from Symfony Beacon with neutral copy.
- Vitest suites for the new modules and peers (coverage-gated); PHPUnit for the new partial options.

### Changed

- Dev: Rector `RemoveConcatAutocastRector` applied to `Configuration::findInvalidRewrite()` (drops a redundant `(string)` cast; behaviour-neutral).

### Fixed

- `_burger.html.twig`: `expanded: false` (e.g. `_shell.html.twig` with `collapsed: true`) rendered `aria-expanded="true"` because of `|default(true)`.

[1.15.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.14.0...v1.15.0

## [1.14.0] - 2026-10-13

### Changed

- **Symfony floor raised to `^7.4 || ^8.0`** for every `symfony/*` requirement, matching the CI matrix (7.4, 8.0, 8.1). README badge and INSTALLATION requirements updated (PHP minimum documented as 8.2, as in `composer.json`).
- The extension no longer prepends `framework.translator` (`paths` and `fallbacks: [en]`): the bundle catalogue is auto-discovered, and the host's translator enablement and fallback order are left untouched. The bundle still boots with the translator disabled.
- Dev: vitest and `@vitest/coverage-v8` upgraded to ^4.1.11 (stimulus peer tests stub `window.confirm`, no longer provided by happy-dom under vitest 4).

### Fixed

- Release check: a failed GitHub fetch threw inside the cache callback, so failures were never cached and every status request triggered outbound GitHub calls. Failures are now negatively cached for 5 minutes (`ReleaseUpdateChecker::ERROR_CACHE_TTL`, capped by `cache_ttl`).

### Security

- Documented that `GET /_nowo-ui/release/status` is anonymous by default and discloses the installed version, and how to protect it (route prefix + `access_control`).

[1.14.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.13.2...v1.14.0

## [1.13.2] - 2026-10-13

### Security

- Row actions: the `confirm_message` `onsubmit` handler is now HTML-attribute escaped. Messages containing double quotes previously broke the attribute (the destructive POST was sent without confirmation) and allowed attribute injection / XSS.
- `panel_path_rewrites`: a root target (`'/admin': '/'`) no longer produces protocol-relative `Location: //host` redirects; redirect targets always start with a single `/`. Config validation now rejects prefixes starting with `//` or containing backslashes.

[1.13.2]: https://github.com/nowo-tech/UiKitBundle/compare/v1.13.1...v1.13.2

## [1.13.1] - 2026-10-13

### Changed

- Repository: `.twig-cs-fixer.cache` is now git-ignored.
- Assets: the six `src/Resources/public/js/nowo-ui-*.js` builds were re-minified by Vite 8.3.4 (equivalent output; no behaviour change).

### Dependencies

- `vite` 8.3.2 → 8.3.4 (frontend tooling; #21).
- `happy-dom` 20.14.5 → 20.14.6 (frontend tooling; #22).

[1.13.1]: https://github.com/nowo-tech/UiKitBundle/compare/v1.13.0...v1.13.1

## [1.13.0] - 2026-10-13

### Added

- **Complete `--nowo-ui-*` theming tokens** (Symfony Beacon spec 134 FR-002): every colour, radius, font, focus ring, overlay and shadow used by `nowo-ui.css`, the `ui.*` macros and the partials reads a documented token. New base tokens with light + dark defaults: `--nowo-ui-warning-{bg,border,text}`, `--nowo-ui-info-{bg,border,text}` (were fixed hex). New optional component tokens, resolved at the use site through `var()` fallbacks to the base tokens (so they also work on a scope such as `.kit-admin` and follow dark mode): typography (`--nowo-ui-font-family`, `--nowo-ui-font-display`, `--nowo-ui-heading-weight`), focus (`--nowo-ui-focus-ring-color`, `--nowo-ui-focus-ring`), links (`--nowo-ui-link`, `--nowo-ui-link-hover`, `--nowo-ui-primary-hover`, `--nowo-ui-accent`), radii / overlays / shadows (`--nowo-ui-badge-radius`, `--nowo-ui-modal-radius`, `--nowo-ui-card-radius`, `--nowo-ui-overlay`, `--nowo-ui-shadow-{popover,toast,dialog}`), buttons (`--nowo-ui-btn-*`: base, hover, primary, danger, outline), inputs (`--nowo-ui-input-{bg,text,border,radius,placeholder,focus-border}`), tables (`--nowo-ui-table-{border,head-bg,head-text,stripe-bg,hover-bg}`), tabs (`--nowo-ui-tabs-{border,gap}`, `--nowo-ui-tab-*` incl. active / hover), pagination (`--nowo-ui-pagination-*` incl. hover / active), lists (`--nowo-ui-list-*`), cards, aside current link (`--nowo-ui-nav-active-{bg,text}`), progress.
- **Theming guide** [docs/THEMING.md](THEMING.md): every token with its default, framework notes, a generic host theme and the Symfony Beacon (moss / sand) mapping.
- `.nowo-ui-btn--outline` variant (`ui.btn('outline')`; Bootstrap: `btn-outline-primary`), `.nowo-ui-link` (`ui.link()`), `.nowo-ui-check` checkbox / radio label (`ui.check()`), `accent-color` on checkboxes / radios inside kit surfaces.
- `:focus-visible` rings (token-driven) on buttons, links, tabs, pagination links, toggles, menus, modal close, aside links; focused `.nowo-ui-input` gets the primary border + ring.
- Hover states for the kit-painted stacks (`custom` / `none` / `tailwind`): buttons (replacing the removed Tailwind `hover:bg-*` utilities, same default colours), pagination links, inactive tabs, plain `.nowo-ui-table` rows (matching the `table-hover` the Bootstrap branch already emits).
- **Opt-in Bootstrap token bridge** `.nowo-ui-bs-tokens`: on an ancestor, paints Bootstrap 5.3 buttons, forms, tables, list groups, pagination, nav tabs, cards, modals, progress, alerts, `text-muted` and kit badges from the same tokens. No effect without the class.
- Twig tests guarding the contract: no palette / radius / divide utilities in any macro output (tailwind, custom, none, foundation), no hex in partials / components / macros or outside the two token blocks of `nowo-ui.css`, base tokens present in light + dark, component tokens never declared, bridge rules scoped, every token documented.

### Changed

- **Tailwind macro output no longer carries palette, radius or divide utilities** (`bg-blue-600`, `hover:bg-blue-500`, `text-white`, `border-red-500`, `text-red-600`, `hover:bg-red-50`, `text-blue-600`, `hover:underline`, `border-slate-*`, `text-slate-*`, `hover:bg-slate-50`, `bg-emerald/amber/sky/red-*`, `divide-y`, `divide-slate-200`, `rounded-md`, `rounded-full`, `bg-white`, `bg-slate-200`, `border-blue-600`, `text-blue-700`, `border-t-blue-600`, `animate-spin`). The semantic `nowo-ui-*` classes (unchanged) paint the same default colours from tokens; layout / spacing / type-scale utilities stay. `ui.muted()` in Tailwind no longer appends `text-slate-500` (`.nowo-ui-muted` has the same `#64748b`).
- `.nowo-ui-input` (non-Bootstrap) now paints `--nowo-ui-input-bg` / `--nowo-ui-input-text` (white / text in light — same as the browser default; dark mode now gets the dark surface instead of a white field).
- Warning / info flashes and badges have dark-mode defaults (they kept the light hex before).
- `_pagination.html.twig`'s `<ul class="pagination">` lays out as a wrapping row with a 0.25rem gap on every stack (zero-specificity rule; it stacked vertically on `custom` / `tailwind` unless the host fixed it).
- Modal hex fallbacks (`var(--nowo-ui-surface, #ffffff)` …) removed — base tokens are always declared.

[1.13.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.12.0...v1.13.0

## [1.12.0] - 2026-10-12

### Added

- **Open modal on load (deep link):** `partials/_open_modal_on_load.html.twig` emits a CSP-safe JSON island `script#nowo-ui-open-modal-on-load` (`modal`, optional `trigger`, `param` default `new` — rendered only when `?new=1`; hex-escaped JSON; request `csp_nonce`). IIFE `js/nowo-ui-open-modal.js` (`window.nowoUiOpenModalOnLoad`) clicks the trigger / opens the modal directly, retries while kit scripts boot, then drops the parameter from the URL. Side-effect-free helpers for bundled hosts in `assets/src/nowo-ui-open-modal-core.ts` (`openModalOnLoad`, `readOpenModalConfig`, `tryOpenModal`, `isModalOpen`, `clearQueryParam`, `OPEN_MODAL_ISLAND_ID`). Ported from Symfony Beacon.
- **Stimulus peer `auto-refresh`** (`stimulus-peers/auto_refresh_controller.ts`): periodic same-origin re-render of `[data-auto-refresh-region]` elements by id; `interval` / `url` values, `toggle` / `status` targets, pauses on hidden tabs, `auto-refresh:refreshed` event. Ported from Symfony Beacon.
- **Opt-in filter bar / data table CSS:** `.nowo-ui-filter-bar` (explicit 38px control height, compact 34px via `--compact` or `[data-ui-density="compact"]`, actions on the field baseline, `__actions--pager` keeps pager + Filter + Clear on one row, `__help` visually hidden) and `table.nowo-ui-data-table` (`--compact`, `--nowo-ui-table-*` tokens). Partial `partials/_filter_actions.html.twig` (pre-rendered `pager`, `clear_href`, label / class overrides). Existing selectors unchanged.
- Vitest coverage for the new modules and the `auto-refresh` peer (gated at 100%); Twig tests for both partials.

[1.12.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.11.0...v1.12.0

## [1.11.0] - 2026-10-09

### Added

- **Page loader minimal visuals:** `_page_loader.html.twig` `visual: bar | bar_loop | bar_spinner | corner_spinner | dots | glow | corner_mark` (`mark_src` / `mark_alt`). No veil, no pointer capture, delayed fade-in (fast loads show nothing), keyframe-only children. CSS variables `--nowo-ui-page-loader-color`, `--nowo-ui-page-loader-corner-bottom`, `--nowo-ui-page-loader-corner-left`.
- `_page_loader.html.twig` `stimulus: true`: attaches the `page-loader` peer with timing that matches the visual (`min_visible_ms` / `leave_ms` overrides).
- **Page transitions:** `partials/_page_transition.html.twig` — cross-document View Transitions opt-in in one nonce-aware `<style>` (`style: fade_slide | fade | slide | zoom | blur | wipe | none`, optional `persist` selector → name map). No JS; off under `prefers-reduced-motion: reduce`.
- CSS: `.nowo-ui-page-loader.is-leaving` fade for the veil when driven by the Stimulus peer.

[1.11.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.10.1...v1.11.0

## [1.10.1] - 2026-10-09

### Fixed

- `page-loader` peer: the leave only ends on the overlay's own `opacity` `transitionend`. The event bubbles, so a child transition (logo, label…) finishing first used to hide the veil mid-fade (visible cut).
- `page-loader` peer: the leave fallback timer no longer hides an overlay that was shown again before it fired (e.g. quick back-to-back navigations).

### Added

- Vitest coverage for the `page-loader` peer (`stimulus-peers/page_loader_controller.test.ts`).

[1.10.1]: https://github.com/nowo-tech/UiKitBundle/compare/v1.10.0...v1.10.1

## [1.10.0] - 2026-10-09

### Added

- **Release check (optional, off by default):** `nowo_ui_kit.release_check` (`enabled`, `github_repo`, `current_version`, `cache_ttl`, `default_branch`, `user_agent`). `ReleaseUpdateChecker` compares the installed clean semver with GitHub `releases/latest` (cached, soft-fail, counts newer non-prerelease releases); `ReleaseStatusController` + opt-in route file `@NowoUiKitBundle/Resources/config/routes/release_check.yaml` (`GET /_nowo-ui/release/status`, 404 while disabled); partial `partials/_release_version.html.twig`; IIFE `js/nowo-ui-release.js` (`window.nowoUiLoadReleaseStatus`); Stimulus peer `release_status_controller.ts`; `release.*` translations (7 locales); `.nowo-ui-release*` CSS. Ported from the podologiapriego-web footer.
- Stimulus peers are now type-checked (`tsconfig.json`) and tested with Vitest (`stimulus-peers/peers.test.ts`; dev dependency `@hotwired/stimulus`).
- Dev dependency `symfony/http-client` (tests / PHPStan for the release check).

### Changed

- `confirm-submit` peer: documented single-button usage (`click->confirm-submit#confirm`) next to form `submit`, plus `blocked` / empty-message behaviour (no API change).

### Fixed

- `tabs` peer: `activeTabValue` was declared `readonly`, so `open()` failed type-checking when the peer was compiled by a strict host TypeScript build.

## [1.9.2] - 2026-10-09

### Dependencies

- Bundle lockfile: Symfony 8.1.8, `twig/twig` 3.30.0; dev `phpstan/phpstan` 2.3.1, `phpstan/phpstan-symfony` 2.1.0, `nowo-tech/phpstan-frankenphp` 1.2.3, `rector/rector` 2.7.0.
- Demos (`demo/symfony8`, `demo/symfony8-tailwind`): Symfony 8.1.8, `twig/twig` 3.30.0; regenerated `config/reference.php`.

[1.9.2]: https://github.com/nowo-tech/UiKitBundle/compare/v1.9.1...v1.9.2

## [1.9.1] - 2026-10-07

### Fixed

- `LegacyPanelPathRedirectSubscriber` now listens at `kernel.request` priority **64** (above `RouterListener` at 32) so legacy `/admin/…` paths 301 instead of 404 when the rewritten route is not matched first.

[1.9.1]: https://github.com/nowo-tech/UiKitBundle/compare/v1.9.0...v1.9.1

## [1.9.0] - 2026-10-07

### Added

- **`nowo_ui_kit.panel_path_rewrites`** (default `{}`): map of legacy path prefix → new prefix.
- **`PanelPathRewriter`** and **`PanelPathRewritingLoader`**: when the map is non-empty, `routing.loader` is decorated and route paths are rewritten (longest prefix first, segment-boundary matching).
- **`LegacyPanelPathRedirectSubscriber`**: permanent redirect (301 for GET/HEAD, 308 otherwise) from legacy prefixes to the new ones, preserving sub-path and query string.
- Parameter `%nowo_ui_kit.panel_path_rewrites%`; config validation rejects non-absolute prefixes and targets nested under their source (redirect loops).

### Changed

- None for existing setups: an empty map registers no routing services (BC).

[1.9.0]: https://github.com/nowo-tech/UiKitBundle/releases/tag/v1.9.0

## [1.8.5] - 2026-09-27

### Added

- **REQ-CS-008:** `igor-php/igor-php` (require-dev only), root `igor.json`, Composer/`Makefile` `igor` target, and `release-check` wiring for FrankenPHP worker-state audit.

### Changed

- **Worker safety (Igor):** justified `// @igor-ignore` annotations and/or `ResetInterface` / request-scoped fixes so `make igor` passes on package `src/`.

[1.8.5]: https://github.com/nowo-tech/UiKitBundle/releases/tag/v1.8.5

## [1.8.4] - 2026-09-25

### Added

- **Docs:** [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md) — full audit confirming **100%** compatibility with FrankenPHP worker mode when the Symfony kernel is **not** reset between requests (no runtime PHP services / no per-request mutable state).
- Spec baseline: `FR-FRANKENPHP-001` / `FR-FRANKENPHP-002` + user story for worker / `reset_kernel=false`.

### Changed

- README, [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md), and [SECURITY.md](SECURITY.md) link the worker audit; README states worker friendliness including no kernel reset.
- Rector: `NowoUiKitExtension` translations path uses `__DIR__.'/../Resources/translations'`.

## [1.8.3] - 2026-08-24

### Changed

- Raise minimum PHP to **8.2** and sync README badge (REQ-SF-001).
- **QA:** add `phpstan-frankenphp` extension (REQ-CS-005).
- **Assets:** English JSDoc on TypeScript sources (REQ-ASSETS-002).
- **Docs:** PHP-FIG PSR evaluation (REQ-CS-007).

### Notes

- **No API or configuration changes** for integrators unless noted above.

[1.8.3]: https://github.com/nowo-tech/UiKitBundle/releases/tag/v1.8.3

## [1.8.2] - 2026-08-19

### Security

- **CI:** run `composer audit --locked` after dependency install (REQ-SEC / P3).

### Changed

- **Maintainer:** PR template checklist; keep `tests/Integration` for REQ-TEST-002.

## [1.8.1] - 2026-08-18

### Changed

- **Demos:** pin `nowo-tech/hot-reload-bundle` to `^1.4` with FrankenPHP Mercure/`hot_reload` (`dev`/`test` only).

[1.8.1]: https://github.com/nowo-tech/UiKitBundle/releases/tag/v1.8.1

## [1.8.0] - 2026-08-15

### Added

- **Clipboard IIFE:** `nowo-ui-clipboard.js` (`data-nowo-ui-clipboard*`) → `nowoUiCopyText`.
- **In-page tabs IIFE:** `nowo-ui-tabs.js` (`data-nowo-ui-tabs*`) → `nowoUiActivateTab`. Twig `_tabs` remains link-based navigation.
- **Stimulus peer sources** under `src/Resources/assets/stimulus-peers/`: `clipboard_copy`, `confirm_dialog`, `confirm_submit`, `page_loader`, `toast_stack`, `tabs` (optional; hosts import via AssetMapper/Vite). Docs: [STIMULUS.md](STIMULUS.md).

### Changed

- Vite/`pnpm run build` emits **nine** IIFEs (adds `clipboard`, `tabs`).
- [STIMULUS.md](STIMULUS.md): peer import examples + Beacon attribute migration notes.

[1.8.0]: https://github.com/nowo-tech/UiKitBundle/releases/tag/v1.8.0

## [1.7.0] - 2026-08-05

### Changed

- **`_pagination`:** previous/next use `«` / `»` with translated `aria-label`s; wrap links in `ul.pagination` / `li.page-item` / `a.page-link` for Bootstrap-friendly markup. Optional `trans_domain` / `nav_trans_domain`; `route` defaults to the current request route when omitted.

[1.7.0]: https://github.com/nowo-tech/UiKitBundle/releases/tag/v1.7.0

## [1.6.0] - 2026-08-04

### Added
- **`row_actions_display`** (`icon` \| `text` \| `icon_text`): kit config + Twig global `nowo_ui_kit_row_actions_display`; `_row_actions` emits glyph-only, label-only, or both (REQ-UI-001). Per-include `display` override. CSS modifiers `--icon` / `--text` / `--icon-text` + `nowo-ui-action__label`. Kitchen sink shows all three modes.
- **Canonical row-action API:** `_row_actions` supports `tag` (`a`/`button`/`form`/`auto`), `method` POST + CSRF, `confirm_id` / `modal_id`, `confirm_message` (transitional), `variant` / `icon` / `label` overrides. Expanded kinds: `create`, `save`, `cancel`, `restore`, `share`, `config`, `export`, `filter`. Docs: [ROW_ACTIONS.md](ROW_ACTIONS.md).

### Changed
- `ui.action(kind)` default variant: `edit` / most kinds → **secondary**; `delete` → danger; `create`/`save` → primary (was: edit primary). Third macro arg `variant` overrides.

[1.6.0]: https://github.com/nowo-tech/UiKitBundle/releases/tag/v1.6.0

## [1.5.1] - 2026-08-04

### Fixed
- **Vite / TypeScript CI:** orb remount test used removed state `"idle"`; use a valid `OrbState` (`listening`).

[1.5.1]: https://github.com/nowo-tech/UiKitBundle/releases/tag/v1.5.1

## [1.5.0] - 2026-08-04

### Added
- **REQ-TWIG-004:** require `twig/extra-bundle` + `twig/string-extra`; `make check-twig-extra` in `release-check`; demos register `TwigExtraBundle`.
- **Twig-CS-Fixer:** `vincentlanglet/twig-cs-fixer`, `.twig-cs-fixer.php`, `composer twig:lint` / `twig:fix`.

[1.5.0]: https://github.com/nowo-tech/UiKitBundle/releases/tag/v1.5.0

## [1.4.0] - 2026-08-04

### Added

- **Phase A — feedback:** `_toasts`, `_confirm`, `_page_loader` partials; CSS; TypeScript IIFEs `nowo-ui-toast.js`, `nowo-ui-confirm.js`, `nowo-ui-page-loader.js`.
- **Phase B — layout:** `_card`, `_filters`, `_brand`; nested aside `children` + shell nav-group toggle; card/filters macros.
- **Phase C — host chrome:** `_theme_toggle`, `_locale_switcher`, `_kebab`; `progress` / `progress_bar` / `spinner` macros; `nowo-ui-theme.js`; dark `[data-theme="dark"]` token defaults.
- **Thinking Orbs:** `_thinking_orb.html.twig`, `nowo-ui-orb.js` (local MIT canvas engine from [thinking-orbs](https://orbs.jakubantalik.com)); page loader `visual: orb|spinner` (default `spinner`); [THIRD_PARTY.md](THIRD_PARTY.md).
- **Shell UX:** smoother aside transitions; `_width_toggle.html.twig` (full ↔ content main width) + `nowoUiSetMainWidth` / `nowoUiToggleMainWidth`.
- Badge variants (`success`, `warning`, `danger`, `info`, `neutral`) with BC for `ui.badge('tailwind')`.
- Confirm Twig helpers: `confirm_toggle_attrs`, `confirm_target_attr`, `confirm_dismiss_attrs`.
- Docs: [ADOPTION.md](ADOPTION.md), [STIMULUS.md](STIMULUS.md); kitchen sink covers new surfaces (orbs section).
- i18n keys for toast/loader/filters/theme/locale/layout/orb/cancel/more (`en`, `es`, `it`, `fr`, `pt`, `de`, `nl`).

### Changed

- [ROADMAP.md](ROADMAP.md): phases A–D marked complete on the kit side (consumer migration tracked in ADOPTION).
- Vite/`pnpm run build` emits seven IIFE entrypoints (adds `orb`).
- `_icon.html.twig`: `ux_icon` call deferred to `_icon_ux.html.twig` so hosts without `symfony/ux-icons` can compile templates.

### Fixed

- Demo `config/routes.yaml`: controller path `../src/Controller/` (was `../../…`).
- Demo `web_profiler.yaml`: removed deprecated `framework.profiler.collect_serializer_data` (Symfony 8.1).
- TypeScript coverage gate: Vitest include limited to top-level IIFE sources; `ts-coverage-percent.sh` enforces branches ≥85% (aligned with Vitest / COVERAGE.md).

## [1.3.0] - 2026-08-03

### Added

- **FrankenPHP demos**: `demo/symfony8` (port **8092**, Bootstrap) and `demo/symfony8-tailwind` (port **8093**) with `FRANKENPHP_MODE` (default `worker`), DNS, path repo sync, WebProfiler / Debug / Twig Inspector.
- Root Docker (`Dockerfile`, `docker-compose.yml`) and full Makefile (`ensure-up`, `release-check`, `coverage-check`, `setup-hooks`, `validate-translations`, …).
- Flex recipe `.symfony/recipe/nowo-tech/ui-kit-bundle/1.0/`.
- GitHub automation: `release.yml`, `sync-releases.yml`, Dependabot, PR lint, stale, CodeRabbit, demo-smoke, Scrutinizer, Copilot instructions, CODEOWNERS.
- Spec Kit baseline (`specs/001-baseline/`) + `.specify/` + Cursor rules/skills (REQ-SPECKIT / REQ-IDE).
- Docs: INSTALLATION, CONTRIBUTING, SECURITY, COVERAGE, DEMO-FRANKENPHP, GITHUB_CI, RECIPE, SPEC-KIT, SPEC-DRIVEN-DEVELOPMENT, ENGRAM.
- GIT-001 hooks (`.githooks/commit-msg`) and coverage scripts (PHP ≥99%, TS summary).

### Changed

- README follows canonical section order (REQ-DOCS-019) with FrankenPHP banner.
- CI: PHP × Symfony matrix (7.4 / 8.0 / 8.1), git-hygiene, coverage gate, assets/Vitest coverage job.

## [1.2.1] - 2026-08-03

### Fixed

- CI: `pnpm/action-setup@v4` no longer sets `version: 9` alongside `package.json` `packageManager` (`pnpm@9.15.0`), which caused `ERR_PNPM_BAD_PM_VERSION` / multiple-version install failures on GitHub Actions.

## [1.2.0] - 2026-08-03

### Changed

- Frontend assets: **TypeScript + Vite (pnpm)** — sources under `src/Resources/assets/src/`; built IIFEs in `src/Resources/public/js/` (`nowo-ui-modal.js`, `nowo-ui-shell.js`). Twig `asset(..., 'nowo_ui_kit')` paths unchanged.

### Added

- Vitest coverage for modal/shell helpers; CI job for `pnpm` typecheck / test / build.
- [docs/ROADMAP.md](ROADMAP.md): gap analysis vs Nowo Twig bundles and symfony-beacon admin; phased plan (toasts, confirm, cards, filters, nested nav, theme/locale helpers, consolidation).

## [1.1.0] - 2026-08-03

### Added

- **App chrome**: left aside, burger toggle, avatar, user dropdown (with logout), and footer.
- Partials: `_aside`, `_burger`, `_avatar`, `_user_menu`, `_footer`, composed `_shell`.
- Macros: `shell`, `header`, `aside`, `avatar`, `user_menu`, `burger`, `footer`.
- Asset `js/nowo-ui-shell.js` (desktop collapse + mobile drawer).
- Kitchen sink demo renders inside the shell chrome.

## [1.0.0] - 2026-08-03

### Added

- Initial public release of **UiKit Bundle** (`nowo-tech/ui-kit-bundle`).
- **Twig macros** (`@NowoUiKitBundle/macros/ui.html.twig`): buttons, toolbar, page header, search/input, list, table wrap/table, row actions, badge, muted/empty, flash, modal data attributes, pagination wrap, tabs — multi-framework class helpers (`bootstrap5`, `bootstrap4`, `tabler`, `tailwind`, `foundation`, `custom`, `none`) with optional per-call `framework` override.
- **Partials**: pagination, empty state, flashes, row actions, page header, tabs, modal shell; **icon** component by `icon_set`.
- **Assets** (named package `nowo_ui_kit`): `css/nowo-ui.css` (semantic `nowo-ui-*` + CSS variables) and `js/nowo-ui-modal.js` (custom/none/tailwind stacks).
- **Configuration** (`nowo_ui_kit`): `css_framework`, `icon_set`; Twig globals `nowo_ui_kit_css_framework` / `nowo_ui_kit_icon_set`.
- **i18n** domain `NowoUiKitBundle` with key parity for `en`, `es`, `it`, `fr`, `pt`, `de`, `nl`.
- **Twig namespace** `NowoUiKitBundle` with application override path support (REQ-TWIG-001/002).
- Kitchen sink demo template and minimal Symfony 8 demo under `demo/symfony8`.

[Unreleased]: https://github.com/nowo-tech/UiKitBundle/compare/v2.15.4...HEAD
[1.10.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.8.4...v1.10.0
[1.8.4]: https://github.com/nowo-tech/UiKitBundle/compare/v1.8.3...v1.8.4
[1.4.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.2.1...v1.3.0
[1.2.1]: https://github.com/nowo-tech/UiKitBundle/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/nowo-tech/UiKitBundle/releases/tag/v1.0.0
