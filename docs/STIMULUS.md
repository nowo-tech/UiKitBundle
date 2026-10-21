# Stimulus / data-attribute contracts — Nowo UiKit Bundle

UiKit ships optional vanilla **IIFEs** as the default no-build path. Hosts **may** attach Symfony UX Stimulus controllers to the same markup; the kit does **not** require Stimulus.

Optional **Stimulus peer** TypeScript sources live under `src/Resources/assets/stimulus-peers/` for hosts that already compile Stimulus. Prefer IIFEs unless you need Stimulus values/actions.

## Table of contents

- [Shared rules](#shared-rules)
- [Clipboard](#clipboard)
- [Tabs](#tabs)
- [Toast stack](#toast-stack)
- [Confirm dialog](#confirm-dialog)
- [Confirm submit (Stimulus peer only)](#confirm-submit-stimulus-peer-only)
- [Page loader](#page-loader)
- [Release status](#release-status)
- [Auto refresh (Stimulus peer only)](#auto-refresh-stimulus-peer-only)
- [Temporary reveal (Stimulus peer only)](#temporary-reveal-stimulus-peer-only)
- [Collapse panel (Stimulus peer only)](#collapse-panel-stimulus-peer-only)
- [Data table (Stimulus peer only)](#data-table-stimulus-peer-only)
- [Responsive table (Stimulus peer only)](#responsive-table-stimulus-peer-only)
- [Filters collapse (Stimulus peer only)](#filters-collapse-stimulus-peer-only)
- [Disclosure icon (Stimulus peer only)](#disclosure-icon-stimulus-peer-only)
- [Error retry countdown (2.6)](#error-retry-countdown-26)
- [Auto-submit (2.6)](#auto-submit-26)
- [Icon state bridge and morphicons](#icon-state-bridge-and-morphicons)
- [Open modal on load](#open-modal-on-load)
- [Thinking orb](#thinking-orb)
- [Shell / nested nav](#shell--nested-nav)
- [Theme toggle](#theme-toggle)
- [Theme boot](#theme-boot)
- [Locale / kebab](#locale--kebab)
- [Stimulus peers — import examples](#stimulus-peers--import-examples)
- [Beacon attribute migration](#beacon-attribute-migration)

## Shared rules

1. Prefer **`data-nowo-ui-*` / `data-nowo-*`** as the public contract.
2. Kit JS is additive: if a Stimulus controller owns behaviour, omit the matching kit script.
3. Window helpers (when kit JS is loaded) are stable for progressive enhancement.
4. Stimulus peers require `@hotwired/stimulus` in the **host** app (not a kit Composer runtime dependency).

## Clipboard

| Attribute | Role |
|-----------|------|
| `data-nowo-ui-clipboard` | Copy host (click) |
| `data-nowo-ui-clipboard-copy` | Optional nested trigger |
| `data-nowo-ui-clipboard-text` | Plain text to copy |
| `data-nowo-ui-clipboard-url` | Same-origin URL; fetch body then copy |
| `data-nowo-ui-clipboard-label` | Idle button label (flash restore) |
| `data-nowo-ui-clipboard-done-label` | Brief success label |

Script: `js/nowo-ui-clipboard.js` → `window.nowoUiCopyText(text, flashTarget?)`.

Stimulus peer: identifier `clipboard-copy` (`clipboard_copy_controller.ts`).

## Tabs

Two patterns:

1. **Link navigation** — Twig `_tabs.html.twig` (`href` + `aria-current`). No JS.
2. **In-page panels** — IIFE / Stimulus peer below.

| Attribute | Role |
|-----------|------|
| `data-nowo-ui-tabs` | Root |
| `data-nowo-ui-tabs-active` | Active tab id on root |
| `data-nowo-ui-tabs-trigger` | Trigger control |
| `data-nowo-ui-tabs-panel` | Panel |
| `data-nowo-ui-tab-id` | Shared id on trigger + panel |

| `data-nowo-ui-tabs-list` *(2.5)* | Tab list of a progressive root (`hidden` until upgraded) |
| `data-nowo-ui-tabs-upgrade` *(2.5)* | Root: progressive (panels visible without JS); the runtime sets `data-nowo-ui-tabs-ready` |
| `data-nowo-ui-tabs-tab-class` / `data-nowo-ui-tabs-tab-active-class` *(2.5)* | Root: classes swapped on the triggers (slots `tab` / `tab_active`) |

Script: `js/nowo-ui-tabs.js` → `window.nowoUiActivateTab(root, tabId)`.

2.5 (both the script and the peer): each root drives only its own triggers / panels (nested sets); triggers with
`role="tab"` get a roving `tabindex` and `ArrowLeft` / `ArrowRight` (mirrored in RTL; `ArrowUp` / `ArrowDown` with
`aria-orientation="vertical"` in the script), `Home` / `End`; an `invalid` control inside a hidden panel opens it;
`nowo-ui:tabs:change` (bubbling `CustomEvent` on the root, `detail = {tabId, previousTabId, trigger, panel, reason}`,
reason `click` | `keyboard` | `invalid` | `api`) after every change; the state is re-applied after a DOM morph (Live
Component re-render, Turbo morph): the tab the user chose stays open. The `locale_tabs` partial emits this markup —
see [USAGE](USAGE.md#locale-tabs-25).

Stimulus peer: identifier `tabs` (`tabs_controller.ts`; targets `trigger`, `tab`, 2.5: `list`; value `activeTab`;
action `click->tabs#open`). Legacy Beacon `data-tab-id` is still read as a fallback. When the element is also a kit
root (`data-nowo-ui-tabs`) and `nowo-ui-tabs.js` is loaded, the peer hands every value to `window.nowoUiActivateTab`
and leaves keys / `invalid` / morphs to the script (no double handling).

## Toast stack

| Attribute | Role |
|-----------|------|
| `data-nowo-ui-toast-stack` | Root stack |
| `data-nowo-ui-toast` | One toast |
| `data-timeout` | Auto-hide ms (`0` / missing → kit default 5000) |
| `data-nowo-ui-toast-dismiss` | Dismiss control |

Script: `js/nowo-ui-toast.js` → `window.nowoUiDismissToast(el)`.

Stimulus peer: identifier `toast-stack` (`toast_stack_controller.ts`) — adds pause-on-hover/focus. Targets: `toast`. Prefer kit dismiss attr; peer also accepts Stimulus `dismiss` actions.

## Confirm dialog

| Attribute | Role |
|-----------|------|
| `data-nowo-ui-confirm` on `<dialog>` | Confirm root |
| `data-nowo-confirm-open` + `data-nowo-confirm-target` | Open trigger |
| `data-nowo-confirm-close` | Close control |

Script: `js/nowo-ui-confirm.js` → `nowoUiOpenConfirm` / `nowoUiCloseConfirm`.

Destructive **POST + CSRF** stays in the host form inside the dialog (REQ-SEC-005).

Stimulus peer: identifier `confirm-dialog` (`confirm_dialog_controller.ts`) — typed confirmation, portal to `document.body`, optional `nowoUiOpenConfirm`/`nowoUiCloseConfirm` when the IIFE is also loaded. Close also accepts legacy `data-confirm-dialog-close`.

## Confirm submit (Stimulus peer only)

No kit IIFE (uses `window.confirm`).

| Stimulus | Role |
|----------|------|
| `data-controller="confirm-submit"` | Form root **or** a single submit button |
| `data-confirm-submit-message-value` | Confirm message (trimmed; empty → no prompt, event passes) |
| `data-confirm-submit-blocked-value` | When true, always block submit |
| `data-action="submit->confirm-submit#confirm"` | Hook on the form (every submit) |
| `data-action="click->confirm-submit#confirm"` | Hook on one button (e.g. “Reset” next to “Save”; other buttons submit without prompt) |

Peer: `confirm_submit_controller.ts`. Replaces inline `onsubmit="return confirm(…)"` / `onclick=` handlers, which a strict CSP (`script-src` without `'unsafe-inline'`) blocks.

```twig
<form method="post" data-controller="confirm-submit"
      data-confirm-submit-message-value="{{ 'Delete?'|e('html_attr') }}"
      data-action="submit->confirm-submit#confirm">…</form>

<button type="submit" name="reset" value="1" data-controller="confirm-submit"
        data-confirm-submit-message-value="{{ 'Reset?'|e('html_attr') }}"
        data-action="click->confirm-submit#confirm">Reset</button>
```

## Page loader

| Attribute | Role |
|-----------|------|
| `data-nowo-ui-page-loader` | Overlay root |
| `data-nowo-ui-page-loader-show` / `…-hide` | Optional triggers |
| class `is-active` | Visible state |

Twig: `visual: spinner` (default) or `visual: orb` (+ optional `orb_state` / `orb_size` / `orb_theme`), or a minimal
indicator `bar` | `bar_loop` | `bar_spinner` | `corner_spinner` | `dots` | `glow` | `corner_mark` (see
[USAGE.md](USAGE.md#page-loader-and-page-transitions)). `stimulus: true` adds `data-controller="page-loader"` with
timing that matches the visual (`min_visible_ms` / `leave_ms` to override).

Script: `js/nowo-ui-page-loader.js` → `nowoUiShowPageLoader` / `nowoUiHidePageLoader`. With `visual: orb`, also load `js/nowo-ui-orb.js`.

Stimulus peer: identifier `page-loader` (`page_loader_controller.ts`) — min-visible timing, same-origin link interception, leave animation. Resolves `[data-nowo-ui-page-loader]` when no `overlay` target. The leave (`.is-leaving`) ends on the overlay's own `opacity` `transitionend` (children may use transitions freely) or after `leave-ms` + 80 ms.

## Release status

Optional `release_check` feature ([CONFIGURATION.md](CONFIGURATION.md#release-check)).

| Attribute | Role |
|-----------|------|
| `data-nowo-ui-release` on `<dialog>` | Root (also `data-nowo-ui-confirm` for open/close) |
| `data-nowo-ui-release-url` | Same-origin JSON endpoint (`nowo_ui_kit_release_status`) |
| `data-nowo-ui-release-messages` | JSON map of translated strings (`%version%`, `%count%`, `%when%` placeholders) |
| `data-nowo-ui-release-part` | `loading` / `content` / `summary` / `detail` / `meta` / `error` / `compare` / `release` |
| `data-nowo-ui-release-state` | Set by the IIFE: `loading` / `loaded` / `error` |
| `data-nowo-ui-release-update` | On the compare link after load: `true` when an update exists (style hook) |

Script: `js/nowo-ui-release.js` → `window.nowoUiLoadReleaseStatus(dialog)`; loads once on the dialog `toggle` (open) event. Skips dialogs whose `data-controller` includes `release-status`.

Stimulus peer: identifier `release-status` (`release_status_controller.ts`). Put it **on the `<dialog>`** (`confirm-dialog` portals the dialog to `body`) with `data-action="toggle->release-status#onToggle"`. Values `url` / `messages` override the kit attributes; Stimulus targets (`loading`, `summary`, …) override `data-nowo-ui-release-part`. Render the Twig partial with `stimulus: true` to get this wiring.

## Auto refresh (Stimulus peer only)

No kit IIFE. Periodically re-renders server regions of the current page: same-origin `GET` of the current URL
(header `X-Requested-With: auto-refresh`), then every `[data-auto-refresh-region]` **with an id** inside the controller
root is replaced by the element with the same id in the response. Paused while the tab is hidden or after the toggle.

| Stimulus | Role |
|----------|------|
| `data-controller="auto-refresh"` | Root containing the regions |
| `data-auto-refresh-interval-value` | Interval in ms (default `10000`, floor `2000`) |
| `data-auto-refresh-url-value` | Optional URL to fetch (default: current location) |
| `data-auto-refresh-region` + `id` | Region swapped on each tick |
| `data-auto-refresh-target="toggle"` + `data-action="auto-refresh#toggle"` | Pause / resume (`aria-pressed`) |
| `data-auto-refresh-target="status"` | Receives the last refresh time (`toLocaleTimeString()`) |
| event `auto-refresh:refreshed` | Dispatched on the root after a swap (`detail.regions`) |

Peer: `auto_refresh_controller.ts`. Swapped regions are new nodes: Stimulus controllers inside them reconnect.

```twig
<div data-controller="auto-refresh" data-auto-refresh-interval-value="10000">
    <span data-auto-refresh-target="status"></span>
    <button type="button" data-auto-refresh-target="toggle" data-action="auto-refresh#toggle" aria-pressed="true">Auto</button>
    <section id="stats" data-auto-refresh-region>…</section>
</div>
```

## Temporary reveal (Stimulus peer only)

No kit IIFE (1.15). Shows a secret masked, reveals it on demand and hides it again after `duration`.

| Stimulus | Role |
|----------|------|
| `data-controller="temporary-reveal"` | Root |
| `data-temporary-reveal-secret-value` | The secret (removed from the DOM after hide with `clear-on-hide`) |
| `data-temporary-reveal-duration-value` | Auto-hide after ms (default `30000`; `0` = never) |
| `data-temporary-reveal-show-label-value` / `-hide-label-value` | Toggle labels (default `Show` / `Hide`) |
| `data-temporary-reveal-start-revealed-value` | Reveal on connect (e.g. right after creating a key) |
| `data-temporary-reveal-clear-on-hide-value` | After the first hide: drop the secret attribute, empty and disable copy buttons inside the root (`data-clipboard-copy-text-value`, `data-nowo-ui-clipboard-text`), show `cleared-label` |
| `data-temporary-reveal-cleared-label-value` | Text shown once cleared (default `Hidden`) |
| target `display` | Receives the masked / clear value (`data-revealed` / `data-cleared` style hooks) |
| target `toggle` + `data-action="temporary-reveal#toggle"` | Button (`aria-pressed`, label) |

Masking keeps URLs readable (`https://user:••••••••@host/…`); any other secret is replaced by bullets.
Peer: `temporary_reveal_controller.ts` (exports `maskSecret()`).

## Collapse panel (Stimulus peer only)

No kit IIFE (1.15). A collapsible panel whose open state survives navigation: every panel id is stored in one
localStorage JSON map, so the same panel keeps its state across pages. Without a saved state the **server default**
applies — render per-user defaults as a value, no global JS.

| Stimulus | Role |
|----------|------|
| `data-controller="collapse-panel"` | Root (gets `is-collapsed`) |
| `data-collapse-panel-id-value` | Panel id (no id = no persistence) |
| `data-collapse-panel-storage-key-value` | localStorage key (default `nowo-ui.collapse-panels`) |
| `data-collapse-panel-collapsed-value` | Server default when nothing is saved (default `false` = open) |
| `data-collapse-panel-open-icon-value` / `-closed-icon-value` | Icon target text (default `▾` / `▸`) |
| targets `body` (hidden when collapsed), `button` (`aria-expanded`), `icon` | |
| `data-action="collapse-panel#toggle"` | Toggle (persists, dispatches `collapse-panel:toggled` with `detail.open` / `detail.id`) |

Peer: `collapse_panel_controller.ts`. The state is only written when the user toggles, so changing the server default
reaches every panel the user never touched.

```twig
<section data-controller="collapse-panel" data-collapse-panel-id-value="raw"
         data-collapse-panel-collapsed-value="{{ 'raw' in collapsed_panels ? 'true' : 'false' }}">
    <button type="button" data-collapse-panel-target="button" data-action="collapse-panel#toggle">
        <span data-collapse-panel-target="icon"></span> Raw data
    </button>
    <div data-collapse-panel-target="body">…</div>
</section>
```

## Data table (Stimulus peer only)

No kit IIFE (1.15). Responsive [DataTables](https://datatables.net/) layout for server-rendered tables: sorting,
searching and paging stay server-side (`paging`, `searching`, `ordering`, `info`, `lengthChange` off), DataTables
only folds overflowing columns into child rows (`<th data-priority>` sets the order).

**Optional dependencies** (peer dependencies of the kit assets, not installed by the kit): `datatables.net-dt`
(^2.3) and `datatables.net-responsive-dt` (^3.0). The peer imports their JS only; load their stylesheets in the host
(`datatables.net-dt/css/dataTables.dataTables.min.css`, `datatables.net-responsive-dt/css/responsive.dataTables.min.css`)
or style the table yourself. Hosts that do not register this peer never need the packages.

| Stimulus | Role |
|----------|------|
| `data-controller="datatable"` | Stable wrapper **around** the table (DataTables moves the table node) |
| target `table` | The `<table>` |
| `data-datatable-options-value` | JSON merged over the defaults (e.g. `{"responsive":{"details":{"type":"column"}}}`) |
| `data-action="click->datatable#stop"` | On links / buttons inside rows: keep Responsive from toggling the child row |

Mounted on the next animation frame, destroyed (`destroy(false)`) on disconnect. Peer: `datatable_controller.ts`
(exports `DATATABLE_DEFAULT_OPTIONS`).

## Responsive table (Stimulus peer only)

No kit IIFE (1.17). *Scroll* ↔ *Responsive* table with a row detail dialog — markup from
`partials/_responsive_table.html.twig`, `_row_detail_head.html.twig`, `_row_detail_cell.html.twig`
([USAGE.md](USAGE.md#responsive-table)).

| Stimulus | Role |
|----------|------|
| `data-controller="responsive-table"` | Root (gets `data-table-mode`, `data-rt-has-hidden`, `data-rt-overflow`; `data-rt-measuring` during a fit — 1.17.5) |
| `data-responsive-table-mode-value` | `scroll` \| `responsive` \| empty (default: responsive when `phone-query` matches, else scroll) |
| `data-responsive-table-url-value` | Save endpoint (JSON `{"mode": …}`, `X-CSRF-TOKEN` header); empty = client-side only |
| `data-responsive-table-token-value` / `-token-url-value` | CSRF token / GET URL answering `{"token": "…"}` (one refresh on 403) |
| `data-responsive-table-method-value` | HTTP method of the save (default `PUT`) |
| `data-responsive-table-phone-query-value` | Default-responsive media query (default `(max-width: 767px)`) |
| `data-responsive-table-part-selector-value` | Stacked header parts joined in dialog labels (default `[data-cell-part]`) |
| `data-responsive-table-messages-value` | `{toResponsive, toScroll, details, saveError}` (toggle name / title, detail button prefix, save error text — 1.17.1) |
| targets `scroller`, `table`, `toggle` (optional), `dialog`, `dialogTitle`, `dialogBody` | |
| header cells `th[data-column]` (+ `data-required="1"`), row cells `td[data-column]` | Columns (document order = hiding order, from the end) |
| `[data-responsive-detail]` cell with a `button[data-action="responsive-table#open"]` | Row detail button (last cell) |
| `data-action="responsive-table#toggle"` / `#close` | Mode toggle / dialog close |

Events: `responsive-table:changed` (`detail.mode`, optimistic), `responsive-table:save-failed` (`detail.mode`,
`detail.previous`, `detail.message` = `messages.saveError` or empty — 1.17.1; the mode is already switched back).
Hosts show their toast from a listener, or subclass the peer and override the protected `saveFailed(mode, previous)`. Peer: `responsive_table_controller.ts` (exports
`RESPONSIVE_TABLE_PHONE_QUERY`, type `TableMode`). No inline styles (CSP); ResizeObserver + rAF; everything undone on
disconnect (an open dialog is closed and its cells put back).

## Filters collapse (Stimulus peer only)

No kit IIFE (1.17). Markup from `partials/_filters_collapse.html.twig` ([USAGE.md](USAGE.md#foldable-filter-card)).

| Stimulus | Role |
|----------|------|
| `data-controller="filters-collapse"` | Root, first child of the `<form>` (gets `is-collapsed`) |
| `data-filters-collapse-key-value` | Page key stored in the cookie (empty = not remembered) |
| `data-filters-collapse-cookie-value` | Cookie name (default `nowo_ui_filters_collapsed`; comma-separated keys, `path`, one year, `SameSite=Lax`) |
| `data-filters-collapse-cookie-path-value` / `-max-age-value` | Cookie path (default `/`) / lifetime in seconds |
| `data-filters-collapse-messages-value` | `{collapse, expand, none, one, many}` (`many` gets `%count%`) |
| targets `toggle` (`aria-expanded`, label), `summary` (optional "N active filters" line) | |
| `data-action="filters-collapse#toggle"` | Fold / unfold (dispatches `filters-collapse:toggled` with `detail.collapsed`, `detail.key`, `detail.count`) |

Peer: `filters_collapse_controller.ts` (exports `FILTERS_COLLAPSE_COOKIE`, `foldedKeys(cookie?)`,
`activeFilterCount(form)`).

## Disclosure icon (Stimulus peer only)

No kit IIFE (1.17). `data-controller="disclosure-icon"` on a toggle that contains `partials/_disclosure_icon.html.twig`:
on a `<summary>` it follows its `<details>` (`toggle` event), on any other element its `aria-expanded` (e.g. a
`collapse-panel` button). It only reports the state (`disclosure` / `open` | `closed`) to the icon state bridge —
the static chevrons follow the state through CSS without it. Peer: `disclosure_icon_controller.ts`.

## Error retry countdown (2.6)

Self-reload of a 5xx error page on GET (REQ-ERROR-001 item 10). Kit IIFE `js/nowo-ui-error-retry.js` (loaded by
`@NowoUiKit/error/layout.html.twig`):

| Attribute | Role |
| --- | --- |
| `data-nowo-ui-error-retry` | Root (bound once: `data-nowo-ui-error-retry-bound`) |
| `data-nowo-ui-error-retry-url` | URL loaded at zero (default the current location) |
| `data-nowo-ui-error-retry-seconds` | Countdown (default 600) |
| `[data-nowo-ui-error-retry-remaining]` | Child receiving `m:ss` every second — no live region |

`window`-free API for tests: `bindNowoUiErrorRetry(root, navigate)`. Stimulus peer for host markup:
`stimulus-peers/error_retry_countdown_controller.ts`, identifier **`error-retry-countdown`**, values `url`, `seconds`
(default 600), target `remaining` — the controller the apps copied, on the same core (`nowo-ui-error-retry-core.ts`).

## Auto-submit (2.6)

`js/nowo-ui-autosubmit.js` (in the `scripts` partial): a `<select>` / `<input>` with **`data-nowo-ui-autosubmit`**
submits its form on `change` (`requestSubmit()`, else `submit()`), through one delegated document listener — the
compact pagination's per-page select; keep a `<noscript>` submit button for no-JS.

## Icon state bridge and morphicons

`stimulus-peers/icon_state.ts`: `setIconStateHandler(handler | null)`, `syncIconState(kind, host, state, animate)`.
The peers `responsive-table` (`table-mode`: `scroll` | `responsive`), `filters-collapse` (`filters-collapse`:
`expanded` | `collapsed`) and `disclosure-icon` (`disclosure`: `open` | `closed`) report their state through it and
never import an icon library. `assets/morphicons/index.ts` (optional `morphicons` + `lucide`) provides
`installMorphiconPeers()` — call it once in the Stimulus bootstrap, before the controllers connect
([USAGE.md](USAGE.md#morphicons-animated-icons)). Keep the vendor directory layout (the peers and the morphicons
module must resolve the same `icon_state.ts`).

## Open modal on load

Not a Stimulus peer: JSON island `script#nowo-ui-open-modal-on-load` (`_open_modal_on_load.html.twig`) read by
`js/nowo-ui-open-modal.js` → `window.nowoUiOpenModalOnLoad()`, or `openModalOnLoad()` from
`assets/src/nowo-ui-open-modal-core.ts` in bundled hosts. See [USAGE.md](USAGE.md#open-a-modal-on-load-deep-link).

## Thinking orb

| Attribute | Role |
|-----------|------|
| `data-nowo-ui-orb` on `<canvas>` | Mount root |
| `data-state` | `working` / `searching` / `solving` / `listening` / `connecting` / `weaving` / `composing` / `breathing` / `shaping` |
| `data-size` | `64` or `20` |
| `data-theme` | `auto` / `dark` / `light` |
| `data-speed` | Speed multiplier |
| `data-paused` | Freeze current frame |

Script: `js/nowo-ui-orb.js` → `nowoUiMountOrb` / `nowoUiSetOrbState` / `nowoUiBindOrbs`.

Engine adapted from [thinking-orbs](https://github.com/Jakubantalik/thinking-orbs) (MIT); see [THIRD_PARTY.md](THIRD_PARTY.md).

## Shell / nested nav

| Attribute | Role |
|-----------|------|
| `data-nowo-ui-shell` | Shell root |
| `data-nowo-ui-burger` | Aside toggle |
| `data-nowo-ui-aside-backdrop` | Mobile backdrop |
| `data-nowo-ui-nav-group` | Collapsible group |
| `data-nowo-ui-nav-group-toggle` | Group toggle (`aria-expanded`) |
| `data-nowo-ui-width-toggle` | Main full ↔ content width |
| class `is-main-content` | Constrained main max-width |
| `data-nowo-ui-sidebar-rail` *(1.15)* | Opt-in three-mode sidebar on the shell (expanded / rail / hidden) |
| `data-nowo-ui-sidebar-storage` / `-cookie` / `-hidden-value` / `-default` / `-rail-labels` | Rail keys and server default ([USAGE.md](USAGE.md#sidebar-rail-opt-in)) |
| `data-label-expand` / `data-label-rail` / `data-label-hide` on the burger | Next-action labels |
| `data-nowo-ui-sidebar-mode` (shell), `data-nowo-ui-sidebar-state` (burger), `data-nowo-ui-rail-label` / `-initial` (items) | Set by the script |
| classes `is-aside-rail` / `is-aside-collapsed` / `is-aside-open-mobile` | Mode classes |
| event `nowo-ui:sidebar-mode` | `detail.mode`, `detail.mobile` |

Script: `js/nowo-ui-shell.js` → `nowoUiToggleAside` / `nowoUiSetMainWidth` / `nowoUiToggleMainWidth` /
`nowoUiSetSidebarMode` (1.15). Side-effect-free helpers for bundled hosts: `assets/src/nowo-ui-sidebar-core.ts`.

## Theme toggle

| Attribute | Role |
|-----------|------|
| `data-nowo-ui-theme-toggle` | Toggle button |
| `data-nowo-ui-theme-storage` | localStorage key (on the toggle or, since 1.15, on `<html>` — shared with the theme boot) |
| `data-nowo-ui-theme-cookie` *(1.15)* | Optional cookie written with the theme (toggle or `<html>`) |
| `data-aria-to-light` / `data-aria-to-dark` | Accessibility labels |
| `data-nowo-ui-theme-label` | Optional visible label node |

Script: `js/nowo-ui-theme.js` → `nowoUiSetTheme` / `nowoUiToggleTheme`. Sets `data-theme` on `<html>`.

Hosts that sync theme to the server (Beacon `account_theme`) can ignore kit storage and drive `data-theme` from the user preference.

## Theme boot

Blocking `<head>` script (1.15): `partials/_theme_boot.html.twig` (inline + CSP nonce) or `js/nowo-ui-theme-boot.js`.
Reads `data-nowo-ui-pref-{theme,density,motion,font-scale,contrast,sidebar}` (alias `data-user-*`),
`data-nowo-ui-theme-storage` / `data-nowo-ui-theme-cookie` and the optional sidebar keys on `<html>`; writes
`data-theme`, `data-ui-density`, `data-motion`, `data-font-scale`, `data-contrast`. Globals:
`window.nowoUiPrefs`, `window.nowoUiThemeBoot()`. Shared storage helpers: `assets/src/nowo-ui-theme-core.ts`.
Details: [USAGE.md](USAGE.md#pre-paint-theme-boot).

## Locale / kebab

No kit JS required (`<details>` menus).

- Locale: `data-nowo-ui-locale-switcher` — host supplies `locales` with `href` or `form_action` + CSRF.
- Kebab: `data-nowo-ui-kebab` — overflow links only.

## Stimulus peers — import examples

Vendor path after Composer install (adjust for AssetMapper vs Vite):

```text
vendor/nowo-tech/ui-kit-bundle/src/Resources/assets/stimulus-peers/
```

### AssetMapper (`importmap.php`)

```php
// importmap.php (host)
return [
    'clipboard-copy' => [
        'path' => './vendor/nowo-tech/ui-kit-bundle/src/Resources/assets/stimulus-peers/clipboard_copy_controller.ts',
    ],
    'confirm-dialog' => [
        'path' => './vendor/nowo-tech/ui-kit-bundle/src/Resources/assets/stimulus-peers/confirm_dialog_controller.ts',
    ],
    'confirm-submit' => [
        'path' => './vendor/nowo-tech/ui-kit-bundle/src/Resources/assets/stimulus-peers/confirm_submit_controller.ts',
    ],
    'page-loader' => [
        'path' => './vendor/nowo-tech/ui-kit-bundle/src/Resources/assets/stimulus-peers/page_loader_controller.ts',
    ],
    'toast-stack' => [
        'path' => './vendor/nowo-tech/ui-kit-bundle/src/Resources/assets/stimulus-peers/toast_stack_controller.ts',
    ],
    'tabs' => [
        'path' => './vendor/nowo-tech/ui-kit-bundle/src/Resources/assets/stimulus-peers/tabs_controller.ts',
    ],
    'release-status' => [
        'path' => './vendor/nowo-tech/ui-kit-bundle/src/Resources/assets/stimulus-peers/release_status_controller.ts',
    ],
    'auto-refresh' => [
        'path' => './vendor/nowo-tech/ui-kit-bundle/src/Resources/assets/stimulus-peers/auto_refresh_controller.ts',
    ],
    'temporary-reveal' => [
        'path' => './vendor/nowo-tech/ui-kit-bundle/src/Resources/assets/stimulus-peers/temporary_reveal_controller.ts',
    ],
    'collapse-panel' => [
        'path' => './vendor/nowo-tech/ui-kit-bundle/src/Resources/assets/stimulus-peers/collapse_panel_controller.ts',
    ],
    'responsive-table' => [
        'path' => './vendor/nowo-tech/ui-kit-bundle/src/Resources/assets/stimulus-peers/responsive_table_controller.ts',
    ],
    'filters-collapse' => [
        'path' => './vendor/nowo-tech/ui-kit-bundle/src/Resources/assets/stimulus-peers/filters_collapse_controller.ts',
    ],
    'disclosure-icon' => [
        'path' => './vendor/nowo-tech/ui-kit-bundle/src/Resources/assets/stimulus-peers/disclosure_icon_controller.ts',
    ],
    // 'datatable' needs datatables.net-dt + datatables.net-responsive-dt in the host importmap.
];
```

Or lazy-load from `assets/controllers.json` pointing at the same paths.

### Vite / Webpack Encore

```ts
// assets/controllers/clipboard_copy_controller.ts (host re-export)
export { default } from '../../vendor/nowo-tech/ui-kit-bundle/src/Resources/assets/stimulus-peers/clipboard_copy_controller';
```

Repeat for `confirm_dialog`, `confirm_submit`, `page_loader`, `toast_stack`, `tabs`, `release_status`, `auto_refresh`, `temporary_reveal`, `collapse_panel`, `responsive_table`, `filters_collapse`, `disclosure_icon`, `datatable` (the last one needs the optional DataTables packages).

Or register directly in the host Stimulus bootstrap (Vite + pnpm):

```ts
// assets/stimulus_bootstrap.ts (host)
import AutoRefreshController from '../vendor/nowo-tech/ui-kit-bundle/src/Resources/assets/stimulus-peers/auto_refresh_controller';

app.register('auto-refresh', AutoRefreshController);

// 1.17 chrome peers (lazy: createLazyController from vite-plugin-symfony) + optional animated icons.
import { installMorphiconPeers } from '../vendor/nowo-tech/ui-kit-bundle/src/Resources/assets/morphicons';
installMorphiconPeers();
app.register('responsive-table', createLazyController(() => import('../vendor/nowo-tech/ui-kit-bundle/src/Resources/assets/stimulus-peers/responsive_table_controller')));
app.register('filters-collapse', createLazyController(() => import('../vendor/nowo-tech/ui-kit-bundle/src/Resources/assets/stimulus-peers/filters_collapse_controller')));
```

`release_status_controller.ts` imports `../src/nowo-ui-release-core.ts`, so keep the vendor directory layout intact (do not copy the peer file alone).

Peers are covered by Vitest (`stimulus-peers/*.test.ts`) and type-checked with `pnpm run typecheck`.

When using a Stimulus peer, **do not** also load the matching IIFE for the same markup (duplicate listeners / double dismiss).

## Beacon attribute migration

| Beacon (legacy) | Kit contract |
|-----------------|--------------|
| `data-controller="clipboard-copy"` + `data-clipboard-copy-*-value` | Keep Stimulus peer **or** switch to `data-nowo-ui-clipboard*` + IIFE |
| `data-confirm-dialog-close` | Prefer `data-nowo-confirm-close` (peer accepts both) |
| `data-toast-stack-target="toast"` | Prefer `data-nowo-ui-toast` + kit stack attrs; peer still uses Stimulus targets |
| `data-tab-id` | Prefer `data-nowo-ui-tab-id` (peer/IIFE accept legacy on Stimulus path) |
| `data-controller="page-loader"` without kit attr | Add `data-nowo-ui-page-loader` on the overlay (peer already falls back to it) |

`password-confirm-mirror` and other product-only controllers stay in the host app.
