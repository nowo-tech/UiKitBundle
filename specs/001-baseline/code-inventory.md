# Code inventory — 100% traceability

**Last audited**: 2026-11-27 (2.75.0 line: dates for people mapped, 478 files; 2.74.1, 475/475 re-verified; 2026-11-24, 2.74.0 line: owning page parameters — resolver SPI, mapping check, resolver pass, controller-ran listener mapped, 475 files; 2.73.0 line: remote dialog content opened directly mapped, Coverage summary category counts recounted; v2.72.0: every `src/` file mapped again — 2.65-2.71 confirm runtimes, one-time secret and console banner; 2026-11-22 v2.64.0: 2.56-2.64 components, runtimes, demo platform and lint mapped; 2026-11-17: 2.55 line: shell focus mode; 2.54.0 line: context menus; 2.53.0 line: inset sidebar style, dialog focus return, async combobox; 2.51.0 line: brand appearance, remote dialog, listing ports, side panel, rail, tree, image input, base stylesheet)  
**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/ui-kit-bundle`

This file proves that **every production source artifact** under `src/` is referenced by the baseline specification,
one row per file. Paths are relative to `src/`. Requirement IDs are defined in [`spec.md`](spec.md) (section
"Requirements"); feature specs `specs/002+` refine some of them.

**Scope**: every file tracked by Git under `src/` (`git ls-files src`), i.e. PHP, TypeScript, Twig, YAML, CSS, SCSS,
the built JavaScript and the Stimulus peers README. **Excluded**: the co-located Vitest files `*.test.ts` under
`Resources/assets/` (105 files at this audit — test artifacts covered by FR-TEST-001, not shipped runtime units; same rule as
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
| `Form/EmojiPickerType.php` | Emoji picker field: text input, one grapheme checked on the server | FR-FORM-001 |
| `Form/ComboboxType.php` | 2.53 async grouped combobox field: text input, search URL and bounds checked | FR-FORM-001 |
| `Form/DateRangeType.php` | 2.15: date-range filter field (two native dates, host-chosen `from_name` / `to_name`) | FR-DATERANGE-001 |
| `Form/DateRangeRequestHandler.php` | 2.15.1: request handler decorator — a URL carrying only a range submits the nameless filter form | FR-DATERANGE-001 |
| `Form/DateRangeSubmitExtension.php` | 2.15: moves the flat sibling from / to parameters onto the date-range child before submit | FR-DATERANGE-001 |
| `Form/ImageCrop.php` | 2.43: attribute contract of the image crop step (`ImageCrop::attributes()`, Twig `nowo_ui_image_crop_attrs()`) | FR-FORM-001 |
| `Form/ImageCropTypeExtension.php` | 2.43: FileType option `nowo_ui_image_crop` | FR-FORM-001 |
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
| `Twig/ActionIconsExtension.php` | Globals `nowo_ui_kit_action_icons` / `nowo_ui_kit_action_variants` from `action_icons` / `action_variants`, name grammar, kind list | FR-THEME-002 |
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
| `View/ListPage.php` | List scaffold model: filters, table, row actions, pagination, empty state, 2.20 `sorting` / `clientSort`, 2.72 `filtersMore` / `filtersMoreOpen` | FR-LIST-001, FR-SORT-002 |
| `View/SafeAttributes.php` | 2.34: check of the per-element attribute options (no on*, style, srcdoc, unsafe URLs) | FR-SEC-001 |
| `View/Table.php` | Data table model (columns, rows, caption, sort, responsive cards, 2.34 `rowAttributes`) | FR-LIST-001, FR-SORT-002 |
| `View/Column.php` | Table column (priority, required, 2.20 sort key / first / type / value key) | FR-LIST-001, FR-SORT-002 |
| `View/FilterField.php` | Filter field options (width, row attributes; 2.72 `more` / `pager` placement) | FR-LIST-001 |
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
| `Test/PageContractTestCase.php` | Shared REQ-UI-003 page contract test for UI bundles | FR-TESTKIT-001, FR-FORMACTIONS-001 |
| `Test/PageFixture.php` | One page fixture (template + context) | FR-TESTKIT-001 |
| `Test/RenderedPage.php` | Rendered page with contract assertions and missing translation ids | FR-TESTKIT-001, FR-FORMACTIONS-001 |
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
| `Resources/views/page/form.html.twig` | Form scaffold | FR-SCAFFOLD-001, FR-FORMPAGE-001, FR-FORMACTIONS-001 |
| `Resources/views/page/settings.html.twig` | Settings scaffold (section tabs) | FR-SCAFFOLD-001, FR-FORMPAGE-001 |
| `Resources/views/page/wizard.html.twig` | Wizard scaffold (steps indicator) | FR-SCAFFOLD-001, FR-FORMPAGE-001 |
| `Resources/views/page/dashboard.html.twig` | Dashboard scaffold (KPI tiles, cards) | FR-SCAFFOLD-001, FR-DASHBOARD-001 |
| `Resources/views/page/auth.html.twig` | Auth scaffold (guest layout) | FR-SCAFFOLD-001, FR-AUTH-001 |
| `Resources/views/page/confirm.html.twig` | Full-page confirm scaffold | FR-SCAFFOLD-001, FR-CONFIRM-001 |
| `Resources/views/page/_scaffold.html.twig` | Internal scaffold helpers (page header, status badge, actions) | FR-SCAFFOLD-001, FR-STATUS-001 |
| `Resources/views/page/_page_header.html.twig` | 2.3: public page-header contract (2.21 title row + status + split actions) | FR-PAGEHEADER-001, FR-STATUS-001, FR-HEADERACTIONS-001 |
| `Resources/views/page/_detail_list.html.twig` | Definition list of detail items (badges, 2.21 `facts` modifier, 2.23 item attributes) | FR-SHOW-001, FR-FACTS-001, FR-ITEMATTR-001 |
| `Resources/views/page/_list_filters.html.twig` | Filters region of the list scaffold (child loop; 2.65 opt-in phone sheet; 2.72 Beacon filter standard: More filters fold, compact pager, icon-only actions) | FR-SCAFFOLD-001, FR-LIST-001, FR-FILTERSHEET-002 |
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
| `Resources/views/macros/filter_sheet.html.twig` | Filter sheet button, active count and native `<dialog>` shared by `filter_bar` and `list_filters` (2.65; 2.72 icon close button, light dismiss) | FR-FILTERSHEET-001, FR-FILTERSHEET-002 |
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
| `Resources/views/partials/_emoji_picker.html.twig` | Emoji picker wrapper around a text input | FR-PARTIAL-001 |
| `Resources/views/partials/_combobox.html.twig` | 2.53 combobox wrapper around a text input (listbox, status) | FR-PARTIAL-001 |
| `Resources/views/partials/_show_more.html.twig` | Show more fold | FR-PARTIAL-001 |
| `Resources/views/form/nowo_ui_emoji_picker.html.twig` | Emoji picker field's form block | FR-FORM-001 |
| `Resources/views/form/nowo_ui_combobox.html.twig` | Combobox field's form block | FR-FORM-001 |
| `Resources/views/partials/_dialog.html.twig` | 2.1: generic native dialog (2.12 side-panel placement) | FR-DIALOG-001 |
| `Resources/views/partials/_modal_shell.html.twig` | Generic modal shell (Bootstrap-JS compatible) | FR-PARTIAL-001, FR-JS-001 |
| `Resources/views/partials/_open_modal_on_load.html.twig` | Open-modal-on-load JSON island | FR-OPENMODAL-001 |
| `Resources/views/partials/_menu.html.twig` | 2.3: `<details>` menu / popover (2.21 `square`, `menu_items`) | FR-MENU-001, FR-HEADERACTIONS-001 |
| `Resources/views/partials/_kebab.html.twig` | Kebab overflow menu (details-based, 2.4 popover; 2.58 separators, rows, `sheet`) | FR-MENU-001, FR-PARTIAL-001, FR-CTXMENU-ROW-001, FR-CTXMENU-SHEET-001 |

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
| `Resources/public/css/nowo-ui.unlayered.css` | Semantic `nowo-ui-*` styles and tokens, unlayered source (1.x cascade); 2.74.1: "More filters" rows stack and fill, shared filter control height token, explicit toolbar `flex-direction: row` | FR-CSS-001, FR-THEME-001 |
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
| `Resources/assets/src/nowo-ui-image-crop.ts` | Opt-in crop step for image file inputs (`nowo-ui-image-crop-core.ts`: options, geometry, output) | FR-JS-001 |
| `Resources/assets/src/nowo-ui-emoji-picker.ts` | Opt-in emoji picker over a text input (`nowo-ui-emoji-table.ts`: its table) | FR-JS-001 |
| `Resources/assets/src/nowo-ui-combobox.ts` | Opt-in async grouped combobox over a text input (same-origin search, text-only listbox) | FR-JS-001 |
| `Resources/assets/src/nowo-ui-context-menu.ts` | 2.54 opt-in context menus: the kebab's items on right-click, keyboard and long press (proxy items); 2.58 rows and phone bottom sheet | FR-JS-001, FR-CTXMENU-001, FR-CTXMENU-ROW-001, FR-CTXMENU-SHEET-001 |
| `Resources/assets/src/nowo-ui-show-more.ts` | Opt-in line-clamp fold with a real button | FR-JS-001 |
| `Resources/assets/src/nowo-ui-motion-core.ts` | Closing with an effect: fade, then the real close, with a fallback timer | FR-JS-001 |
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
| `Resources/assets/src/nowo-ui-focus-return.ts` | 2.52.2: returns focus to the opener (or its menu toggle) when a dialog closes (WCAG 2.4.3) | FR-DIALOG-002 |
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

## Brand appearance (spec 220)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Appearance/AppearanceLogos.php` | Brand images of an appearance: URLs only, filtered by `SafeHref::filterImage()`, never markup or bytes | FR-BRAND-001 |
| `Appearance/AppearanceView.php` | What the templates read from the appearance SPI; `current()` never throws so error pages render with a broken provider | FR-BRAND-001 |
| `Appearance/BrandAppearanceProvider.php` | Default provider built from a `nowo-tech/brand-contracts` `BrandProviderInterface` service | FR-BRAND-002 |
| `DependencyInjection/Compiler/BrandAppearanceProviderPass.php` | Points `appearance.provider` at the brand provider when every condition holds, else removes the candidate | FR-BRAND-002 |
| `Resources/views/partials/_appearance_brand.html.twig` | Brand region of the guest and error layouts: logo lockup or nothing | FR-BRAND-001 |
| `Resources/views/partials/_brand_logos.html.twig` | Logo lockup: light and dark `<img>` switched by CSS, never inline markup | FR-BRAND-001 |

