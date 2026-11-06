# Code inventory — 100% traceability

**Last audited**: 2026-11-01 (2.21.0)  
**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/ui-kit-bundle`

This file proves that **every production source artifact** under `src/` is referenced by the baseline specification,
one row per file. Paths are relative to `src/`. Requirement IDs are defined in [`spec.md`](spec.md) (section
"Requirements"); feature specs `specs/002+` refine some of them.

**Scope**: every file tracked by Git under `src/` (`git ls-files src`), i.e. PHP, TypeScript, Twig, YAML, CSS, SCSS,
the built JavaScript and the Stimulus peers README. **Excluded**: the co-located Vitest files `*.test.ts` under
`Resources/assets/` (42 files — test artifacts covered by FR-TEST-001, not shipped runtime units; same rule as
TwigInspectorBundle). `tests/`, `demo/` apps and caches are outside `src/` and out of scope. Built files that live
under `src/` (the Vite IIFEs, the layered stylesheet and the inline theme-boot copy) are committed (Packagist installs
no Node toolchain); they are listed in their own table as **outputs** of named sources (`FR-BUILD-*`) and are never
edited by hand. `find src -type f` finds no file beyond `git ls-files src` (no untracked build output).

## Bundle and dependency injection

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `NowoUiKitBundle.php` | Bundle entry: registers the extension and the Twig paths compiler pass | FR-BUNDLE-001 |
| `DependencyInjection/Configuration.php` | `nowo_ui_kit` config tree: framework / icon set / row actions enums, panel path rewrites, release check, route links, font preload, appearance, layouts, breadcrumbs, theme, error pages, shell | FR-CFG-001, FR-FONTPRELOAD-001, FR-APPEARANCE-001 |
| `DependencyInjection/NowoUiKitExtension.php` | Loads services, Twig globals, asset package, prepends form themes; registers optional services only when their dependency is installed (form, http-client) or the feature is enabled | FR-CFG-002, FR-FORM-001, FR-FORM-002, FR-RELEASE-001, FR-FRANKENPHP-001 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Registers the `@NowoUiKitBundle` namespace; app overrides win (REQ-TWIG-001/002) | FR-TWIG-001 |

## Configuration enums

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Enum/CssFramework.php` | `css_framework` values (bootstrap, bootstrap5, tabler, tailwind, foundation, custom, none, …) | FR-ENUM-001 |
| `Enum/IconSet.php` | `icon_set` values (bootstrap-icons, tabler-icons, ux_icon, …) | FR-ENUM-001, FR-ICON-001 |
| `Enum/RowActionsDisplay.php` | `row_actions_display`: icon, text or icon + text | FR-ENUM-001 |

## Routing and legacy panel paths

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Routing/PanelPathRewriter.php` | Rewrites route path prefixes from `panel_path_rewrites` (segment boundaries, longest prefix wins) | FR-ROUTING-001 |
| `Routing/PanelPathRewritingLoader.php` | Decorates `routing.loader` with the rewriter; registered only with a non-empty map | FR-ROUTING-001 |
| `EventSubscriber/LegacyPanelPathRedirectSubscriber.php` | Permanent redirect from legacy prefixes: 301 for GET/HEAD, 308 otherwise | FR-ROUTING-002 |

## Release check (optional)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Release/ReleaseUpdateChecker.php` | Compares the installed version with GitHub `releases/latest`; cached, soft-fails | FR-RELEASE-001 |
| `Release/ReleaseUpdateInfo.php` | Cached result DTO of the release comparison | FR-RELEASE-001 |
| `Release/ReleaseStatusController.php` | `GET /_nowo-ui/release/status` JSON, `private, no-store`, 404 while disabled | FR-RELEASE-002 |

## Appearance SPI (opt-in)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Appearance/Appearance.php` | Immutable site look and feel (brand, light / dark palettes, preset ids, corners, borders, footer) | FR-APPEARANCE-001 |
| `Appearance/AppearancePalette.php` | The seven colours of one theme mode | FR-APPEARANCE-001 |
| `Appearance/CornerStyle.php` | Corner style enum: card and control radii | FR-APPEARANCE-001 |
| `Appearance/BorderStrength.php` | Border strength enum: ink mix light / dark and border width | FR-APPEARANCE-001 |
| `Appearance/AppearanceProviderInterface.php` | Host storage SPI (`appearance.provider`) | FR-APPEARANCE-002 |
| `Appearance/ConfigAppearanceProvider.php` | Default provider serving `appearance.defaults` (no storage) | FR-APPEARANCE-002 |
| `Appearance/AppearancePreset.php` | Named, mode-tagged palette pair | FR-APPEARANCE-003 |
| `Appearance/AppearancePresetProviderInterface.php` | Tagged host preset provider (`nowo_ui_kit.appearance_preset_provider`) | FR-APPEARANCE-003 |
| `Appearance/AppearancePresetRegistry.php` | Host presets, tagged providers then enabled built-ins; mode-tagged `apply()` | FR-APPEARANCE-003 |
| `Appearance/BuiltInAppearancePresets.php` | Generic built-in presets (ocean, slate, sandstone, midnight, obsidian, aurora, ember) | FR-APPEARANCE-003 |
| `Appearance/AppearanceCssEmitter.php` | Emits sanitised `--nowo-ui-*` tokens (+ `token_aliases`) for root / light / dark selectors | FR-APPEARANCE-004, FR-THEME-001 |

