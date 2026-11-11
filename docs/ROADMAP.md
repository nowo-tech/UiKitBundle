# Roadmap — Nowo UiKit Bundle

English product roadmap for extracting shared admin UI from **Nowo Twig bundles** and the **symfony-beacon** host app into this kit.

## Table of contents

- [Sources observed](#sources-observed)
- [Already in UiKit](#already-in-uikit)
- [Gap analysis](#gap-analysis)
  - [High frequency in bundles](#high-frequency-in-bundles)
  - [Prominent in Beacon admin](#prominent-in-beacon-admin)
- [Planned phases](#planned-phases)
  - [Phase A — Feedback parity with Beacon ✅](#phase-a--feedback-parity-with-beacon-)
  - [Phase B — Layout building blocks ✅](#phase-b--layout-building-blocks-)
  - [Phase C — Host chrome helpers ✅](#phase-c--host-chrome-helpers-)
  - [Phase D — Consolidation ✅ (kit-side)](#phase-d--consolidation--kit-side)
- [Ideas from an admin-template review (2026-11)](#ideas-from-an-admin-template-review-2026-11)
  - [Layout and chrome](#layout-and-chrome)
  - [Feedback and overlays](#feedback-and-overlays)
  - [Content components](#content-components)
  - [Tables](#tables)
  - [Considered and left out](#considered-and-left-out)
  - [Routed to other bundles](#routed-to-other-bundles)
- [Out of scope](#out-of-scope)
- [Adoption notes](#adoption-notes)

## Sources observed

| Source | What we scanned |
|--------|-----------------|
| **symfony-beacon** | `templates/` (app shell, `_user_menu`, `_toasts`, `_tabs`, pagination, kit layouts), Stimulus controllers, `_components.scss` |
| **Nowo bundles** (`**/src/**/*.twig`) | Admin UIs: DashboardMenu, BreadcrumbKit, HttpLog, CookieConsent, Workflow, Vault, TaskBoard, Wiki, ApiStudio, UptimeMonitor, SiteBackup, Performance, FormKit, AuthKit, … |

Goal: one place to restyle chrome so Beacon and feature bundles stop shipping parallel `_ui_macros` / `nowo-ui.css` copies and ad-hoc partials.

## Already in UiKit

Shipped through **v1.16.x** (phases A–D implemented in the kit; v1.5–1.8 add `row_actions_display`, clipboard IIFE, in-page tabs IIFE, Stimulus peers; 1.12–1.16 add filter bar / data table chrome, theming tokens, theme boot, sidebar rail, more peers and the appearance SPI — see [CHANGELOG.md](CHANGELOG.md)):

| Area | Surface |
|------|---------|
| Class macros | `btn`, `toolbar`, `page_header`, `search_*`, `list*`, `table*`, `row_actions`, `badge` (+ variants), `flash`, `empty`, `tabs`, modal/confirm attrs, shell/header/aside/avatar/user_menu/burger/footer, `card*`, `filters`, `progress*`, `spinner` |
| Partials | pagination, empty, flashes, **toasts**, row actions, page header, tabs, **filters**, **card**, modal shell, **confirm**, **page loader** (`spinner`/`orb`), **thinking orb**, **brand**, **theme toggle**, **width toggle**, **locale switcher**, **kebab**, icon, burger, avatar, aside (**nested groups**), user menu, footer, composed `_shell` |
| Assets | `nowo-ui.css` (+ dark tokens); Vite/pnpm IIFEs: modal, shell (+ opt-in **sidebar rail**, 1.15), **toast**, **confirm**, **page-loader**, **theme**, **theme-boot** (pre-paint, 1.15), **orb**, **clipboard**, **tabs**; optional Stimulus peers (incl. `temporary-reveal`, `collapse-panel`, `datatable`, 1.15) |
| Config | `css_framework`, `icon_set`, `row_actions_display` (`icon` / `text` / `icon_text`), opt-in `appearance` SPI (1.16: presets, token emitter + aliases, Twig functions, optional form; storage stays in the host) |
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
| Site appearance (palette presets, corners, borders) | ✅ SPI (1.16; host keeps entity + admin controller) |
| Pre-paint theme boot / sidebar rail | ✅ (1.15) |
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

## Ideas from an admin-template review (2026-11)

In November 2026 we reviewed the public demo of a commercial admin template. We took feature ideas only: no code,
CSS, markup, icons, images or asset names. The kit already covers much of what the demo shows: shell, sidebar rail,
theme and width toggles, toasts, confirm and remote dialogs, side panel (drawer), tabs, segmented control,
pagination, filters, responsive and sortable tables, listing customizer (column visibility), stat row, avatar,
tree, sortable, guided tour, clipboard, page loader, error pages, wizard and settings scaffolds, date range, emoji
picker and image crop. This list keeps the real gaps. Form ideas went to the FormKit roadmap; ideas that belong to
other bundles are routed at the end.

**Rules for every item.** It works without JavaScript (the script only enhances). It is CSP-safe: no inline script,
no inline style, no `on*` attributes. It meets WCAG 2.2 AA (keyboard, `aria-*`, visible focus, reduced motion). It
uses `--nowo-ui-*` tokens, light and dark. Its strings ship in the 7 locales (en, es, fr, de, it, pt, nl). It is
FrankenPHP worker-safe: no per-request state in services. It is opt-in when it changes existing markup, and it
keeps the theme slots and `css_framework` mapping.

**Priority.** P1 = next minor releases. P2 = when a host asks or a related item is open. P3 = idea, no plan yet.
**Size.** S = a few days. M = one to two weeks. L = more.

### Layout and chrome

| Pri | Idea | Why for a Symfony host | Size | Fit | Constraints |
|-----|------|------------------------|------|-----|-------------|
| P1 | **RTL readiness** — logical properties everywhere, `dir` from the locale, mirrored directional icons, an RTL pass in the kitchen sink. | Hosts that add Arabic or Hebrew get a broken shell today; the CSS uses logical properties only in part. | M | UiKit CSS + layouts | No new locale needed in the kit; a test renders the kitchen sink with `dir="rtl"`. |
| P1 | **Top navigation layout** — a shell variant with the main menu in the header (dropdown groups), folding into the aside burger on small screens. | Apps with few sections prefer a full-width content area. | L | UiKit shell; items from `AreaNavProviderInterface` or DashboardMenuBundle | Menu semantics as in 2.28; groups open on click and keyboard, not on hover only; works as plain links without JS. |
| P2 | **Rail expands on hover** — the collapsed rail opens over the content on hover or focus, without changing the saved state. | Fast access to labels without unpinning the rail. | S | UiKit sidebar rail | Opt-in; delay to avoid flicker; Escape closes; no animation with `prefers-reduced-motion`. |
| P2 | **Dark sidebar on a light page** — an aside token scope that keeps the sidebar dark while the content is light. | A common brand choice; today the aside follows the page theme. | S | UiKit tokens + appearance SPI | Contrast checked for both scopes; theme boot sets it before paint. |
| P2 | **Header modes** — sticky, static, or hidden on scroll down and shown on scroll up. | More room on small screens and long lists. | S | UiKit shell option | Never hides while focus is inside the header; static is the no-JS result. |
| P2 | **Layout customizer** — a side panel where a user picks theme, density, sidebar mode and content width; the host stores the choice. | Users tune the admin without an admin page; hosts keep one storage. | M | UiKit side panel + appearance SPI | Each control works as a form post; storage stays in the host (cookie or provider). |
| P3 | **Cover auth layout** — a guest layout split in two: brand or illustration panel and the form. | Login and sign-up pages that carry the brand. | S | UiKit guest layout; pages stay in AuthKitBundle | Illustration slot is host content with `alt`; the panel hides on small screens. |

### Feedback and overlays

| Pri | Idea | Why for a Symfony host | Size | Fit | Constraints |
|-----|------|------------------------|------|-----|-------------|
| P1 | **Alert (callout) partial** — tone, optional title and icon, soft or outline style, optional dismiss. | Page-level notices (beta, quota, missing setup) today reuse flashes or ad-hoc markup. | S | UiKit partial + macro | Dismiss needs JS and hides the button without it; `role="alert"` only for urgent tones; icon is decorative. |
| P1 | **Tooltip partial** — short text on hover and focus, built on the popover core. | Icon-only buttons and truncated cells need a visible name. | S | UiKit partial + `nowo-ui-popover` | `aria-describedby`; stays while hovered; Escape closes (WCAG 1.4.13); never the only place for key information. |
| P2 | **Busy overlay for a region** — cover a card, section or form with a spinner and a message while it works. | Fragment reloads and long submits; the page loader covers the whole page only. | S | UiKit partial + overlay JS | `aria-busy="true"` on the region; message in a live region; inputs disabled, not only covered. |
| P2 | **Toast placements and actions** — top or bottom, start or end, and one action button (for example "Undo"). | Undo after delete and links to the new record. | S | UiKit toasts | Toasts with an action do not auto-hide; the action is reachable by keyboard. |
| P2 | **Idle timeout warning** — a dialog before the session expires with a countdown and "stay signed in". | Avoid lost work in long forms. | M | UiKit dialog + peer; keep-alive route in the host or AuthKitBundle | Countdown announced at set steps, not every second; works with the host session lifetime; no timer when the tab is hidden. |

### Content components

| Pri | Idea | Why for a Symfony host | Size | Fit | Constraints |
|-----|------|------------------------|------|-----|-------------|
| P1 | **Statistic card** — extend `View\Stat` with a delta (up or down, percent or absolute), a period hint and an optional icon. | Dashboards and list headers (users, revenue, errors) show trends, not just values. | S–M | UiKit `_stat_row` + `View\Stat` | Direction in text and icon, not colour only; the host computes the numbers. |
| P1 | **Bulk selection in lists** — a header checkbox, row checkboxes and a bulk-actions bar with the selected count. | Delete, archive or export many rows at once in every admin list. | M | UiKit listing / table + peer | Works as a plain form with checkboxes and a submit; "select all on every page" is an explicit second step; count in a live region. |
| P2 | **Activity timeline** — a vertical list of events: time, title, text, tone or icon, and a slot for attachments or avatars. | Audit trails, workflow history, ticket logs. | M | UiKit partial; data from AuditKitBundle, HttpLogBundle, WorkflowBundle | An ordered list with `<time datetime>`; relative time through RelativeTimeBundle when installed. |
| P2 | **Avatar group and status** — stacked avatars with a "+N" overflow, and an optional presence dot. | Team members on cards, assignees, who is online. | S | UiKit `_avatar` | The group has one accessible name ("5 members"); status in text, not only the dot; presence data from UserKitBundle. |
| P2 | **Card actions** — collapse, reload the body, full screen, and remove, from the card header. | Dashboard widgets that refresh or expand. | M | UiKit card + collapse panel + fragment reload | Full screen uses a dialog; Escape exits; reload keeps focus; remove asks the host to persist. |
| P2 | **Accordion** — a group of `<details>` with an exclusive mode. | FAQ blocks and long settings. | S | UiKit partial | Native `<details name>`; no JS; summary is a heading level the host chooses. |
| P2 | **Dropdown menu** — a generic menu button with headers, dividers, icons and a split-button form. | Actions beyond the kebab (export as, create from template). | S–M | UiKit menu (2.28 semantics) | Keyboard model of a menu button; items are links or forms, never `javascript:`. |
| P2 | **Text divider** — a rule with a short label ("or", a date). | Between sign-in options and in chat-like lists. | S | UiKit macro | `role="separator"` with the label. |
| P3 | **Rating display** — read-only stars for a score. | Reviews and feedback lists. | S | UiKit macro; the input lives in FormKit | Text "4 of 5" for screen readers. |
| P3 | **Sparkline** — a tiny server-rendered SVG line for a series of numbers. | Trends inside stat cards and table cells without a chart library. | M | UiKit Twig function | No JS; SVG with `role="img"` and a text summary; full charts stay out of scope. |
| P3 | **Keyboard hint** — a `<kbd>` style for shortcuts. | Shortcut help next to actions and in the command palette. | S | UiKit macro | Text stays readable in high-contrast mode. |

### Tables

| Pri | Idea | Why for a Symfony host | Size | Fit | Constraints |
|-----|------|------------------------|------|-----|-------------|
| P2 | **Sticky header and first column** — keep the header row and the first column visible while scrolling. | Wide reports and long lists. | S | UiKit responsive table CSS | CSS `position: sticky` only; focusable scroll region stays (2.28). |
| P2 | **Export action slot** — a standard place in the listing toolbar for "Export CSV / XLSX". | Every admin list gets the same export entry; the host streams the file. | S | UiKit listing toolbar | A link or form to a host route with the current filters; no client-side export. |
| P2 | **Row grouping** — group header rows by a field value, optional collapse. | Orders by status, entries by day. | M | UiKit table partial | Group rows use `<th scope="rowgroup">`; collapse is `<details>`-like with a button. |
| P3 | **Totals row** — a footer row with sums or counts. | Money and time lists. | S | UiKit table partial | `<tfoot>`; values computed by the host. |
| P3 | **Inline cell edit** — edit one value in place through a form fragment. | Quick fixes without opening the record. | L | UiKit + FormKit + remote form dialog | Falls back to the edit page; one cell at a time; Escape cancels. |

### Considered and left out

| Idea seen | Why not in the kit |
|-----------|--------------------|
| Carousel | Low value in admin screens and hard to make accessible; hosts that need one use a dedicated library. |
| Charts and maps | Stay out of scope (see below); sparkline is the only small exception above. |
| Media player | The native `<video>` / `<audio>` elements are enough. |
| Pricing tables, landing, checkout and payment pages | Product and marketing pages belong to the host. |
| Several icon fonts | The kit keeps `icon_set` and UX Icons; no bundled icon font. |

### Routed to other bundles

| Idea seen | Owner |
|-----------|-------|
| Menu data, multi-level menus, horizontal menu items | DashboardMenuBundle |
| Breadcrumbs data | BreadcrumbKitBundle (the kit keeps the `_breadcrumbs` partial) |
| Global search, Ctrl/⌘ K | SearchKitBundle |
| Kanban board | TaskBoardBundle |
| Chat app | ChatKitBundle |
| Email templates and sending | MailKitBundle |
| Login, register, verify email, reset password, two-step pages | AuthKitBundle (+ OtpInputBundle, QrCodeBundle for the authenticator QR) |
| Roles list and permission matrix | RbacKitBundle (matrix field in FormKit) |
| User list and view, devices and sessions, presence | UserKitBundle |
| FAQ and help center | ManualKitBundle |
| Maintenance and coming-soon pages | MaintenanceModeBundle |
| Relative timestamps ("12 min ago") | RelativeTimeBundle |
| Time tracking calendar views | TimeTrackBundle; a general event calendar has no owner yet (candidate for a new bundle) |

## Out of scope

| Keep elsewhere | Why |
|----------------|-----|
| DashboardMenu / BreadcrumbKit domain CRUD | Feature bundles own data + routes |
| Form themes / widgets | FormKit, OtpInput, TagInput, PasswordToggle, … |
| Auth login pages | AuthKit |
| Charts, Mercure realtime, product tour, PWA install | Beacon product features |
| Full design-system Storybook | Optional later; kitchen sink remains the demo |
| Appearance storage (entity / settings row) and its admin controller | Host app — the kit only defines `AppearanceProviderInterface` (1.16) |
| Config export / import portability | No kit fits yet (candidate for a dedicated bundle) |

## Adoption notes

- Prefer **`_shell` + tokens** over forking page Twig in feature bundles ([REQ-TWIG-001 override vs upgrade](USAGE.md#override-vs-upgrade)).
- Feature bundles may pass their own `css_framework` into macros until configs are unified on `nowo_ui_kit.*`.
- New kit surfaces always emit **`nowo-ui-*`** semantic classes; framework extras stay optional via `css_framework`.

Status legend: ✅ done in kit · ⏳ consumer migration · ❌ out of scope / other package.
