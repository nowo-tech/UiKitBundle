# Stimulus / data-attribute contracts — Nowo UiKit Bundle

UiKit ships optional vanilla IIFEs. Hosts **may** attach Symfony UX Stimulus controllers to the same markup; the kit does **not** require Stimulus.

## Table of contents

- [Shared rules](#shared-rules)
- [Toast stack](#toast-stack)
- [Confirm dialog](#confirm-dialog)
- [Page loader](#page-loader)
- [Thinking orb](#thinking-orb)
- [Shell / nested nav](#shell--nested-nav)
- [Theme toggle](#theme-toggle)
- [Locale / kebab](#locale--kebab)

## Shared rules

1. Prefer **`data-nowo-ui-*` / `data-nowo-*`** as the public contract.
2. Kit JS is additive: if a Stimulus controller owns behaviour, omit the matching kit script.
3. Window helpers (when kit JS is loaded) are stable for progressive enhancement.

## Toast stack

| Attribute | Role |
|-----------|------|
| `data-nowo-ui-toast-stack` | Root stack |
| `data-nowo-ui-toast` | One toast |
| `data-timeout` | Auto-hide ms (`0` / missing → kit default 5000) |
| `data-nowo-ui-toast-dismiss` | Dismiss control |

Script: `js/nowo-ui-toast.js` → `window.nowoUiDismissToast(el)`.

Stimulus peer idea: `data-controller="toast-stack"` with targets `toast`; keep the same dismiss button attribute or map actions.

## Confirm dialog

| Attribute | Role |
|-----------|------|
| `data-nowo-ui-confirm` on `<dialog>` | Confirm root |
| `data-nowo-confirm-open` + `data-nowo-confirm-target` | Open trigger |
| `data-nowo-confirm-close` | Close control |

Script: `js/nowo-ui-confirm.js` → `nowoUiOpenConfirm` / `nowoUiCloseConfirm`.

Destructive **POST + CSRF** stays in the host form inside the dialog (REQ-SEC-005).

## Page loader

| Attribute | Role |
|-----------|------|
| `data-nowo-ui-page-loader` | Overlay root |
| `data-nowo-ui-page-loader-show` / `…-hide` | Optional triggers |
| class `is-active` | Visible state |

Twig: `visual: spinner` (default) or `visual: orb` (+ optional `orb_state` / `orb_size` / `orb_theme`).

Script: `js/nowo-ui-page-loader.js` → `nowoUiShowPageLoader` / `nowoUiHidePageLoader`. With `visual: orb`, also load `js/nowo-ui-orb.js`.

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