## Forms

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Form/ActionCsrfType.php` | 2.18: nameless CSRF-only form of a POST `Action` with a `csrfToken` (one hidden `csrfField`) | FR-FORM-001 |
| `Form/HiddenFieldsType.php` | 2.19: hidden-only form (nested names kept) for row actions, logout, locale switcher | FR-FORM-002 |
| `Form/SearchFilterType.php` | 2.19: nameless GET search of the `filters` partial (`q_name`, id `nowo-ui-filter-q`) | FR-FORM-002 |
| `Form/PerPageType.php` | 2.19: nameless GET per-page form of the compact pagination (other query parameters hidden) | FR-FORM-002, FR-PAGINATION-001 |
| `Form/UiFormViews.php` | 2.19: builds the partial form views on a standalone factory (core types only, own theme) | FR-FORM-002 |
| `Form/AppearanceType.php` | Optional appearance admin form (`with_brand` / `with_colors` / `with_footer`) | FR-APPEARANCE-006 |
| `Form/AppearancePaletteType.php` | The seven colours of one mode as a sub-form | FR-APPEARANCE-006 |
| `Form/DateRangeType.php` | 2.15: date-range filter field (two native dates, host-chosen `from_name` / `to_name`) | FR-DATERANGE-001 |
| `Form/DateRangeRequestHandler.php` | 2.15.1: request handler decorator — a URL carrying only a range submits the nameless filter form | FR-DATERANGE-001 |
| `Form/DateRangeSubmitExtension.php` | 2.15: moves the flat sibling from / to parameters onto the date-range child before submit | FR-DATERANGE-001 |
| `Form/MultiSelectTypeExtension.php` | 2.14: ChoiceType option `nowo_ui_multi_select` adds the `multi-select` peer markup | FR-MULTISELECT-001 |
| `Form/VisibleWhen.php` | 2.23: `visible-when` peer contract — row attributes (`data-nowo-ui-visible-when-field` / `-in`), `fromOption()` for a `visible_when` option, container attributes | FR-VISIBLEWHEN-001 |

## Listing (customizer, preferences, sorting)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Listing/ListingDefinitionInterface.php` | 2.13: customizable columns and filters of one listing (tag `nowo_ui_kit.listing_definition`) | FR-LISTING-001 |
| `Listing/ListingField.php` | Column / filter declaration (required, default visibility, priority, classes, `queryKeys`, 2.20 `sortKey` / `sortFirst`) | FR-LISTING-001, FR-SORT-002 |
| `Listing/ListingRegistry.php` | Listing definitions by key | FR-LISTING-001 |
| `Listing/ListingFieldState.php` | Per-viewer state of one field (visible, merged under previous column) | FR-LISTING-002 |
| `Listing/ListingLayout.php` | Effective columns and filters for one viewer; `filterRequest()` drops hidden filters | FR-LISTING-002 |
| `Listing/ListingLayoutMerger.php` | Lenient merge of stored layouts, strict validation of submitted ones | FR-LISTING-002 |
| `Listing/ListingPreferenceStoreInterface.php` | Preference store port (stateless, request-only) | FR-LISTING-003 |
| `Listing/CookieListingPreferenceStore.php` | Default store: one `nowo_ui_listing_<key>` JSON cookie per listing | FR-LISTING-003 |
| `Listing/ListingCustomizerView.php` | What a listing page renders: layout, table mode, peer state, hidden filters | FR-LISTING-004 |
| `Listing/ListingCustomizerViewBuilder.php` | Builds the view and the filtered request for a listing key | FR-LISTING-004 |
| `Listing/ListingTableMode.php` | Table mode enum: scroll or responsive | FR-RESPTABLE-001 |
| `Listing/SortRequest.php` | 2.20: validated `sort` / `dir` against a whitelist, link queries, `sortRows()` | FR-SORT-001 |

## Navigation

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Navigation/AreaNavProviderInterface.php` | Tagged area navigation provider (`nowo_ui_kit.area_nav_provider`, priority) | FR-AREANAV-001 |
| `Navigation/AreaNavRegistry.php` | First provider that knows the area wins; marks the current item | FR-AREANAV-001 |

## Theme

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Theme/Theme.php` | Host theme: slot classes / attributes, partial overrides, tokens, page-type and bundle exceptions | FR-THEME-002 |
| `Theme/ThemeSlots.php` | Theme slot vocabulary: semantic class + framework defaults per family | FR-THEME-002 |
| `Theme/FontPreload.php` | 2.21: `<link rel="preload" as="font">` tags from `fonts` and a Vite manifest (memoized) | FR-FONTPRELOAD-001 |

## Product tour

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Tour/Tour.php` | 2.13: guided tour of one screen (stable key + ordered steps) | FR-TOUR-001 |
| `Tour/TourBuilder.php` | Immutable builder of a tour from translation keys | FR-TOUR-001 |
| `Tour/TourStep.php` | One anchored or centred tour step | FR-TOUR-001 |

## Twig extensions and helpers

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Twig/ThemeExtension.php` | Page contract Twig API: `nowo_ui_class` / `attrs` / `partial` / `tokens` / `page_header` / `script_names` / `action_form` / `form` / `form_hidden` / `header_actions` / `font_preload` / `visible_when` (2.23), filters `nowo_ui_trans` / `nowo_ui_translatable` | FR-THEME-002, FR-PAGEHEADER-001, FR-SCRIPTS-001, FR-FORM-001, FR-FORM-002, FR-HEADERACTIONS-001, FR-FONTPRELOAD-001, FR-I18N-002, FR-VISIBLEWHEN-001 |
| `Twig/AppearanceExtension.php` | `nowo_ui_appearance()`, `_css()`, `_style(nonce)`, `_presets(mode)` (only when appearance is enabled) | FR-APPEARANCE-005 |
| `Twig/AreaNavExtension.php` | `nowo_ui_area_nav(area, context)` | FR-AREANAV-001 |
| `Twig/ErrorPageExtension.php` | `nowo_ui_error_page()`, `nowo_ui_error_text()`, global `nowo_ui_error_pages`; 2.23 path-only retry URI (`pathOnly()`) | FR-ERROR-001, FR-SEC-223 |
| `Twig/LocaleNames.php` | Locale display names and direction (ext-intl, code fallback) | FR-LOCALE-001 |
| `Twig/RouteLinkExtension.php` | `nowo_ui_route_link()` from `route_links` (longest prefix wins) | FR-EDGETAB-001 |
| `Twig/TranslatableText.php` | Text translated once more by Symfony form themes (`nowo_ui_translatable`) | FR-I18N-002 |

