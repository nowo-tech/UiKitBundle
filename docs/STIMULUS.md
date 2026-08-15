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
- [Thinking orb](#thinking-orb)
- [Shell / nested nav](#shell--nested-nav)
- [Theme toggle](#theme-toggle)
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

Script: `js/nowo-ui-tabs.js` → `window.nowoUiActivateTab(root, tabId)`.

Stimulus peer: identifier `tabs` (`tabs_controller.ts`). Legacy Beacon `data-tab-id` is still read as a fallback.

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
| `data-controller="confirm-submit"` | Form root |
| `data-confirm-submit-message-value` | Confirm message |
| `data-confirm-submit-blocked-value` | When true, always block submit |
| `data-action="submit->confirm-submit#confirm"` | Hook |

Peer: `confirm_submit_controller.ts`.

## Page loader

| Attribute | Role |
|-----------|------|
| `data-nowo-ui-page-loader` | Overlay root |
| `data-nowo-ui-page-loader-show` / `…-hide` | Optional triggers |
| class `is-active` | Visible state |

Twig: `visual: spinner` (default) or `visual: orb` (+ optional `orb_state` / `orb_size` / `orb_theme`).

Script: `js/nowo-ui-page-loader.js` → `nowoUiShowPageLoader` / `nowoUiHidePageLoader`. With `visual: orb`, also load `js/nowo-ui-orb.js`.

Stimulus peer: identifier `page-loader` (`page_loader_controller.ts`) — min-visible timing, same-origin link interception, leave animation. Resolves `[data-nowo-ui-page-loader]` when no `overlay` target.

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

Script: `js/nowo-ui-shell.js` → `nowoUiToggleAside` / `nowoUiSetMainWidth` / `nowoUiToggleMainWidth`.

## Theme toggle

| Attribute | Role |
|-----------|------|
| `data-nowo-ui-theme-toggle` | Toggle button |
| `data-nowo-ui-theme-storage` | localStorage key |
| `data-aria-to-light` / `data-aria-to-dark` | Accessibility labels |
| `data-nowo-ui-theme-label` | Optional visible label node |

Script: `js/nowo-ui-theme.js` → `nowoUiSetTheme` / `nowoUiToggleTheme`. Sets `data-theme` on `<html>`.

Hosts that sync theme to the server (Beacon `account_theme`) can ignore kit storage and drive `data-theme` from the user preference.

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
];
```

Or lazy-load from `assets/controllers.json` pointing at the same paths.

### Vite / Webpack Encore

```ts
// assets/controllers/clipboard_copy_controller.ts (host re-export)
export { default } from '../../vendor/nowo-tech/ui-kit-bundle/src/Resources/assets/stimulus-peers/clipboard_copy_controller';
```

Repeat for `confirm_dialog`, `confirm_submit`, `page_loader`, `toast_stack`, `tabs`.

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
