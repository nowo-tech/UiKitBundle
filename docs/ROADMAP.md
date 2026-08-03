# Roadmap — Nowo UiKit Bundle

English product roadmap for extracting shared admin UI from **Nowo Twig bundles** and the **symfony-beacon** host app into this kit.

## Table of contents

- [Sources observed](#sources-observed)
- [Already in UiKit](#already-in-uikit)
- [Gap analysis](#gap-analysis)
- [Planned phases](#planned-phases)
- [Out of scope](#out-of-scope)
- [Adoption notes](#adoption-notes)

## Sources observed

| Source | What we scanned |
|--------|-----------------|
| **symfony-beacon** | `templates/` (app shell, `_user_menu`, `_toasts`, `_tabs`, pagination, kit layouts), Stimulus controllers, `_components.scss` |
| **Nowo bundles** (`**/src/**/*.twig`) | Admin UIs: DashboardMenu, BreadcrumbKit, HttpLog, CookieConsent, Workflow, Vault, TaskBoard, Wiki, ApiStudio, UptimeMonitor, SiteBackup, Performance, FormKit, AuthKit, … |

Goal: one place to restyle chrome so Beacon and feature bundles stop shipping parallel `_ui_macros` / `nowo-ui.css` copies and ad-hoc partials.

## Already in UiKit

Shipped through **v1.2.x**:

| Area | Surface |
|------|---------|
| Class macros | `btn`, `toolbar`, `page_header`, `search_*`, `list*`, `table*`, `row_actions`, `badge`, `flash`, `empty`, `tabs`, modal attrs, shell/header/aside/avatar/user_menu/burger/footer |
| Partials | pagination, empty, flashes, row actions, page header, tabs, modal shell, icon, burger, avatar, aside, user menu, footer, composed `_shell` |
| Assets | `nowo-ui.css`; **Vite/pnpm** builds `nowo-ui-modal.js` + `nowo-ui-shell.js` from TypeScript sources |
| Config | `css_framework`, `icon_set` |

## Gap analysis

### High frequency in bundles (duplicate today)

| Element | Seen in | Kit status |
|---------|---------|------------|
| Modal shells / confirm UX | TaskBoard, ApiStudio, Wiki, FormKit, HttpLog, SiteBackup, … | Modal shell ✅ · **confirm dialog** ❌ |
| Badges | CookieConsent, HttpLog, Uptime, Vault, Wiki, Workflow, … | Macro ✅ · richer variants ⏳ |
| Flash / alert | Vault, TaskBoard, SiteBackup, MarketingKit, HttpLog, … | Inline flashes ✅ · **toasts** ❌ |
| Cards / panels | MaintenanceMode, SiteBackup, Yopass, Performance, Vault, Beacon `.panel` | ❌ |
| Toolbar / page header | DashboardMenu, BreadcrumbKit, HttpLog, Workflow, … | ✅ |
| Pagination | ContactForm, CookieConsent, Workflow, Performance, Beacon shared | ✅ |
| Filters bar | HttpLog `_filter`, Performance, TranslationYamlTools, Beacon issue-filters | ❌ |
| Tabs | CookieConsent, ContactForm, ApiStudio, AuthKit, Beacon `_tabs` | ✅ |
| `_ui_macros` copies | DashboardMenu, BreadcrumbKit, Uptime, Workflow, Yopass | Migrate → UiKit ⏳ |

### Prominent in Beacon admin (not yet in kit)

| Element | Beacon reference | Kit status |
|---------|------------------|------------|
| Toast stack (flash → fixed toasts) | `_toasts.html.twig` + `toast-stack` | ❌ |
| Page loader overlay | `_page_loader.html.twig` + `page-loader` | ❌ |
| Confirm `<dialog>` | `confirm-dialog` Stimulus | ❌ |
| Theme toggle | `_theme_toggle.html.twig` | ❌ |
| Locale switcher | `_locale_switcher.html.twig` | ❌ |
| Content-width / density prefs chrome | `_content_width_toggle.html.twig`, `data-user-*` | ❌ |
| Brand mark | `_brand_mark.html.twig` | ❌ |
| Nested sidebar nav / collapse | `_section_nav.html.twig`, `menu-nested-collapse` | Aside flat ✅ · nested ❌ |
| Breadcrumbs presentation | `breadcrumb_render()` / BreadcrumbKit host | ❌ (lives in BreadcrumbKit) |
| Combobox / Tom Select chrome | `combobox` Stimulus | ❌ (FormKit / host) |
| Audit meta / timeline snippets | `shared/_audit_meta`, `admin/_audit_timeline` | ❌ |
| Collapse panels | `issue/_collapse_section` | ❌ |
| Empty states (panel-style) | dashboards | Basic `_empty` ✅ |

### Niche but reusable (1–2 bundles)

| Element | Where | Priority |
|---------|-------|----------|
| Sidebar tree | ApiStudio, UptimeMonitor | Medium |
| Kebab / overflow menu | ApiStudio | Medium |
| Progress bar | SiteBackup, TaskBoard | Medium |
| Spinner / loading | IconSelector, SiteBackup, Uptime | Medium |
| Chips / tags display | TaskBoard | Low (TagInput owns input) |
| Timeline | TaskBoard, Audit | Low–medium |

## Planned phases

### Phase A — Feedback parity with Beacon (next)

1. **Toasts** — `_toasts.html.twig` + `nowo-ui-toast*` CSS + small JS/Stimulus-friendly hooks (map Symfony flashes → stack; dismiss / auto-hide). Align with Beacon `_toasts` contract where practical.
2. **Confirm dialog** — accessible `<dialog>` / `nowo-ui-confirm` for delete/destructive actions (POST + CSRF stays in the host form).
3. **Page loader** — optional overlay partial + data attributes (Beacon `page-loader` behaviour, kit-owned markup/classes).

### Phase B — Layout building blocks

1. **Cards / panels** — `nowo-ui-card` (+ header/body/footer slots); replace ad-hoc `.panel` / Bootstrap cards in demos and migrate HttpLog/SiteBackup/Vault surfaces gradually.
2. **Filter bar** — `_filters.html.twig` (search + actions row) extracted from HttpLog / Performance patterns.
3. **Nested aside nav** — optional tree / collapsible groups (Beacon section nav + ApiStudio/Uptime trees), without owning DashboardMenu data.
4. **Brand mark** — small `_brand.html.twig` (text + optional SVG/img).

### Phase C — Host chrome helpers (Beacon-first)

1. **Theme toggle** + **locale switcher** partials (host supplies routes/URLs).
2. **Kebab / overflow menu** partial.
3. **Progress** + **spinner** primitives.
4. Document Stimulus optional peers (`data-controller` contracts) without hard-requiring Symfony UX in the kit.

### Phase D — Consolidation

1. Replace per-bundle `_ui_macros.html.twig` / divergent `nowo-ui.css` with `suggest`/`require` on UiKit (DashboardMenu, BreadcrumbKit, CookieConsent, RoutingKit, …).
2. Beacon: point kit admin layouts at UiKit partials; thin local CSS tokens only (`--nowo-ui-*` remap).
3. Spec checklist: keep UiKit as the **canonical** REQ-UI-001 class/macro provider.

## Out of scope

| Keep elsewhere | Why |
|----------------|-----|
| DashboardMenu / BreadcrumbKit domain CRUD | Feature bundles own data + routes |
| Form themes / widgets | FormKit, OtpInput, TagInput, PasswordToggle, … |
| Auth login pages | AuthKit |
| Charts, Mercure realtime, product tour, PWA install | Beacon product features |
| Full design-system Storybook | Optional later; kitchen sink remains the demo |

## Adoption notes

- Prefer **`_shell` + tokens** over forking page Twig in feature bundles ([REQ-TWIG-001 override vs upgrade](../README.md)).
- Feature bundles may pass their own `css_framework` into macros until configs are unified on `nowo_ui_kit.*`.
- New kit surfaces should always emit **`nowo-ui-*`** semantic classes; framework extras stay optional via `css_framework`.

Status legend in tables: ✅ done · ⏳ partial / migrate · ❌ not in kit yet.