## Avatar URL policy

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Avatar/AvatarUrlPolicy.php` | The only picture URLs the `_avatar` partial puts in `<img src>` (2.26) | FR-AVATAR-001 |
| `Twig/AvatarExtension.php` | `nowo_ui_avatar_url` filter applying the policy | FR-AVATAR-001 |
| `Resources/assets/src/nowo-ui-avatar.ts` | Picture fallback over the initials; opt-in `data-nowo-avatar-image` for other bundles | FR-AVATAR-001 |
| `Resources/public/js/nowo-ui-avatar.js` | Build output of `nowo-ui-avatar.ts` | FR-BUILD-001, FR-AVATAR-001 |

## Dates for people

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Date/DateFormatterInterface.php` | Host contract: a date as a person reads it, five kinds, optional zone (2.75) | FR-DATE-001 |
| `Date/IsoDateFormatter.php` | Default formatter: the fixed ISO-style output, aliased to the contract | FR-DATE-001 |
| `Twig/DateExtension.php` | `nowo_date` and `nowo_time_tag` filters over the host formatter | FR-DATE-001 |

## Error illustration SPI

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `View/ErrorIllustrationProviderInterface.php` | Optional host SPI: illustration per status code | FR-ERROR-003 |
| `DependencyInjection/Compiler/ErrorIllustrationProviderPass.php` | Fails the container build when the configured provider service is missing or of the wrong type | FR-ERROR-003 |

