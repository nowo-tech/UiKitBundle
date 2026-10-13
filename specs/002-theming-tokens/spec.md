# Feature Specification: Complete `--nowo-ui-*` theming tokens

**Feature Branch**: `002-theming-tokens`  
**Created**: 2026-10-13  
**Status**: Implemented (1.13.0)  
**Input**: Symfony Beacon spec 134 (`kit-upstream-improvements`) **FR-002** — "every colour, radius, font and focus style used by UiKit templates, macros and `nowo-ui.css` reads a documented `--nowo-ui-*` token (no hard-coded Tailwind palette classes or Bootstrap colour utilities in kit output). Add the tokens hosts are missing today (display font, focus ring, link, input surface / border, table head, tabs, pagination, list items). A host theme becomes a short `:root` block. Document a theming guide."

**Related docs**: [`docs/THEMING.md`](../../docs/THEMING.md), [`docs/USAGE.md`](../../docs/USAGE.md#remap-css-tokens), [`docs/UPGRADING.md`](../../docs/UPGRADING.md#to-1130), [`../001-baseline/spec.md`](../001-baseline/spec.md) (FR-THEME-001)

---

## Problem

Symfony Beacon (Tailwind stack) needed a 1,557-line `templates/kit/_kit_admin_styles.html.twig` remap under
`.kit-admin` to repaint kit output. A large part of it existed only because UiKit:

1. emitted Tailwind palette utilities from the macros (`bg-blue-600`, `hover:bg-blue-500`, `border-slate-300`,
   `text-slate-500`, `divide-y divide-slate-200`, `border-blue-600 text-blue-700`, `bg-emerald-50`…) and radius
   utilities (`rounded-md`, `rounded-full`) that the host had to neutralise with `!important`;
2. hard-coded hex for warning / info flashes and badges and for modal fallbacks;
3. lacked tokens for display font, focus ring, links, input surface / border / placeholder / focus, table head /
   stripes / hover, tabs (inactive / hover / active), pagination (hover / active), list items, button variants
   (secondary hover, danger, outline, link), badge radius, overlays and shadows;
4. declared every token on `:root`, so derived values resolved once and scoped remaps (`.kit-admin`) could not
   reach hover / focus colours.

## User Scenarios & Testing

### User Story 1 — Host theme in one block (P1)

As a host, I set a handful of `--nowo-ui-*` tokens in one `html:root` block and every kit surface (macros,
partials, feature-bundle panels using them) follows, in light and dark mode.

**Acceptance**:
1. **Given** `--nowo-ui-primary` set on `html:root`, **When** a primary button, active tab, active page, link,
   focus ring, checkbox and progress bar render, **Then** all use that colour (and the hover / ring derive from it).
2. **Given** the same token set on a scope (`.kit-admin`), **Then** the same holds inside the scope (component
   tokens are resolved at the use site).
3. **Given** no host tokens, **Then** the light look is identical to 1.12 (same base values).

### User Story 2 — No palette in kit output (P1)

As a Tailwind host, I never need to override `bg-blue-*` / `slate-*` / `divide-*` / `rounded-*` utilities coming
from kit macros.

**Acceptance**: rendering every `ui.*` macro for `tailwind`, `custom`, `none` and `foundation` yields no palette,
radius or divide utility (guarded by `ThemingTokensTest`).

### User Story 3 — Bootstrap hosts (P2)

As a Bootstrap host, the default look stays Bootstrap's; adding `nowo-ui-bs-tokens` on an ancestor paints
Bootstrap 5.3 components from the same tokens.

## Requirements

- **FR-001**: Base tokens declared on `:root` and `[data-theme="dark"]`: primary / contrast, danger ×5,
  success ×3, **warning ×3**, **info ×3**, surface, surface-muted, border, border-strong, text, muted, radius.
- **FR-002**: Component tokens are optional (never declared) and read through `var()` fallback chains ending at
  base tokens: typography (`font-family`, `font-display`, `heading-weight`), focus (`focus-ring-color`,
  `focus-ring`), links (`link`, `link-hover`, `primary-hover`, `accent`), radii (`badge-`, `modal-`, `card-radius`),
  `overlay`, shadows (`shadow-popover`, `shadow-toast`, `shadow-dialog`), `btn-*`, `input-*`, `table-*` paint,
  `tabs-*` / `tab-*`, `pagination-*`, `list-*`, `card-*`, `nav-active-*`, `progress-*`.
- **FR-003**: `nowo-ui.css` contains no hex outside the two token blocks and no hex `var()` fallbacks.
- **FR-004**: Macros emit no Tailwind palette, radius or divide utilities; layout / spacing / type-scale utilities
  stay. No `nowo-ui-*` class, macro argument or partial variable is removed.
- **FR-005**: New additive surface: `.nowo-ui-btn--outline` (`ui.btn('outline')`, Bootstrap `btn-outline-primary`),
  `.nowo-ui-link` (`ui.link()`), `.nowo-ui-check` (`ui.check()`), `:focus-visible` rings, hover states on
  kit-painted stacks (`:not(.btn)` / `:not(.form-control)` / `:not(.table)` guards keep Bootstrap's own states),
  pagination list row layout (zero specificity).
- **FR-006**: Opt-in `.nowo-ui-bs-tokens` bridge: every rule scoped under the class; maps tokens to Bootstrap 5.3
  component variables (buttons, forms, tables, list groups, pagination, nav tabs, cards, modals, progress, alerts)
  plus scoped `!important` for `text-muted` and kit badges.
- **FR-007**: `docs/THEMING.md` lists every token used in `nowo-ui.css` with its default (guarded by a test), the
  framework notes, a generic host theme and the Beacon (moss / sand) mapping.

## Out of scope (host product layout)

Control heights, paddings and font-size scales; Beacon's `<dialog>` confirm-panel chrome; four-sided pill borders
on tabs; toolbar / panel spacing; styling of Bootstrap class names emitted by other kits on hosts that do not load
Bootstrap.

## Success criteria

- `ThemingTokensTest` + `UiMacrosTwigTest` green; PHP coverage gate (≥ 99 %) and Vitest gates unchanged.
- Beacon can replace its `.kit-admin` colour remaps and Tailwind-utility neutralisers with one `html:root` block.