## View models (`View/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `View/Label.php` | Translatable label (key + domain + parameters) | FR-VIEW-001, FR-I18N-001 |
| `View/Link.php` | Plain navigation link | FR-VIEW-001 |
| `View/ViewList.php` | Internal typed list normalisation | FR-VIEW-001 |
| `View/Action.php` | Page / row action (href or route, POST form or `csrfToken`, `_method`, confirm, 2.21 `withDisplay()` / `asIcon()`) | FR-ACTION-001, FR-FORM-001, FR-HEADERACTIONS-001 |
| `View/Confirm.php` | Confirm dialog of a destructive action (type-to-confirm, message placement) | FR-CONFIRM-001 |
| `View/ConfirmPage.php` | Full-page confirm (no-JS fallback) | FR-CONFIRM-001 |
| `View/PageHeader.php` | Title, intro, actions, breadcrumbs mode, back link, area nav, 2.21 `status` / `actionsLayout` | FR-PAGEHEADER-001, FR-STATUS-001, FR-HEADERACTIONS-001 |
| `View/Crumb.php` | One breadcrumb of the plain trail | FR-PAGEHEADER-001 |
| `View/BackLink.php` | 2.7: "Back to …" control above the title | FR-PAGEHEADER-001 |
| `View/PageStatus.php` | 2.21: status badge next to the title (label, tone, tooltip); 2.23 `attributes` | FR-STATUS-001, FR-ITEMATTR-001 |
| `View/HeaderActions.php` | 2.21: split of header actions into icons / primary / more | FR-HEADERACTIONS-001 |
| `View/AreaNav.php` | Area navigation tab bar of a bundle area | FR-AREANAV-001 |
| `View/AreaNavItem.php` | One area tab (2.17 `icon`) | FR-AREANAV-001, FR-TABS-001 |
| `View/ListPage.php` | List scaffold model: filters, table, row actions, pagination, empty state, 2.20 `sorting` / `clientSort` | FR-LIST-001, FR-SORT-002 |
| `View/SafeAttributes.php` | 2.34: check of the per-element attribute options (no on*, style, srcdoc, unsafe URLs) | FR-SEC-001 |
| `View/Table.php` | Data table model (columns, rows, caption, sort, responsive cards, 2.34 `rowAttributes`) | FR-LIST-001, FR-SORT-002 |
| `View/Column.php` | Table column (priority, required, 2.20 sort key / first / type / value key) | FR-LIST-001, FR-SORT-002 |
| `View/FilterField.php` | Filter field options (width, row attributes) | FR-LIST-001 |
| `View/EmptyState.php` | Empty state of a list | FR-LIST-001 |
| `View/Pagination.php` | Pagination shape (`fromArray`, `fromPagerfanta`, `fromKnp`, `fromTotal`, compact variant; 2.23 modes `pager` / `infinite` / `responsive`, `withMode()`) | FR-PAGINATION-001, FR-PAGINATION-002 |
| `View/ShowPage.php` | Show scaffold model: sections, audit meta, 2.21 `stats` | FR-SHOW-001, FR-STAT-001 |
| `View/DetailSection.php` | Definition-list section, 2.21 `facts` layout | FR-SHOW-001, FR-FACTS-001 |
| `View/DetailItem.php` | Label / value item (2.2 `badge` tone; 2.23 `attributes` / `valueAttributes`) | FR-SHOW-001, FR-ITEMATTR-001 |
| `View/Stat.php` | 2.21: one key figure (label, value, hint, tone, href); 2.23 `attributes` / `valueAttributes` | FR-STAT-001, FR-ITEMATTR-001 |
| `View/FormPage.php` | Form scaffold model (actions, danger zone, form themes) | FR-FORMPAGE-001 |
| `View/SettingsPage.php` | Settings scaffold model (sections, current section) | FR-FORMPAGE-001 |
| `View/SettingsSection.php` | One settings section (form themes, 2.17 `icon`) | FR-FORMPAGE-001, FR-TABS-001 |
| `View/WizardPage.php` | Wizard scaffold model (steps, current step, guest layout) | FR-FORMPAGE-001 |
| `View/WizardStep.php` | One wizard step | FR-FORMPAGE-001 |
| `View/DashboardPage.php` | Dashboard scaffold model (KPI tiles, cards) | FR-DASHBOARD-001 |
| `View/KpiTile.php` | KPI tile (variants, link tile) | FR-DASHBOARD-001 |
| `View/AuthPage.php` | Auth scaffold model (form, themes, attributes, footer links, `scripts`) | FR-AUTH-001 |
| `View/ErrorPage.php` | 2.6: what an error page shows (illustration, retry, back, self-reload) | FR-ERROR-001 |
| `View/ErrorIllustration.php` | 2.6: `<picture>` illustration with WebP source and intrinsic size | FR-ERROR-001 |

## Test kit for UI bundles (`Test/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Test/PageContractTestCase.php` | Shared REQ-UI-003 page contract test for UI bundles | FR-TESTKIT-001 |
| `Test/PageFixture.php` | One page fixture (template + context) | FR-TESTKIT-001 |
| `Test/RenderedPage.php` | Rendered page with contract assertions and missing translation ids | FR-TESTKIT-001 |
| `Test/StubHost.php` | Stub host layouts and marker theme | FR-TESTKIT-001 |
| `Test/ContractStubExtension.php` | Deterministic stand-ins for `path`, `url`, `asset`, `csrf_token`, … | FR-TESTKIT-001 |
| `Test/FrameworkClasses.php` | Framework utility-class detection and per-framework allow-lists | FR-TESTKIT-001 |

## Symfony config (`Resources/config/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/services.yaml` | Service wiring (Twig extensions, registries, forms, listing, error pages) | FR-CFG-002 |
| `Resources/config/routes/release_check.yaml` | Opt-in release status route import | FR-RELEASE-002 |

## Translations (`Resources/translations/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/translations/NowoUiKitBundle.en.yaml` | Domain `NowoUiKitBundle`, English | FR-I18N-001 |
| `Resources/translations/NowoUiKitBundle.es.yaml` | Domain `NowoUiKitBundle`, Spanish | FR-I18N-001 |
| `Resources/translations/NowoUiKitBundle.it.yaml` | Domain `NowoUiKitBundle`, Italian | FR-I18N-001 |
| `Resources/translations/NowoUiKitBundle.fr.yaml` | Domain `NowoUiKitBundle`, French | FR-I18N-001 |
| `Resources/translations/NowoUiKitBundle.pt.yaml` | Domain `NowoUiKitBundle`, Portuguese | FR-I18N-001 |
| `Resources/translations/NowoUiKitBundle.de.yaml` | Domain `NowoUiKitBundle`, German | FR-I18N-001 |
| `Resources/translations/NowoUiKitBundle.nl.yaml` | Domain `NowoUiKitBundle`, Dutch | FR-I18N-001 |

## Twig views — layouts (`Resources/views/layout/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/layout/document.html.twig` | Document skeleton: theme boot, kit assets, tokens, appearance style, 2.21 font preload | FR-LAYOUT-001, FR-THEMEBOOT-001, FR-FONTPRELOAD-001 |
| `Resources/views/layout/admin.html.twig` | Admin layout (canonical blocks + navigation region) | FR-LAYOUT-001 |
| `Resources/views/layout/guest.html.twig` | Guest layout (no navigation) | FR-LAYOUT-001 |
| `Resources/views/layout/status.html.twig` | 2.2: self-contained status layout (inline critical CSS) | FR-LAYOUT-002 |
| `Resources/views/layout/_bridge.html.twig` | 2.11: canonical block plumbing for a host layout extending its own shell | FR-LAYOUT-001 |