## Remote form dialog (2.28 / 2.33 / 2.73)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Dialog/RemoteDialog.php` | Dialog request detection and `Vary` for the request header | FR-REMOTEDIALOG-001 |
| `Dialog/RemoteDialogHeaders.php` | The three header names (request, redirect, step) and their validation | FR-REMOTEDIALOG-001 |
| `Dialog/RemoteDialogTarget.php` | Redirect target rule: path-absolute without unsafe segments, or same-origin absolute URL reduced to a path | FR-REMOTEDIALOG-001 |
| `Dialog/RemoteDialogResponder.php` | Server side: success and step answers, template choice for page versus fragment | FR-REMOTEDIALOG-002 |
| `Dialog/RemoteDialogRedirectListener.php` | Opt-in `convert_redirects`: a same-origin redirect becomes `204` + `X-Nowo-Redirect` | FR-REMOTEDIALOG-003 |
| `Dialog/RemoteDialogRedirectEvent.php` | Hook to change the proposed answer before the conversion | FR-REMOTEDIALOG-003 |
| `Resources/assets/src/nowo-ui-remote-form-core.ts` | Client core: loads a form page into a native `<dialog>` and posts it with `fetch` | FR-REMOTEDIALOG-004 |
| `Resources/assets/stimulus-peers/remote_form_dialog_controller.ts` | Optional Stimulus peer driving the same contract | FR-REMOTEDIALOG-004, FR-PEER-001 |
| `Resources/views/partials/_remote_form_dialog.html.twig` | The `dialog` partial prepared for a remote form (Stimulus or IIFE driver) | FR-REMOTEDIALOG-004 |
| `Resources/views/layout/fragment.html.twig` | Dialog fragment layout printing only the `body` block | FR-REMOTEDIALOG-004 |
| `Dialog/RemoteDialogContent.php` | 2.73: one route marked as dialog content (owning page, dialog id, page parameters, `redirect_same_origin`) and its validation | FR-REMOTEDIALOG-005 |
| `Dialog/RemoteDialogContentMap.php` | 2.73: the marked routes (`remote_dialog.direct_get.routes`), the reopen parameter and each dialog's safe list | FR-REMOTEDIALOG-005 |
| `Dialog/RemoteDialogDirectGetListener.php` | 2.73: a top-level GET of a marked route answered `200` HTML becomes a `302` to its owning page (after the controller's access check) | FR-REMOTEDIALOG-005 |
| `Dialog/RemoteDialogReopen.php` | 2.73: the reopen query of the redirect and the owning page's dialog attributes (safe list, target regenerated by the router); 2.74: mapped / default / resolved page parameters | FR-REMOTEDIALOG-005, FR-REMOTEDIALOG-006 |
| `Dialog/RemoteDialogPageResolverInterface.php` | 2.74: SPI for owning page parameters that come from the record (`page_resolver`) | FR-REMOTEDIALOG-006 |
| `Dialog/RemoteDialogControllerRanListener.php` | 2.74 (audit ZBD-L1): marks a request whose controller ran (after `#[IsGranted]`), clears it on exception; resolvers run only for marked requests | FR-REMOTEDIALOG-006 |
| `Dialog/RemoteDialogPageMappingCheck.php` | 2.74: non-optional cache warmer checking mapped entries against the route collection at container build | FR-REMOTEDIALOG-006 |
| `DependencyInjection/Compiler/RemoteDialogPageResolverPass.php` | 2.74: fails the container build when a `page_resolver` service is missing or of the wrong type | FR-REMOTEDIALOG-006 |
| `Resources/assets/src/nowo-ui-remote-dialog-reopen.ts` | 2.73: client reopen: safe-listed route, same-origin path, `history.replaceState` clean-up | FR-REMOTEDIALOG-005 |

## Image input

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Form/ImageInput.php` | Options of the image input box (stored or default image, preview, Replace / Remove / Undo) | FR-IMAGEINPUT-001 |
| `Form/ImageInputTypeExtension.php` | FileType option `nowo_ui_image_input`; `false` keeps the FileType unchanged | FR-IMAGEINPUT-001 |
| `Resources/views/form/nowo_ui_image_input.html.twig` | Form theme block `nowo_ui_image_input_widget` | FR-IMAGEINPUT-001 |
| `Resources/views/partials/_image_input.html.twig` | Theme partial `image_input`; works without JavaScript | FR-IMAGEINPUT-001 |
| `Resources/assets/src/nowo-ui-image-input.ts` | Opt-in runtime of the box | FR-IMAGEINPUT-001 |
| `Resources/assets/src/nowo-ui-image-crop-core.ts` | Crop core without a DOM: options, geometry, output size and type | FR-JS-001 |
| `Resources/public/js/nowo-ui-image-input.js` | Build output of `nowo-ui-image-input.ts` | FR-BUILD-001, FR-IMAGEINPUT-001 |
| `Resources/public/js/nowo-ui-image-crop.js` | Build output of `nowo-ui-image-crop.ts` | FR-BUILD-001, FR-JS-001 |

## Listing ports (framework-free, 2.49)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Listing/ListingContext.php` | Framework-free context of one listing page view (listing cookies, route, scalar parameters, locale, viewer id) | FR-LISTING-005 |
| `Listing/ListingPreferenceReaderInterface.php` | Port reading the preferences of one viewer from a `ListingContext` | FR-LISTING-005 |
| `Listing/ListingViewerResolverInterface.php` | Optional SPI giving the opaque viewer id | FR-LISTING-005 |
| `Listing/Http/ListingContextFactory.php` | HTTP adapter: copies only listing cookies, route, scalar parameters and locale | FR-LISTING-005 |
| `Theme/ListFiltersOptions.php` | Host options of the list scaffold filters (`theme.pages.list.filters`; 2.65 `sheet`) | FR-LISTING-006, FR-FILTERSHEET-002 |

## Compact header, breadcrumbs, page actions and sidebar layout

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Shell/HeaderCrumbs.php` | Compact breadcrumb of the top bar from translated levels | FR-HEADER-001 |
| `Shell/HeaderPresentation.php` | Header presentation enum (standard, compact, inline, no intro, no title, no header) | FR-HEADER-002 |
| `Shell/HeaderPresentationResolver.php` | Resolves explicit option, request attribute or configured default | FR-HEADER-002 |
| `Shell/SafeHref.php` | Scheme guard for host-supplied hrefs and image sources | FR-HEADER-003 |
| `Shell/SidebarLayout.php` | Sidebar layout enum: `below-header` or `full-height` | FR-SIDEBARLAYOUT-001 |
| `Shell/SidebarLayoutResolver.php` | Resolves the sidebar layout of the current request | FR-SIDEBARLAYOUT-001 |
| `Shell/SidebarStyle.php` | Shell chrome style enum: `classic` or `inset` (2.52) | FR-SIDEBARSTYLE-001 |
| `Shell/SidebarStyleResolver.php` | Resolves the sidebar style: `sidebar_style` option, request attribute, then config | FR-SIDEBARSTYLE-001 |
| `Resources/views/shell/_brand_parts.html.twig` | Brand made of icon and name for the full-height aside head | FR-SIDEBARLAYOUT-001 |
| `Resources/views/partials/_header_crumbs.html.twig` | Compact breadcrumb partial, truncated levels, current page last | FR-HEADER-001 |
| `Resources/views/partials/_header_context.html.twig` | Compact header filling: page actions trigger and crumbs | FR-HEADER-002 |
| `Resources/views/partials/_page_actions.html.twig` | Page-actions trigger (square popover menu) and its confirm dialogs | FR-HEADERACTIONS-001 |
| `Resources/views/partials/_guest_header.html.twig` | Guest header: brand linked home and a sign-in link | FR-GUESTHEADER-001 |
| `Resources/assets/src/nowo-ui-header-crumbs.ts` | Opt-in collapse of the crumb trail | FR-HEADER-001 |
| `Resources/public/js/nowo-ui-header-crumbs.js` | Build output of `nowo-ui-header-crumbs.ts` | FR-BUILD-001, FR-HEADER-001 |

## Side panel, rail, tree, edge tabs and layout state

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/partials/_side_panel.html.twig` | Complementary panel: inline column or overlay drawer, open state rendered by the server | FR-SIDEPANEL-001 |
| `Resources/views/partials/_side_panel_container.html.twig` | Page content and a side panel as two columns | FR-SIDEPANEL-001 |
| `Resources/views/partials/_side_panel_toggle.html.twig` | Toggle with `aria-controls` / `aria-expanded`, optional count and no-JS link | FR-SIDEPANEL-001 |
| `Resources/assets/src/nowo-ui-side-panel.ts` | Opt-in runtime of the panel | FR-SIDEPANEL-001 |
| `Resources/assets/src/nowo-ui-shortcut-core.ts` | Side-effect-free keyboard shortcut helpers shared by side panel and rail | FR-SIDEPANEL-001, FR-RAIL-001 |
| `Resources/public/js/nowo-ui-side-panel.js` | Build output of `nowo-ui-side-panel.ts` | FR-BUILD-001, FR-SIDEPANEL-001 |
| `Resources/views/partials/_rail_toggle.html.twig` | Collapses an in-page column to a thin rail | FR-RAIL-001 |
| `Resources/assets/src/nowo-ui-rail.ts` | Opt-in runtime of the rail toggle | FR-RAIL-001 |
| `Resources/public/js/nowo-ui-rail.js` | Build output of `nowo-ui-rail.ts` | FR-BUILD-001, FR-RAIL-001 |
| `Resources/assets/src/nowo-ui-layout-state-core.ts` | Remembered layout state helpers (localStorage, no host store) | FR-LAYOUTSTATE-001 |
| `Resources/assets/src/nowo-ui-layout-state.ts` | Optional pre-paint script applying remembered panel and rail state | FR-LAYOUTSTATE-001 |
| `Resources/public/js/nowo-ui-layout-state.js` | Build output of `nowo-ui-layout-state.ts` | FR-BUILD-001, FR-LAYOUTSTATE-001 |
| `Resources/views/partials/_tree.html.twig` | Navigation tree following the WAI-ARIA treeview pattern; plain links without JavaScript | FR-TREE-001 |
| `Resources/views/partials/_tree_items.html.twig` | One recursive level of the tree | FR-TREE-001 |
| `Resources/assets/src/nowo-ui-tree.ts` | Opt-in keyboard and disclosure runtime of the tree | FR-TREE-001 |
| `Resources/public/js/nowo-ui-tree.js` | Build output of `nowo-ui-tree.ts` | FR-BUILD-001, FR-TREE-001 |
| `Resources/views/partials/_edge_tabs_launcher.html.twig` | One launcher tab that folds the page-edge tabs on phones | FR-EDGETAB-002 |
| `Resources/assets/src/nowo-ui-edge-tabs.ts` | Opt-in script that opens and closes the fold | FR-EDGETAB-002 |
| `Resources/public/js/nowo-ui-edge-tabs.js` | Build output of `nowo-ui-edge-tabs.ts` | FR-BUILD-001, FR-EDGETAB-002 |

## Shell focus mode (2.55)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Shell/FocusMode.php` | Region / keep names and shortcut rules validated by `theme.focus_mode` | FR-FOCUSMODE-001 |
| `Resources/views/partials/_focus_mode_toggle.html.twig` | The page's focus mode toggle (renders nothing while focus mode is off in the config) | FR-FOCUSMODE-001 |
| `Resources/views/partials/_focus_mode_bar.html.twig` | Focus bar: exit / fullscreen buttons, one-line breadcrumb, live region | FR-FOCUSMODE-002, FR-FOCUSMODE-004 |
| `Resources/assets/src/nowo-ui-focus-mode-core.ts` | Side-effect-free helpers: regions, keep list, memory, key rules | FR-FOCUSMODE-002, FR-FOCUSMODE-003 |
| `Resources/assets/src/nowo-ui-focus-mode.ts` | Runtime: toggles, F / Escape, fullscreen, live region, Turbo | FR-FOCUSMODE-002, FR-FOCUSMODE-003, FR-FOCUSMODE-004 |
| `Resources/public/js/nowo-ui-focus-mode.js` | Build output of `nowo-ui-focus-mode.ts` | FR-BUILD-001, FR-FOCUSMODE-002 |

## Overlay registry, scroll regions and small helpers

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/assets/src/nowo-ui-overlay.ts` | One owner of "a modal overlay is open" for UiKit, host scripts and other bundles | FR-OVERLAY-001 |
| `Resources/public/js/nowo-ui-overlay.js` | Build output of `nowo-ui-overlay.ts` | FR-BUILD-001, FR-OVERLAY-001 |
| `Resources/assets/src/nowo-ui-scroll-region.ts` | Opt-in focusable scroll regions (table wraps and marked elements) | FR-SCROLLREGION-001 |
| `Resources/public/js/nowo-ui-scroll-region.js` | Build output of `nowo-ui-scroll-region.ts` | FR-BUILD-001, FR-SCROLLREGION-001 |
| `Resources/assets/src/nowo-ui-button-label.ts` | Changes a button's label without touching its icon | FR-JS-001 |
| `Resources/assets/src/nowo-ui-popover-core.ts` | Popover geometry constants and helpers (gap, viewport margin) | FR-MENU-001 |
| `Resources/assets/src/nowo-ui-emoji-table.ts` | Small offline emoji table bundled only into the emoji picker | FR-JS-001 |
| `Resources/public/js/nowo-ui-emoji-picker.js` | Build output of `nowo-ui-emoji-picker.ts` | FR-BUILD-001, FR-JS-001 |
| `Resources/public/js/nowo-ui-combobox.js` | Build output of `nowo-ui-combobox.ts` | FR-BUILD-001, FR-JS-001 |
| `Resources/public/js/nowo-ui-context-menu.js` | Build output of `nowo-ui-context-menu.ts` | FR-BUILD-001, FR-JS-001 |
| `Resources/public/js/nowo-ui-show-more.js` | Build output of `nowo-ui-show-more.ts` | FR-BUILD-001, FR-JS-001 |
| `Resources/public/js/nowo-ui-sortable.js` | Build output of `nowo-ui-sortable.ts` | FR-BUILD-001, FR-JS-001 |

## Default theme stylesheet and token lint

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/public/css/nowo-ui-base.css` | Opt-in default theme (2.51) | FR-BASESTYLE-001 |
| `Lint/TokenLint.php` | Colour-literal lint of bundle CSS, SCSS and Twig (`bin/nowo-ui-token-lint`) | FR-LINT-001 |

## Components and runtimes of 2.56-2.64

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/demo_platform/layout.html.twig` | Demo platform shell | FR-DEMO-002 |
| `Resources/views/demo_platform/_section.html.twig` | Demo platform content block | FR-DEMO-002 |
| `Resources/views/demo_platform/_flashes.html.twig` | Demo platform flashes | FR-DEMO-002 |
| `Resources/public/css/nowo-ui-demo-platform.css` | Demo platform stylesheet (tokens only; 2.74.1: the pagination per-page select gets the platform input border) | FR-DEMO-002 |
| `Lint/DemoStyleLint.php` | Demo style lint (`bin/nowo-ui-demo-style-lint`) | FR-LINT-002 |
| `Resources/public/css/vendor/tom-select.default.min.css` | Vendored Tom Select 2.6.2 default theme | FR-VENDOR-001 |
| `Resources/views/partials/_bulk_bar.html.twig` | Bulk selection bar | FR-BULK-001 |
| `Resources/views/partials/_bulk_toggle.html.twig` | Select-every-row box | FR-BULK-001 |
| `Resources/assets/src/nowo-ui-bulk-select.ts` | Bulk selection runtime | FR-BULK-001 |
| `Resources/views/partials/_dropzone.html.twig` | File dropzone partial | FR-DROPZONE-001 |
| `Resources/assets/src/nowo-ui-dropzone.ts` | Dropzone runtime | FR-DROPZONE-001 |
| `Resources/views/partials/_filter_bar.html.twig` | Filter bar partial | FR-FILTERSHEET-001 |
| `Resources/assets/src/nowo-ui-filter-sheet.ts` | Filter sheet runtime (`filter_bar`, 2.65 `list_filters`) | FR-FILTERSHEET-001, FR-FILTERSHEET-002 |
| `Resources/views/partials/_error_summary.html.twig` | Error summary partial | FR-FORMBUSY-001 |
| `Resources/assets/src/nowo-ui-form-busy.ts` | Form busy and error summary focus runtime | FR-FORMBUSY-001 |
| `Resources/views/partials/_table_view.html.twig` | Table view controls partial | FR-TABLEVIEW-001 |
| `Resources/assets/src/nowo-ui-table-view.ts` | Table view runtime | FR-TABLEVIEW-001 |
| `Resources/views/partials/_tooltip.html.twig` | Tooltip partial | FR-TOOLTIP-001 |
| `Resources/assets/src/nowo-ui-tooltip.ts` | Tooltip runtime | FR-TOOLTIP-001 |
| `Resources/views/partials/_avatar_group.html.twig` | Avatar group | FR-DISPLAY-001 |
| `Resources/views/partials/_chip.html.twig` | Chip | FR-DISPLAY-001 |
| `Resources/views/partials/_code_block.html.twig` | Code block with copy button | FR-DISPLAY-001 |
| `Resources/views/partials/_skeleton.html.twig` | Skeleton loading | FR-DISPLAY-001 |
| `Resources/views/partials/_steps.html.twig` | Steps | FR-DISPLAY-001 |
| `Resources/views/partials/_tab_panels.html.twig` | Client-side tab panels | FR-DISPLAY-001 |
| `Resources/views/partials/_timeline.html.twig` | Timeline | FR-DISPLAY-001 |
| `Resources/views/partials/_form_fieldset.html.twig` | Fieldset row | FR-FIELDSET-001 |
| `Resources/views/partials/_form_actions.html.twig` | Form actions row in the `theme.form_actions_order` order (default primary first; dialog rows Cancel first; theme partial `form_actions`) | FR-FORMACTIONS-001 |
| `Resources/views/partials/_keyset_pager.html.twig` | Keyset pager | FR-PAGER-002 |
| `Resources/views/partials/_option_cards.html.twig` | Option cards | FR-OPTIONCARD-001 |
| `Resources/views/form/nowo_ui_option_card_input.html.twig` | Form theme of one option card | FR-OPTIONCARD-001 |

Built outputs of the six runtimes above (committed, never hand-edited; same rules as the build outputs table):

| Source file | Produced by | Requirement IDs |
| --- | --- | --- |
| `Resources/public/js/nowo-ui-bulk-select.js` | `assets/src/nowo-ui-bulk-select.ts` | FR-BUILD-001, FR-BULK-001 |
| `Resources/public/js/nowo-ui-dropzone.js` | `assets/src/nowo-ui-dropzone.ts` | FR-BUILD-001, FR-DROPZONE-001 |
| `Resources/public/js/nowo-ui-filter-sheet.js` | `assets/src/nowo-ui-filter-sheet.ts` | FR-BUILD-001, FR-FILTERSHEET-001 |
| `Resources/public/js/nowo-ui-form-busy.js` | `assets/src/nowo-ui-form-busy.ts` | FR-BUILD-001, FR-FORMBUSY-001 |
| `Resources/public/js/nowo-ui-table-view.js` | `assets/src/nowo-ui-table-view.ts` | FR-BUILD-001, FR-TABLEVIEW-001 |
| `Resources/public/js/nowo-ui-tooltip.js` | `assets/src/nowo-ui-tooltip.ts` | FR-BUILD-001, FR-TOOLTIP-001 |

## Confirm runtimes, one-time secret and console banner (2.65-2.72)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/assets/src/logger.ts` | Shared bundle logger of the IIFE runtimes (level-gated console output) | FR-JS-001 |
| `Resources/assets/src/script-banner.ts` | REQ-ASSETS-005 "script loaded" console banner, once per runtime, with the build time of `build-info.json` | FR-JS-001, FR-BUILD-001 |
| `Resources/assets/src/nowo-ui-confirm-core.ts` | Confirm dialog core shared by the IIFE runtime and the `confirm-shared` peer (type-to-confirm, shared dialog fill, safe first focus) | FR-JS-001, FR-CONFIRM-001 |
| `Resources/assets/src/nowo-ui-runtime-owner.ts` | Dialog runtime ownership between the IIFE runtimes and the Stimulus peers | FR-JS-001, FR-PEER-001 |
| `Resources/assets/src/nowo-ui-secret-once.ts` | One-time secret runtime (`secret-once`): reveal / hide / copy without duplicating the secret in the DOM | FR-JS-001, FR-CLIPBOARD-001 |
| `Resources/assets/stimulus-peers/confirm_shared_controller.ts` | Stimulus peer `confirm-shared`: the shared confirm `<dialog>` without the IIFE runtime | FR-PEER-001, FR-CONFIRM-001 |
| `Resources/views/partials/_confirm_shared.html.twig` | Shared confirm `<dialog>` of a table's row actions (theme partial `confirm_shared`) | FR-CONFIRM-001, FR-PARTIAL-001 |
| `Resources/views/partials/_secret_once.html.twig` | One-time secret (theme partial `secret_once`): printed once, reveal / copy controls | FR-PARTIAL-001, FR-CLIPBOARD-001 |
| `Resources/public/js/nowo-ui-secret-once.js` | Build output of `assets/src/nowo-ui-secret-once.ts` | FR-BUILD-001, FR-JS-001 |

## Coverage summary

| Category | Mapped |
| --- | ---: |
| Bundle and dependency injection | 4 |
| Configuration enums | 3 |
| Routing and legacy panel paths | 3 |
| Release check (optional) | 3 |
| Appearance SPI (opt-in) | 11 |
| Forms | 16 |
| Listing (customizer, preferences, sorting) | 12 |
| Navigation | 2 |
| Theme | 3 |
| Product tour | 3 |
| Twig extensions and helpers | 8 |
| View models (`View/`) | 34 |
| Test kit for UI bundles (`Test/`) | 6 |
| Symfony config (`Resources/config/`) | 2 |
| Translations (`Resources/translations/`) | 7 |
| Twig views — layouts (`Resources/views/layout/`) | 5 |
| Twig views — page scaffolds (`Resources/views/page/`) | 15 |
| Twig views — app shell (`Resources/views/shell/`) | 4 |
| Twig views — error pages (`Resources/views/error/`) | 2 |
| Twig views — form themes (`Resources/views/form/`) | 3 |
| Twig views — macros and components (`Resources/views/{macros,components}/`) | 4 |
| Twig views — kitchen sink demo (`Resources/views/demo/`) | 4 |
| Twig views — partials: actions, confirm, dialogs, menus (`Resources/views/partials/`) | 18 |
| Twig views — partials: page header, key figures and navigation | 11 |
| Twig views — partials: shell chrome and feedback | 27 |
| Twig views — partials: filters, tables, pagination, segmented | 26 |
| Stylesheets (authored) | 4 |
| TypeScript — kit IIFE sources (`Resources/assets/src/`) | 30 |
| TypeScript — Thinking Orbs (vendored MIT, `Resources/assets/src/orb/`) | 14 |
| TypeScript — morphicons (optional, `Resources/assets/morphicons/`) | 1 |
| Stimulus peers (`Resources/assets/stimulus-peers/`) | 27 |
| Build outputs (generated, committed, never hand-edited) | 19 |
| Brand appearance (spec 220) | 6 |
| Avatar URL policy | 4 |
| Dates for people (2.75) | 3 |
| Error illustration SPI | 2 |
| Remote form dialog (2.28 / 2.33 / 2.73 / 2.74) | 19 |
| Image input | 8 |
| Listing ports (framework-free, 2.49) | 5 |
| Compact header, breadcrumbs, page actions, sidebar layout and style | 15 |
| Side panel, rail, tree, edge tabs and layout state | 19 |
| Shell focus mode (2.55) | 6 |
| Overlay registry, scroll regions and small helpers | 12 |
| Default theme stylesheet and token lint | 2 |
| Components and runtimes of 2.56-2.64 (31 sources) and their 6 built outputs | 37 |
| Confirm runtimes, one-time secret and console banner (2.65-2.72) | 9 |
| **Total** | **478** |

Excluded: co-located Vitest `*.test.ts` (105 files at this audit).

Audit (2026-11-24, 2.74.0 line; previously 2.73.0, v2.72.0, 2026-11-22 v2.64.0):

```bash
git ls-files src | grep -v '\.test\.ts$' | wc -l                      # 478
git ls-files src | grep -v '\.test\.ts$' | grep -c '\.php$'           # 151
git ls-files src | grep -v '\.test\.ts$' | grep -c '\.ts$'            # 104
git ls-files src | grep -c '\.test\.ts$'                              # 105 (excluded)
```
