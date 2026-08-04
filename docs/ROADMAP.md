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

Shipped through **v1.4.x** (phases A–D implemented in the kit):

| Area | Surface |
|------|---------|
| Class macros | `btn`, `toolbar`, `page_header`, `search_*`, `list*`, `table*`, `row_actions`, `badge` (+ variants), `flash`, `empty`, `tabs`, modal/confirm attrs, shell/header/aside/avatar/user_menu/burger/footer, `card*`, `filters`, `progress*`, `spinner` |
| Partials | pagination, empty, flashes, **toasts**, row actions, page header, tabs, **filters**, **card**, modal shell, **confirm**, **page loader** (`spinner`/`orb`), **thinking orb**, **brand**, **theme toggle**, **width toggle**, **locale switcher**, **kebab**, icon, burger, avatar, aside (**nested groups**), user menu, footer, composed `_shell` |
| Assets | `nowo-ui.css` (+ dark tokens); Vite/pnpm IIFEs: modal, shell, **toast**, **confirm**, **page-loader**, **theme**, **orb** |
| Config | `css_framework`, `icon_set` |
| Docs | [ADOPTION.md](ADOPTION.md), [STIMULUS.md](STIMULUS.md), [THIRD_PARTY.md](THIRD_PARTY.md) |

## Gap analysis

### High frequency in bundles

| Element | Kit status |
|---------|------------|
| Modal shells / confirm UX | Modal shell ✅ · Confirm dialog ✅ |
| Badges | Macro + variants ✅ |
| Flash / alert / toasts | Inline flashes ✅ · Toasts ✅ |
| Cards / panels | ✅ |
| Toolbar / page header | ✅ |
| Pagination | ✅ |
| Filters bar | ✅ |
| Tabs | ✅ |
| `_ui_macros` copies in other repos | Migrate per [ADOPTION.md](ADOPTION.md) ⏳ (consumer work) |

### Prominent in Beacon admin

| Element | Kit status |
|---------|------------|
| Toast stack | ✅ |
| Page loader overlay | ✅ (host may inject brand orb into slot) |
| Confirm `<dialog>` | ✅ |
| Theme toggle | ✅ |
| Locale switcher | ✅ (generic; host supplies URLs) |
| Nested sidebar nav | ✅ |
| Brand mark | ✅ |
| Kebab / overflow | ✅ |
| Progress + spinner | ✅ |
| Breadcrumbs / combobox / charts | ❌ (BreadcrumbKit / FormKit / product) |

## Planned phases

### Phase A — Feedback parity with Beacon ✅

1. **Toasts** — `_toasts.html.twig` + CSS + `nowo-ui-toast.js`
2. **Confirm dialog** — `_confirm.html.twig` + `nowo-ui-confirm.js`
3. **Page loader** — `_page_loader.html.twig` + `nowo-ui-page-loader.js`

### Phase B — Layout building blocks ✅

1. **Cards / panels** — macros + `_card.html.twig`
2. **Filter bar** — `_filters.html.twig`
3. **Nested aside nav** — `children` + shell JS toggle
4. **Brand mark** — `_brand.html.twig`

### Phase C — Host chrome helpers ✅

1. **Theme toggle** + **locale switcher**
2. **Kebab / overflow menu**
3. **Progress** + **spinner** macros
4. Stimulus peer contracts — [STIMULUS.md](STIMULUS.md)

### Phase D — Consolidation ✅ (kit-side)

1. Adoption guide for replacing per-bundle `_ui_macros` — [ADOPTION.md](ADOPTION.md)
2. Beacon / feature-bundle migration remains **consumer work** (depend on UiKit, remap tokens, swap partials)
3. Spec checklist: UiKit remains the canonical REQ-UI-001 provider

## Out of scope

| Keep elsewhere | Why |
|----------------|-----|
| DashboardMenu / BreadcrumbKit domain CRUD | Feature bundles own data + routes |
| Form themes / widgets | FormKit, OtpInput, TagInput, PasswordToggle, … |
| Auth login pages | AuthKit |
| Charts, Mercure realtime, product tour, PWA install | Beacon product features |
| Full design-system Storybook | Optional later; kitchen sink remains the demo |

## Adoption notes

- Prefer **`_shell` + tokens** over forking page Twig in feature bundles ([REQ-TWIG-001 override vs upgrade](USAGE.md#override-vs-upgrade)).
- Feature bundles may pass their own `css_framework` into macros until configs are unified on `nowo_ui_kit.*`.
- New kit surfaces always emit **`nowo-ui-*`** semantic classes; framework extras stay optional via `css_framework`.

Status legend: ✅ done in kit · ⏳ consumer migration · ❌ out of scope / other package.