## Twig views — page scaffolds (`Resources/views/page/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/page/list.html.twig` | List scaffold (filters, table, row actions, pagination, empty state, sorting; 2.23 rows marker for pagination modes) | FR-SCAFFOLD-001, FR-LIST-001, FR-SORT-002, FR-PAGINATION-002 |
| `Resources/views/page/show.html.twig` | Show scaffold (sections, audit meta, 2.21 stats row) | FR-SCAFFOLD-001, FR-SHOW-001, FR-STAT-001 |
| `Resources/views/page/form.html.twig` | Form scaffold | FR-SCAFFOLD-001, FR-FORMPAGE-001 |
| `Resources/views/page/settings.html.twig` | Settings scaffold (section tabs) | FR-SCAFFOLD-001, FR-FORMPAGE-001 |
| `Resources/views/page/wizard.html.twig` | Wizard scaffold (steps indicator) | FR-SCAFFOLD-001, FR-FORMPAGE-001 |
| `Resources/views/page/dashboard.html.twig` | Dashboard scaffold (KPI tiles, cards) | FR-SCAFFOLD-001, FR-DASHBOARD-001 |
| `Resources/views/page/auth.html.twig` | Auth scaffold (guest layout) | FR-SCAFFOLD-001, FR-AUTH-001 |
| `Resources/views/page/confirm.html.twig` | Full-page confirm scaffold | FR-SCAFFOLD-001, FR-CONFIRM-001 |
| `Resources/views/page/_scaffold.html.twig` | Internal scaffold helpers (page header, status badge, actions) | FR-SCAFFOLD-001, FR-STATUS-001 |
| `Resources/views/page/_page_header.html.twig` | 2.3: public page-header contract (2.21 title row + status + split actions) | FR-PAGEHEADER-001, FR-STATUS-001, FR-HEADERACTIONS-001 |
| `Resources/views/page/_detail_list.html.twig` | Definition list of detail items (badges, 2.21 `facts` modifier, 2.23 item attributes) | FR-SHOW-001, FR-FACTS-001, FR-ITEMATTR-001 |
| `Resources/views/page/_list_filters.html.twig` | Filters region of the list scaffold (child loop) | FR-SCAFFOLD-001, FR-LIST-001 |
| `Resources/views/page/_section_form.html.twig` | Settings section form (child loop + submit) | FR-FORMPAGE-001 |
| `Resources/views/page/_wizard_form.html.twig` | Default wizard step body | FR-FORMPAGE-001 |
| `Resources/views/page/_auth_form.html.twig` | Auth form functions (compiles without symfony/form) | FR-AUTH-001 |

## Twig views — app shell (`Resources/views/shell/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/shell/_shell.html.twig` | 2.6: signed-in app shell (open + main + close) | FR-APPSHELL-001 |
| `Resources/views/shell/_open.html.twig` | 2.6: opening half (header, burger, brand, aside, main start) | FR-APPSHELL-001 |
| `Resources/views/shell/_close.html.twig` | 2.6: closing half (footer, mobile backdrop) | FR-APPSHELL-001 |
| `Resources/views/shell/_burger.html.twig` | 2.6: sidebar toggle with next-action labels (rail aware) | FR-APPSHELL-001, FR-SIDEBAR-001 |

## Twig views — error pages (`Resources/views/error/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/error/layout.html.twig` | 2.6: self-sufficient error document (2.21 font preload) | FR-ERROR-001, FR-FONTPRELOAD-001 |
| `Resources/views/error/status.html.twig` | 2.6: one HTTP error page for every status code | FR-ERROR-001, FR-ERROR-002 |

## Twig views — form themes (`Resources/views/form/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/form/nowo_ui_layout.html.twig` | 2.2: UiKit form theme for none / custom / tailwind hosts | FR-FORM-005 |
| `Resources/views/form/nowo_ui_kit_fields.html.twig` | 2.19: theme of the forms UiKit builds for its partials (2.x hidden markup) | FR-FORM-002 |
| `Resources/views/form/nowo_ui_date_range.html.twig` | 2.15: `nowo_ui_date_range_*` widget blocks | FR-DATERANGE-001 |

## Twig views — macros and components (`Resources/views/{macros,components}/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/macros/ui.html.twig` | Multi-framework class helpers (`ui.btn`, toolbar, table, …) | FR-MACRO-001, FR-THEME-001 |
| `Resources/views/components/_icon.html.twig` | Canonical icon kinds per `icon_set` (incl. 2.20 `sort*`, 2.21 `more`) | FR-ICON-001 |
| `Resources/views/components/_icon_ux.html.twig` | Isolated optional `ux_icon()` backend | FR-ICON-001 |

## Twig views — kitchen sink demo (`Resources/views/demo/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/demo/kitchen_sink.html.twig` | Kitchen sink page of every surface (incl. orbs, tables, forms) | FR-DEMO-001, FR-MACRO-001, FR-ORB-001 |
| `Resources/views/demo/_kitchen_sink_filter_bar.html.twig` | Host-chrome filter bar sample from a FormView | FR-DEMO-001, FR-FILTERCOLLAPSE-001 |
| `Resources/views/demo/_kitchen_sink_locale_tabs.html.twig` | Locale tabs sample from a FormView | FR-DEMO-001, FR-LOCALE-001 |
| `Resources/views/demo/_kitchen_sink_swatch.html.twig` | Control swatches from a FormView (never submitted) | FR-DEMO-001 |

## Twig views — partials: actions, confirm, dialogs, menus (`Resources/views/partials/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/partials/_action.html.twig` | One view-model action (toolbar button, row action, 2.21 menu item) | FR-ACTION-001, FR-HEADERACTIONS-001 |
| `Resources/views/partials/_action_inner.html.twig` | Icon + label of an action by display mode | FR-ACTION-001, FR-PARTIAL-001 |
| `Resources/views/partials/_action_post.html.twig` | POST form branch of a non-GET action | FR-ACTION-001, FR-FORM-001 |
| `Resources/views/partials/_action_form_view.html.twig` | FormView branch (`form_start` / `form_end`) of a POST action | FR-FORM-001 |
| `Resources/views/partials/_action_post_legacy.html.twig` | Deprecated (2.18) raw POST fallback | FR-FORM-003 |
| `Resources/views/partials/_header_actions.html.twig` | 2.21: split header actions (icons, primary, "more" menu) | FR-HEADERACTIONS-001 |
| `Resources/views/partials/_confirm.html.twig` | Accessible confirm `<dialog>` | FR-CONFIRM-001, FR-PARTIAL-001 |
| `Resources/views/partials/_confirm_dialog.html.twig` | 2.6: Stimulus `confirm-dialog` wrapper | FR-CONFIRM-001 |
| `Resources/views/partials/_dialog.html.twig` | 2.1: generic native dialog (2.12 side-panel placement) | FR-DIALOG-001 |
| `Resources/views/partials/_modal_shell.html.twig` | Generic modal shell (Bootstrap-JS compatible) | FR-PARTIAL-001, FR-JS-001 |
| `Resources/views/partials/_open_modal_on_load.html.twig` | Open-modal-on-load JSON island | FR-OPENMODAL-001 |
| `Resources/views/partials/_menu.html.twig` | 2.3: `<details>` menu / popover (2.21 `square`, `menu_items`) | FR-MENU-001, FR-HEADERACTIONS-001 |
| `Resources/views/partials/_kebab.html.twig` | Kebab overflow menu (details-based, 2.4 popover) | FR-MENU-001, FR-PARTIAL-001 |

## Twig views — partials: page header, key figures and navigation

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/partials/_page_header.html.twig` | 1.x page header (title, intro, toolbar slot) | FR-PARTIAL-001, FR-PAGEHEADER-001 |
| `Resources/views/partials/_page_status.html.twig` | 2.21: status badge next to the title (2.23 attributes) | FR-STATUS-001, FR-ITEMATTR-001 |
| `Resources/views/partials/_stat_row.html.twig` | 2.21: key figures row (`<dl>` of compact `Stat` tiles) above the details (2.23 tile / value attributes) | FR-STAT-001, FR-ITEMATTR-001 |
| `Resources/views/partials/_breadcrumbs.html.twig` | BreadcrumbKit trail or plain trail by `breadcrumbsMode` | FR-PAGEHEADER-001 |
| `Resources/views/partials/_back_link.html.twig` | 2.7: "Back to …" link | FR-PAGEHEADER-001 |
| `Resources/views/partials/_tabs.html.twig` | Link-based tabs (2.16 icons and tab style) | FR-TABS-001, FR-PARTIAL-001 |
| `Resources/views/partials/_locale_tabs.html.twig` | 2.5: per-locale panels behind a WAI-ARIA tab list | FR-LOCALE-001 |
| `Resources/views/partials/_edge_tab.html.twig` | 1.17: fixed page-edge tab (2.13.2 eight slots, 2.12.1 `attr`) | FR-EDGETAB-001 |
| `Resources/views/partials/_corner_tab.html.twig` | 1.17: card corner tab | FR-CORNERTAB-001 |
| `Resources/views/partials/_disclosure_icon.html.twig` | 1.17: disclosure chevron | FR-DISCLOSURE-001 |
| `Resources/views/partials/_tour.html.twig` | 2.13: guided tour root + "Tour this page" tab | FR-TOUR-001 |

## Twig views — partials: shell chrome and feedback

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/partials/_shell.html.twig` | Composed shell (header, aside, main, footer; 1.15 rail options) | FR-PARTIAL-001, FR-SIDEBAR-001 |
| `Resources/views/partials/_aside.html.twig` | Left aside navigation (flat or grouped) | FR-PARTIAL-001 |
| `Resources/views/partials/_aside_nav_items.html.twig` | Recursive aside nav items | FR-PARTIAL-001 |
| `Resources/views/partials/_burger.html.twig` | Burger / sidebar toggle (rail labels) | FR-PARTIAL-001, FR-SIDEBAR-001 |
| `Resources/views/partials/_brand.html.twig` | Brand mark | FR-PARTIAL-001 |
| `Resources/views/partials/_avatar.html.twig` | Avatar (initials or image) | FR-PARTIAL-001 |
| `Resources/views/partials/_user_menu.html.twig` | User avatar dropdown + logout | FR-PARTIAL-001, FR-FORM-002 |
| `Resources/views/partials/_user_menu_logout_form.html.twig` | 2.19: logout form branch (FormView) | FR-FORM-002 |
| `Resources/views/partials/_user_menu_logout_legacy.html.twig` | Deprecated (2.19) raw logout fallback | FR-FORM-003 |
| `Resources/views/partials/_footer.html.twig` | Site footer | FR-PARTIAL-001 |
| `Resources/views/partials/_width_toggle.html.twig` | Main width toggle (full ↔ content) | FR-PARTIAL-001 |
| `Resources/views/partials/_theme_toggle.html.twig` | Light / dark toggle | FR-PARTIAL-001, FR-JS-001 |
| `Resources/views/partials/_theme_boot.html.twig` | 1.15: pre-paint theme boot (inline with nonce or external) | FR-THEMEBOOT-001 |
| `Resources/views/partials/_locale_switcher.html.twig` | Generic locale switcher | FR-PARTIAL-001, FR-FORM-002 |
| `Resources/views/partials/_locale_switcher_form.html.twig` | 2.19: `form_action` entry branch (FormView) | FR-FORM-002 |
| `Resources/views/partials/_locale_switcher_legacy.html.twig` | Deprecated (2.19) raw fallback | FR-FORM-003 |
| `Resources/views/partials/_flashes.html.twig` | Flash messages | FR-PARTIAL-001 |
| `Resources/views/partials/_toasts.html.twig` | Flashes as a toast stack | FR-PARTIAL-001, FR-JS-001 |
| `Resources/views/partials/_empty.html.twig` | Empty state | FR-PARTIAL-001, FR-LIST-001 |
| `Resources/views/partials/_card.html.twig` | Card / panel | FR-PARTIAL-001 |
| `Resources/views/partials/_progress.html.twig` | 2.2: native `<progress>` with label and percentage | FR-PROGRESS-001 |
| `Resources/views/partials/_page_loader.html.twig` | Page loader (veil, orb, minimal visuals) | FR-PARTIAL-001, FR-ORB-002, FR-LOADER-001 |
| `Resources/views/partials/_page_transition.html.twig` | Cross-document View Transitions | FR-TRANSITION-001 |
| `Resources/views/partials/_thinking_orb.html.twig` | Thinking orb canvas | FR-ORB-001 |
| `Resources/views/partials/_release_version.html.twig` | Version button + lazy release dialog | FR-RELEASE-003 |
| `Resources/views/partials/_scripts.html.twig` | 2.2: deferred kit runtime scripts from the kit asset package | FR-SCRIPTS-001 |
| `Resources/views/partials/_ui_form.html.twig` | 2.19: internal dispatcher of the partial Form Types | FR-FORM-002 |

## Twig views — partials: filters, tables, pagination, segmented

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/partials/_filters.html.twig` | Filter / search bar (collapsible) | FR-PARTIAL-001, FR-FORM-002 |
| `Resources/views/partials/_filters_form.html.twig` | 2.19: GET search FormView branch | FR-FORM-002 |
| `Resources/views/partials/_filters_legacy.html.twig` | Deprecated (2.19) raw search fallback | FR-FORM-003 |
| `Resources/views/partials/_filter_actions.html.twig` | Filter bar actions (pager + Filter + Clear, icon-only) | FR-FILTERBAR-001 |
| `Resources/views/partials/_filters_collapse.html.twig` | 1.17: foldable filter card | FR-FILTERCOLLAPSE-001 |
| `Resources/views/partials/_table.html.twig` | 2.2: data table (caption, sort headers, row actions, confirms; 2.23 `list_rows`) | FR-LIST-001, FR-SORT-002, FR-PAGINATION-002 |
| `Resources/views/partials/_sort_header.html.twig` | 2.20: sortable header content (link or `table-sort` button) | FR-SORT-002, FR-SORT-003 |
| `Resources/views/partials/_responsive_table.html.twig` | 1.17: scroll ↔ responsive table | FR-RESPTABLE-001 |
| `Resources/views/partials/_row_detail_head.html.twig` | 1.17: header cell of the row detail column | FR-RESPTABLE-001 |
| `Resources/views/partials/_row_detail_cell.html.twig` | 1.17: row detail cell | FR-RESPTABLE-001 |
| `Resources/views/partials/_listing_head.html.twig` | Listing header cells from a layout (2.20 sorting) | FR-LISTING-004, FR-SORT-002 |
| `Resources/views/partials/_listing_cells.html.twig` | Listing row cells (stacked columns) | FR-LISTING-004 |
| `Resources/views/partials/_listing_filter_fields.html.twig` | Visible filter rows in viewer order | FR-LISTING-004 |
| `Resources/views/partials/_listing_customizer.html.twig` | Customizer opener + side panel | FR-LISTING-004 |
| `Resources/views/partials/_row_actions.html.twig` | Row action cluster (`display`, popover) | FR-PARTIAL-001, FR-ACTION-001 |
| `Resources/views/partials/_row_actions_form.html.twig` | 2.19: 1.x POST hash FormView branch | FR-FORM-002 |
| `Resources/views/partials/_row_actions_legacy.html.twig` | Deprecated (2.19) raw POST fallback | FR-FORM-003 |
| `Resources/views/partials/_pagination.html.twig` | Server-side pagination | FR-PAGINATION-001, FR-PARTIAL-001 |
| `Resources/views/partials/_pagination_compact.html.twig` | 2.6: compact pagination + per-page select | FR-PAGINATION-001 |
| `Resources/views/partials/_pagination_mode.html.twig` | 2.23: `list-pagination` peer contract of the pagination modes (root attributes, load-more region, live region) | FR-PAGINATION-002 |
| `Resources/views/partials/_pagination_per_page_form.html.twig` | 2.19: per-page FormView branch | FR-FORM-002, FR-PAGINATION-001 |
| `Resources/views/partials/_pagination_per_page_legacy.html.twig` | Deprecated (2.19) raw per-page fallback | FR-FORM-003 |
| `Resources/views/partials/_segmented.html.twig` | 2.4: segmented control backed by one checkbox | FR-SEGMENTED-001 |
| `Resources/views/partials/_segmented_field.html.twig` | 2.4: checkbox FormView of the segmented control | FR-SEGMENTED-001 |
| `Resources/views/partials/_segmented_checkbox_form.html.twig` | 2.19: plain-checkbox `CheckboxType` branch | FR-SEGMENTED-001, FR-FORM-002 |
| `Resources/views/partials/_segmented_legacy_checkbox.html.twig` | Deprecated (2.19) raw checkbox fallback | FR-FORM-003 |

## Stylesheets (authored)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/public/css/nowo-ui.unlayered.css` | Semantic `nowo-ui-*` styles and tokens, unlayered source (1.x cascade) | FR-CSS-001, FR-THEME-001 |
| `Resources/public/css/nowo-ui-tom-select.css` | 2.2: Tom Select chips painted from the primary token | FR-CSS-003 |
| `Resources/public/css/nowo-ui-multi-select.css` | 2.14: standalone `multi-select` skin | FR-MULTISELECT-001 |
| `Resources/assets/styles/_thin-scrollbars.scss` | 1.17: thin scrollbars Sass mixin for host selectors | FR-SCROLLBAR-001 |

## TypeScript — kit IIFE sources (`Resources/assets/src/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/assets/src/nowo-ui-modal.ts` | Modal open / close for custom / none / tailwind stacks | FR-JS-001 |
| `Resources/assets/src/nowo-ui-shell.ts` | Shell: burger, aside, backdrop, width toggle, opt-in rail | FR-JS-001, FR-SIDEBAR-001 |
| `Resources/assets/src/nowo-ui-sidebar-core.ts` | 1.15: side-effect-free three-mode sidebar helpers | FR-SIDEBAR-001 |
| `Resources/assets/src/nowo-ui-toast.ts` | Toast dismiss + auto-hide | FR-JS-001 |
| `Resources/assets/src/nowo-ui-sortable.ts` | Opt-in reorder by pointer, touch and keyboard over host markup | FR-JS-001 |
| `Resources/assets/src/nowo-ui-confirm.ts` | Native confirm dialog open / close, type-to-confirm; 2.22 missing-peer warning, 2.23 `window.confirm` fail-closed fallback | FR-JS-001, FR-CONFIRM-001, FR-SEC-223 |
| `Resources/assets/src/nowo-ui-page-loader.ts` | Page loader overlay | FR-JS-001, FR-LOADER-001 |
| `Resources/assets/src/nowo-ui-theme.ts` | Theme toggle (`data-theme`, `data-bs-theme`, follows OS) | FR-JS-001 |
| `Resources/assets/src/nowo-ui-theme-core.ts` | Shared theme storage helpers (boot + toggle) | FR-THEMEBOOT-001 |
| `Resources/assets/src/nowo-ui-theme-boot.ts` | 1.15: pre-paint theme / density / motion boot | FR-THEMEBOOT-001 |
| `Resources/assets/src/nowo-ui-orb.ts` | Thinking orb auto-mount | FR-ORB-001 |
| `Resources/assets/src/nowo-ui-clipboard.ts` | Clipboard copy (`data-nowo-ui-clipboard*`) | FR-CLIPBOARD-001 |
| `Resources/assets/src/nowo-ui-tabs.ts` | In-page tab panels (2.5.2 locale tabs legends) | FR-TABS-001, FR-LOCALE-001 |
| `Resources/assets/src/nowo-ui-release-core.ts` | Side-effect-free release dialog logic | FR-RELEASE-003 |
| `Resources/assets/src/nowo-ui-release.ts` | Lazy release dialog IIFE | FR-RELEASE-003 |
| `Resources/assets/src/nowo-ui-open-modal-core.ts` | Side-effect-free open-modal-on-load helpers | FR-OPENMODAL-001 |
| `Resources/assets/src/nowo-ui-open-modal.ts` | Open-modal-on-load IIFE | FR-OPENMODAL-001 |
| `Resources/assets/src/nowo-ui-dialog.ts` | 2.1: generic native dialog runtime (backdrop close, focus return) | FR-DIALOG-001 |
| `Resources/assets/src/nowo-ui-popover.ts` | 2.4: popover menu placement and keyboard | FR-MENU-001 |
| `Resources/assets/src/nowo-ui-segmented.ts` | 2.4: segmented radio group synced to its checkbox | FR-SEGMENTED-001 |
| `Resources/assets/src/nowo-ui-autosubmit.ts` | 2.6: `data-nowo-ui-autosubmit` submits on change | FR-AUTOSUBMIT-001 |
| `Resources/assets/src/nowo-ui-error-retry-core.ts` | 2.6: error page self-reload countdown logic (2.23 `pathOnlyUrl()`) | FR-ERROR-002, FR-SEC-223 |
| `Resources/assets/src/nowo-ui-error-retry.ts` | 2.6: error page self-reload IIFE | FR-ERROR-002 |

## TypeScript — Thinking Orbs (vendored MIT, `Resources/assets/src/orb/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/assets/src/orb/types.ts` | Orb types (vanilla port) | FR-ORB-001 |
| `Resources/assets/src/orb/theme.ts` | Theme and reduced-motion helpers | FR-ORB-001 |
| `Resources/assets/src/orb/mount.ts` | Vanilla canvas mount (`mountThinkingOrb`) | FR-ORB-001 |
| `Resources/assets/src/orb/presets.ts` | Nine states × two sizes tunings | FR-ORB-001 |
| `Resources/assets/src/orb/engine/types.ts` | Engine contracts | FR-ORB-001 |
| `Resources/assets/src/orb/engine/core.ts` | Shared paint primitives | FR-ORB-001 |
| `Resources/assets/src/orb/engine/profiles.ts` | Density profiles | FR-ORB-001 |
| `Resources/assets/src/orb/engine/registry.ts` | Mode key → frame painter | FR-ORB-001 |
| `Resources/assets/src/orb/engine/orbits.ts` | Mode: orbits | FR-ORB-001 |
| `Resources/assets/src/orb/engine/lattice.ts` | Modes: globe, rubik, wave | FR-ORB-001 |
| `Resources/assets/src/orb/engine/web.ts` | Mode: web | FR-ORB-001 |
| `Resources/assets/src/orb/engine/braid.ts` | Mode: braid | FR-ORB-001 |
| `Resources/assets/src/orb/engine/ribbon.ts` | Modes: ribbon, ring | FR-ORB-001 |
| `Resources/assets/src/orb/engine/morph.ts` | Mode: morph | FR-ORB-001 |

## TypeScript — morphicons (optional, `Resources/assets/morphicons/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/assets/morphicons/index.ts` | 1.17: morph-icon helpers and `installMorphiconPeers()` (optional `morphicons` + `lucide`) | FR-MORPH-001 |

## Stimulus peers (`Resources/assets/stimulus-peers/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/assets/stimulus-peers/README.md` | Peer catalogue (identifier ↔ kit IIFE) | FR-PEER-001 |
| `Resources/assets/stimulus-peers/ask_confirm.ts` | 2.8: promise-based `askConfirm()` in a native dialog | FR-PEER-001, FR-CONFIRM-001 |
| `Resources/assets/stimulus-peers/auto_refresh_controller.ts` | `auto-refresh`: periodic region re-render | FR-PEER-002 |
| `Resources/assets/src/nowo-ui-fragment-filter.ts` | Fragment filter shared by `parseFragment()`, `auto-refresh` and `list-pagination` | FR-PEER-002, FR-PAGINATION-002 |
| `Resources/assets/stimulus-peers/clipboard_copy_controller.ts` | `clipboard-copy` | FR-PEER-001, FR-CLIPBOARD-001 |
| `Resources/assets/stimulus-peers/collapse_panel_controller.ts` | 1.15: `collapse-panel` (state in a localStorage map) | FR-PEER-003 |
| `Resources/assets/stimulus-peers/confirm_dialog_controller.ts` | `confirm-dialog` (typed confirmation, body portal) | FR-PEER-001, FR-CONFIRM-001 |
| `Resources/assets/stimulus-peers/confirm_submit_controller.ts` | `confirm-submit` (submit / click, `blocked`, 2.8 dialog mode; 2.23 approval cleared after the replay) | FR-PEER-001, FR-SEC-223 |
| `Resources/assets/stimulus-peers/datatable_controller.ts` | 1.15: `datatable` (optional DataTables peer deps) | FR-PEER-005 |
| `Resources/assets/stimulus-peers/date_range_controller.ts` | 2.15: `date-range` (Cally range calendar on demand) | FR-DATERANGE-001 |
| `Resources/assets/stimulus-peers/disclosure_icon_controller.ts` | 1.17: `disclosure-icon` | FR-DISCLOSURE-001 |
| `Resources/assets/stimulus-peers/error_retry_countdown_controller.ts` | 2.6: `error-retry-countdown` for host markup | FR-ERROR-002 |
| `Resources/assets/stimulus-peers/filters_collapse_controller.ts` | 1.17: `filters-collapse` (cookie of folded page keys) | FR-FILTERCOLLAPSE-001 |
| `Resources/assets/stimulus-peers/icon_state.ts` | 1.17: icon state bridge (no icon library import) | FR-MORPH-001 |
| `Resources/assets/stimulus-peers/listing_cookie.ts` | Listing preference cookie read / write | FR-LISTING-003 |
| `Resources/assets/stimulus-peers/listing_customizer_controller.ts` | 2.13: `listing-customizer` (show / hide / reorder / stack) | FR-LISTING-004 |
| `Resources/assets/stimulus-peers/list_pagination_controller.ts` | 2.23: `list-pagination` (pagination modes `infinite` / `responsive`: fetch + append the next page rows, load more, live region) | FR-PAGINATION-002 |
| `Resources/assets/stimulus-peers/multi_select_controller.ts` | 2.14: `multi-select` (Tom Select, native fallback) | FR-MULTISELECT-001 |
| `Resources/assets/stimulus-peers/page_loader_controller.ts` | `page-loader` (min-visible timing, leave animation) | FR-PEER-001, FR-LOADER-001 |
| `Resources/assets/stimulus-peers/product_tour_controller.ts` | 2.13: `product-tour` (driver.js on demand) | FR-TOUR-001 |
| `Resources/assets/stimulus-peers/release_status_controller.ts` | `release-status` | FR-PEER-001, FR-RELEASE-003 |
| `Resources/assets/stimulus-peers/responsive_table_controller.ts` | 1.17: `responsive-table` | FR-RESPTABLE-001 |
| `Resources/assets/stimulus-peers/table_sort_controller.ts` | 2.20: `table-sort` (client-side sort) | FR-SORT-003 |
| `Resources/assets/stimulus-peers/tabs_controller.ts` | `tabs` | FR-PEER-001, FR-TABS-001 |
| `Resources/assets/stimulus-peers/temporary_reveal_controller.ts` | 1.15: `temporary-reveal` (masked secret, auto-hide) | FR-PEER-004 |
| `Resources/assets/stimulus-peers/visible_when_controller.ts` | 2.23: `visible-when` (provider-dependent rows: hidden / inert / aria-hidden, never disabled or cleared) | FR-VISIBLEWHEN-001 |
| `Resources/assets/stimulus-peers/toast_stack_controller.ts` | `toast-stack` | FR-PEER-001 |

## Build outputs (generated, committed, never hand-edited)

Produced by `pnpm run build` (`vite.config.ts`, one `VITE_ENTRY` per IIFE; `build:theme-boot`; `build:css` =
`.scripts/build-layered-css.mjs`).

| Source file | Produced by | Requirement IDs |
| --- | --- | --- |
| `Resources/public/js/nowo-ui-modal.js` | `assets/src/nowo-ui-modal.ts` | FR-BUILD-001, FR-JS-001 |
| `Resources/public/js/nowo-ui-shell.js` | `assets/src/nowo-ui-shell.ts` | FR-BUILD-001, FR-JS-001 |
| `Resources/public/js/nowo-ui-toast.js` | `assets/src/nowo-ui-toast.ts` | FR-BUILD-001, FR-JS-001 |
| `Resources/public/js/nowo-ui-confirm.js` | `assets/src/nowo-ui-confirm.ts` | FR-BUILD-001, FR-JS-001 |
| `Resources/public/js/nowo-ui-page-loader.js` | `assets/src/nowo-ui-page-loader.ts` | FR-BUILD-001, FR-JS-001 |
| `Resources/public/js/nowo-ui-theme.js` | `assets/src/nowo-ui-theme.ts` | FR-BUILD-001, FR-JS-001 |
| `Resources/public/js/nowo-ui-theme-boot.js` | `assets/src/nowo-ui-theme-boot.ts` | FR-BUILD-001, FR-THEMEBOOT-001 |
| `Resources/public/js/nowo-ui-orb.js` | `assets/src/nowo-ui-orb.ts` (+ `orb/`) | FR-BUILD-001, FR-ORB-001 |
| `Resources/public/js/nowo-ui-clipboard.js` | `assets/src/nowo-ui-clipboard.ts` | FR-BUILD-001, FR-CLIPBOARD-001 |
| `Resources/public/js/nowo-ui-tabs.js` | `assets/src/nowo-ui-tabs.ts` | FR-BUILD-001, FR-TABS-001 |
| `Resources/public/js/nowo-ui-release.js` | `assets/src/nowo-ui-release.ts` | FR-BUILD-001, FR-RELEASE-003 |
| `Resources/public/js/nowo-ui-open-modal.js` | `assets/src/nowo-ui-open-modal.ts` | FR-BUILD-001, FR-OPENMODAL-001 |
| `Resources/public/js/nowo-ui-dialog.js` | `assets/src/nowo-ui-dialog.ts` | FR-BUILD-001, FR-DIALOG-001 |
| `Resources/public/js/nowo-ui-popover.js` | `assets/src/nowo-ui-popover.ts` | FR-BUILD-001, FR-MENU-001 |
| `Resources/public/js/nowo-ui-segmented.js` | `assets/src/nowo-ui-segmented.ts` | FR-BUILD-001, FR-SEGMENTED-001 |
| `Resources/public/js/nowo-ui-autosubmit.js` | `assets/src/nowo-ui-autosubmit.ts` | FR-BUILD-001, FR-AUTOSUBMIT-001 |
| `Resources/public/js/nowo-ui-error-retry.js` | `assets/src/nowo-ui-error-retry.ts` | FR-BUILD-001, FR-ERROR-002 |
| `Resources/views/inline/nowo-ui-theme-boot.js` | Copy of `public/js/nowo-ui-theme-boot.js` (`build:theme-boot`), printed inline by `_theme_boot` | FR-BUILD-003, FR-THEMEBOOT-001 |
| `Resources/public/css/nowo-ui.css` | `public/css/nowo-ui.unlayered.css` wrapped in `@layer nowo-ui` (`build:css`) | FR-BUILD-002, FR-CSS-002 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| PHP — bundle / DI / enums / routing / release | 13 | 13 |
| PHP — appearance SPI | 11 | 11 |
| PHP — forms | 11 | 11 |
| PHP — listing | 12 | 12 |
| PHP — navigation / theme / tour / Twig | 15 | 15 |
| PHP — view models | 33 | 33 |
| PHP — test kit | 6 | 6 |
| **PHP subtotal** | **101** | **101** |
| TS — kit IIFE sources | 22 | 22 |
| TS — Thinking Orbs | 14 | 14 |
| TS — morphicons | 1 | 1 |
| TS — Stimulus peers | 23 | 23 |
| **TS subtotal (excl. `*.test.ts`)** | **60** | **60** |
| **PHP + TS production units** | **161** | **161** |
| Twig views | 112 | 112 |
| YAML (config 2 + translations 7) | 9 | 9 |
| CSS (authored 3 + generated 1) | 4 | 4 |
| SCSS | 1 | 1 |
| JS build outputs (17 IIFEs + 1 inline copy) | 18 | 18 |
| Markdown (Stimulus peers README) | 1 | 1 |
| **Total production sources under `src/` (excl. `*.test.ts`)** | **306** | **306** |
| Excluded: co-located Vitest `*.test.ts` | 42 | — |

Of the 306 rows, 19 are build outputs (18 JS + the layered `nowo-ui.css`) documented as products of named sources; the
other 287 are authored units.

Audit (2026-11-01, 2.21.0):

```bash
git ls-files src | grep -v '\.test\.ts$' | wc -l                      # 306
git ls-files src | grep -v '\.test\.ts$' | grep -c '\.php$'           # 101
git ls-files src | grep -v '\.test\.ts$' | grep -c '\.ts$'            # 60
git ls-files src | grep -c '\.test\.ts$'                              # 42 (excluded)
comm -3 <(git ls-files src | sort) <(find src -type f | sort)         # empty: no untracked file
```
