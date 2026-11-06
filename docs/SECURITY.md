# Security

If you discover a security-related issue, please report it privately (e.g. by email to the maintainers) rather than opening a public issue. We will address it as soon as possible.

## Table of contents

- [Scope](#scope)
- [Attack surface](#attack-surface)
- [Threat model](#threat-model)
- [Mitigations](#mitigations)
- [AI security audit (REQ-SEC-004)](#ai-security-audit-req-sec-004)
- [Secrets and cryptography](#secrets-and-cryptography)
- [Logging](#logging)
- [Dependencies and updates](#dependencies-and-updates)
- [Permissions and exposure](#permissions-and-exposure)
- [Reporting a vulnerability](#reporting-a-vulnerability)
- [Release security checklist (12.4.1)](#release-security-checklist-1241)

## Scope

**nowo-tech/ui-kit-bundle** is a presentation kit: Twig macros/partials, semantic `nowo-ui-*` CSS, and small TypeScript helpers for modal/shell chrome. It does **not** ship HTTP manage/CRUD routes, consent cookies, Doctrine entities, or admin controllers.

**In scope:** Twig output escaping expectations, trusted markup options passed into macros/partials, published JS helpers, Flex recipe defaults.

**Out of scope:** Host application authentication, CSP policy of the integrator, and third-party CSS frameworks loaded by the host layout.

## Attack surface

| Input / surface | Description |
| --- | --- |
| **Twig macros / partials** | Labels, hrefs, and optional HTML fragments passed by the host app |
| **Configuration** | `nowo_ui_kit.css_framework` / `icon_set` / `row_actions_display` (compile-time enums); optional `release_check` (repo + version strings) |
| **Release check (opt-in)** | Public `GET /_nowo-ui/release/status` (only when the host imports the route; 404 while disabled) + outbound `GET https://api.github.com/repos/{owner}/{name}/releases[/latest]` (fixed host, `owner/name` regex, 5–8 s timeout, no redirects, cached) |
| **Frontend JS** | IIFEs: `nowo-ui-modal.js`, `nowo-ui-shell.js`, `nowo-ui-toast.js`, `nowo-ui-confirm.js`, `nowo-ui-page-loader.js`, `nowo-ui-theme.js`, `nowo-ui-orb.js`, `nowo-ui-clipboard.js`, `nowo-ui-tabs.js`, `nowo-ui-release.js`, `nowo-ui-dialog.js`, `nowo-ui-overlay.js`, `nowo-ui-side-panel.js`, `nowo-ui-sortable.js` (DOM / canvas; clipboard, release and the dialog may `fetch` same-origin URLs; 2.28: the remote form dialog posts host forms back with `fetch`) |
| **Remote form dialog (2.28)** | Request header `X-Nowo-Dialog`, response header `X-Nowo-Redirect`, `RemoteDialogResponder`, opt-in `RemoteDialogRedirectListener` |
| **Remote dialog steps (2.33)** | Response header `X-Nowo-Dialog-Location`, opt-in `remote_dialog.step_routes`, `RemoteDialogRedirectEvent`, configurable names `remote_dialog.{request,redirect,location}_header`; each step load is one more fragment insertion |
| **Plain remote dialog content (2.2)** | `data-nowo-ui-dialog-src` (`Action::$dialogId` + `href`): a same-origin fragment fetched into a `<dialog>` |
| **Element attribute options (2.34)** | `Table::$rowAttributes`, `node.link_attr`, `node.item_attr`, `footer_attr`, `header_attr`, `foot_attr`, `meter_attr`, `back_href`: host-supplied attribute maps and a link target |
| **Translations** | Domain `NowoUiKitBundle` strings rendered in Twig |
| **Action icons (opt-in)** | `nowo_ui_kit.action_icons` / `action_variants`: host configuration, checked against `[prefix:]name` and `primary\|secondary\|danger` at container build; the names reach a `class` attribute (auto-escaped) or `ux_icon()`. Not request data. |

The bundle does **not** expose CLI commands that mutate production data, file uploads, or cookie writers. The only outbound HTTP integration is the opt-in release check (off by default).

## Threat model

| Category | Risk | Applicability |
| --- | --- | --- |
| **XSS** | Host-supplied HTML slots rendered with `\|raw` (toolbar, modal body/footer, card body, confirm body, brand SVG, filters actions, shell regions) | **Host-trusted markup only** — never pass UGC / unsanitized user HTML into those slots |
| **Injection** | N/A (no SQL / no form POST handlers) | Not applicable |
| **CSRF** | N/A (no state-changing routes); confirm dialogs expect host forms with CSRF | Host owns POST+CSRF |
| **Authz** | N/A (no admin routes) | Host protects its own admin area |
| **Supply chain** | Compromised dependency / orb third-party origin | `composer audit` / Dependabot; see [THIRD_PARTY.md](THIRD_PARTY.md) |

## Mitigations

- Twig auto-escaping remains enabled for normal variables; documented composition slots use `|raw` for **developer-controlled** HTML only.
- Row actions `confirm_message` is JSON-encoded and HTML-attribute escaped inside the `onsubmit` handler, so quotes / `=` / markup in messages cannot break out of the attribute. Since 2.22, `row_actions_confirm` / `confirm_mode` `native` | `dialog` drop the inline handler (strict-CSP friendly): the message is an escaped `data-confirm-submit-message-value` attribute read by the `confirm-submit` peer.
- `panel_path_rewrites` redirect targets are always a single-`/` same-origin path (no `//host` / `/\host`); config rejects `//`-prefixed or backslash prefixes.
- Kit JS toggles DOM attributes / theme / loader / canvas; it does not call `eval`, `document.write`, or load remote scripts.
- Release check: GitHub answers are rendered with `textContent`; only `https://github.com/…` links are emitted (server and client side). Successful answers are cached for `cache_ttl`; failures soft-fail and are negatively cached for 5 minutes (`ReleaseUpdateChecker::ERROR_CACHE_TTL`), so neither page views nor endpoint hits fan out to GitHub. The status endpoint is anonymous unless you protect it and it reveals the installed version: import it behind your admin firewall / `access_control` (see [CONFIGURATION.md — Release check](CONFIGURATION.md#release-check)).
- Resource limits (REQ-RUNTIME-001): both outbound GitHub requests of the release check have bounded, configurable idle timeouts (`release_check.timeout` 5 s, `release_check.releases_timeout` 8 s, 0.1–60 s) and no redirects, so a slow or hostile upstream cannot pin a PHP / FrankenPHP worker thread indefinitely.
- Compact header (2.27): the breadcrumb labels of the `header_crumbs` partial come from host data or BreadcrumbKit and
  are always printed escaped (`title` and text); BreadcrumbKit's unescaped `icon` HTML is never printed. Escaping does
  not stop a `javascript:` URL, so hrefs are also scheme-checked (`Shell\SafeHref`): crumb hrefs, `home_href` and
  `page_actions` hash item hrefs keep only relative URLs, `#…`, `http`, `https` and `mailto`, read the way a browser
  reads them (case-insensitive, tabs and line breaks removed, leading controls trimmed); anything else is dropped.
  The check is about the scheme, not the origin: protocol-relative `//host/path` and its backslash forms `/\host/path`
  and `\\host/path` (browsers read `\` as `/` in http(s) URLs) pass as links to another host; validate the target
  when links must stay same-origin. Other host-supplied hrefs (`_menu` link items, actions) must still be router-generated or validated by the host.
  Hash `attr` of `_menu` / `page_actions` items goes through `nowo_ui_html_attrs` (attribute names validated, `on*`
  handlers refused, values escaped); button items keep only `data-*` / `aria-*`, and link items drop `href`, `style`
  and `ping` from `attr` (2.27.1), so the checked `href` is the only one. The popover menu panel `id` is escaped
  (it was not, 2.4 – 2.26). The shells' `header_context` slot is host-trusted HTML (printed
  `|raw`, like `header_end`). The request attribute `_nowo_ui_header_presentation` only selects between two fixed
  values (anything else is ignored).
- Remote form dialog (2.28): fetches use `mode: 'same-origin'`, `credentials: 'same-origin'`, `redirect: 'manual'`;
  openers and form actions on another origin are not intercepted (normal navigation). `X-Nowo-Redirect` is honoured
  only on a 2xx answer and only as a path-absolute same-origin URL (one leading `/`, no `//`, no backslash, no control
  character, no `.` / `..` segment raw or `%2e`-encoded, nothing that decodes to `//…`, ≤ 2048 characters, absolute
  URLs refused; REQ-SEC-004 ES-M1: browsers normalise `/.//evil.example` to the protocol-relative `//evil.example`); `RemoteDialogResponder` / `RemoteDialogTarget` apply the same rule server side
  (an absolute URL on the request's own origin is reduced to its path, anything else throws), also for the
  non-dialog `RedirectResponse`, so the helper is never an open redirect. Only the server's own same-origin
  `text/html` answer is injected (whole documents refused), parsed in an inert `<template>` with `<script>`,
  `iframe[srcdoc]`, `meta[http-equiv]`, `<base>`, `<object>` / `<embed>` and inline `on*` attributes removed (defence
  in depth, not a sanitizer);
  titles and messages are `textContent`. The fragment is host Twig output with autoescaping, at the same trust level
  as the page. `X-Nowo-Dialog` is **not** a security signal (any page can send it): CSRF stays the host form's own
  token, and hosts must never skip CSRF, authorization or validation because of the header. `Vary: X-Nowo-Dialog`
  keeps caches from mixing the two modes; the `204` answers are `no-store`. Closing aborts the request, so a late
  answer never injects or navigates. Submits follow native semantics (`FormData(form, submitter)`, the submitter's
  `formaction` / `formmethod` / `formenctype`, same-origin checked), so the server runs the action the user clicked.
  `convert_redirects` keeps the redirect's security headers (`X-Frame-Options`, CSP…) and cookies on the 204. Warnings name a rejected header, never its value.
- Remote dialog steps and fragments (2.33, audit Appendix GT):
  - `X-Nowo-Dialog-Location` is honoured only on a 2xx answer and under the same rule as `X-Nowo-Redirect`; an invalid
    value is ignored with a warning and never fetched. A load follows at most 5 steps in a row, then warns. The counter
    resets on open and on a user submit.
  - `step_routes` patterns are validated at compile time (letters, digits, `_`, `.`, `-`, `*`) and compiled with
    `preg_quote`. `RemoteDialogRedirectEvent::setPath()` validates again, so a listener cannot set another origin or a
    control character.
  - The configurable header names are validated tokens (no CRLF), three different names, and since this version not a
    standard HTTP name (`RemoteDialogHeaders::invalid()`). They are configuration, not a security signal.
  - Fragment filter, used by every fragment of the remote form path and, since this version, of the plain
    `data-nowo-ui-dialog-src` path: `<script>`, `iframe[srcdoc]`, an `<iframe>` whose `src` is not http(s) (read with
    the URL parser), `meta[http-equiv]`, `<base>`, `<object>`, `<embed>`, `on*`, `javascript:` / `vbscript:` values of
    `href`, `xlink:href`, `action`, `formaction`, and SVG `<animate>` / `<set>` of `href`, recursively through nested
    `<template>` content. The filter reads attributes through `Element.prototype`, so a form control named `attributes`
    or `removeAttribute` cannot hide its form. The same filter (`nowo-ui-fragment-filter.ts`) covers each region of the
    `auto-refresh` peer and everything the `list-pagination` peer imports, which also removes `srcdoc` and script URLs of
    `src`, `data` and `poster` (audit GT-L4, GT-I6). The plain path now also uses `mode: 'same-origin'` (a redirect to another origin fails), http(s)
    sources only, and inserts only `text/html` fragments; `text/plain`, JSON and whole documents follow the opener's link
    instead.
  - **Not covered by the filter, on purpose:** other-origin frames, forms, images, stylesheets and links, which hosts use
    legitimately. Neither is markup in attributes that a host script turns into HTML later (`data-prototype`). The filter
    is defence in depth, not a sanitizer for user HTML, so an injection into a fragment still needs a CSP: `script-src`
    without `'unsafe-inline'` (`script-src-attr 'none'` alone does not stop inline script), `frame-src`, `form-action`,
    `img-src`, `style-src`, `connect-src`, `object-src 'none'`, `base-uri 'none'`.
  - Listing customizer scopes: every save, reset and token request is same-origin (`mode: 'same-origin'`, `url`,
    `tokenUrl` and scope URLs checked), so the CSRF token does not follow a redirect to another origin. The scope key is
    client-set: the host must authorise it.
- Overlay registry (2.28): it only toggles `inert` / `aria-hidden` and a root attribute, removes only what it set
  (never an `inert` the host wrote), releases an owner removed from the DOM at once (a `childList` observer while overlays are open) and everything on
  `pagehide`, so an
  error cannot leave the shell locked. The `nowo:overlay-*` events can be dispatched by any same-document script;
  they only hide or show chrome (no data, no permission).
- Recipe defaults contain no secrets.
- **FrankenPHP worker:** runtime PHP services (`ReleaseUpdateChecker`, `ReleaseStatusController`, routing helpers) are `readonly` / stateless — no per-request mutable state; safe with kernel not reset between requests ([FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md)).

## AI security audit (REQ-SEC-004)

`action_icons`, `action_variants`, new action kinds: independent audit Appendix HJ (2026-11-06): **Pass (good)**, overall risk **Low**. Icon names are host configuration, checked at build. `action_variants` cannot change `delete`, which stays danger. A kind does not add a confirmation: destructive actions need `Action::delete()` or an explicit `Confirm`. `setButtonLabel` writes text only; `temporary-reveal` with `clearOnHide` removes everything from the toggle but its icon and label, so never put a secret in the toggle's icon or in an attribute of the toggle or its children: those stay.

| Field | Value |
| ----- | ----- |
| **Date** | 2026-11-06 |
| **Method** | Independent audit Appendix GT: code read and browser PoC in three engines, re-verified 2026-11-06, 2.33.1 fixes included; Appendix HG for 2.34.0 |
| **Overall risk** | **Low** |
| **Grade** | **Pass (good)** |
| **Open residuals** | Host must not feed UGC into `|raw` slots; embedders must apply their own `access_control` around admin chrome; other-origin frames, forms and subresources are left to the host's CSP |

2.33.0: independent audit Appendix GT (2026-11-06), re-verified 2026-11-06: **Pass (good)**, overall risk **Low**; no Critical, High or Medium. GT-L1, GT-L2, GT-L3 and GT-I1..I5 are fixed. Open at the 2.33.0 tag: GT-L4 (Low), the `auto-refresh` peer did not check the origin of `url`, followed redirects and inserted regions unfiltered; GT-I6 (Info), `sanitizeImported()` was narrower than the fragment filter; both fixed after it, below. Other-origin frames, forms and subresources are left to the host's CSP.

2.33.1: independent audit Appendix GT (2026-11-06): **Pass (good)**, overall risk **Low**; no Critical, High, Medium or Low open. GT-L4 (Low) is fixed: the `auto-refresh` peer refuses a `url` on another origin, uses `mode: 'same-origin'` and filters each region. GT-I6 (Info) is fixed: `sanitizeImported()` applies the fragment filter's rules. GT-I7 (Info) is fixed: the form helpers (`submitTarget()`, `liftActions()`, `submit()`) read through the prototypes, so a form with a control named `action`, `method`, `enctype`, `hasAttribute` or similar stays in the dialog. GT-I8 (Info) is fixed: importing the modules no longer touches the DOM. Known limits: an imported region or row may still load other-origin images, frames and stylesheets, which the host's CSP limits; `focus()` on a `[autofocus]` or `[aria-invalid="true"]` element that is itself a `<form>` with a control named `focus` still fails, and the dialog then follows its failure path. A focused `<form>` with a control named `closest` logs an error on Escape and the dialog still closes. A form that is itself `[data-nowo-ui-dialog-actions]` does not submit: put the attribute on a child. On `list-pagination`, an `<iframe>` with `srcdoc` and an http(s) `src` keeps the `src`, and a `javascript:` one stays as an empty frame.

2.34.0: independent audit Appendix HG (2026-11-06): **Pass (good)**, overall risk **Low**; no Critical, High or Medium open. Attribute options added here go through `View\SafeAttributes` (`nowo_ui_safe_attrs()`). `on*`, `style` and `srcdoc` throw `InvalidArgumentException`: those names are written in code. In `href`, `xlink:href`, `src`, `action`, `formaction`, `data`, `poster`, `background`, `cite`, `ping`, `data-redirect`, `data-location` or a `data-*` attribute named `*href`, `*src`, `*url`, `*uri`, `*link` or `*path` (also with `-value`), a value that runs script (`javascript:`, `vbscript:`, `data:`, `blob:`; `SafeHref::runsScript()`) is dropped: the attribute is not printed, so one stored value cannot break a page. Other values print as given, `tel:` and app schemes included, and so does text such as `Madrid: centro`; text that begins with one of those four schemes is dropped too. Other attributes hold text and are only escaped. `data-action`, `data-controller` and framework attributes such as `hx-*` take any value: never map user data to them. Attributes the partial owns are dropped from the maps. `node.href` of the `tree` partial and `href` of `back_link` use the same check: the first prints `#`, the second no link. `back_href` is stricter (relative, `http`, `https`, `mailto`). None of these check the origin: `//host` and `/\host` pass, so a host must validate the origin of any value taken from the request. A value the host marks safe (`|raw`, `Markup`) is printed as given, and an HTML entity in it is not read: never mark user data safe. Known limits: the older `attr`, `title_attr` and `body_attr` options still refuse only `on*`, unchanged for compatibility; `leading_template` and `actions_template` are host templates, trusted; `window.nowoUiAskConfirm` prints message and labels as text.

Unreleased (`sortable`): independent audit Appendix HH (2026-11-06) rated the candidate **Medium** (HH-M1, HH-M2, HH-L1..L10; client-side only, availability and correctness, no data exposure, nothing in a published version). All are addressed in this branch; audit status **re-verification pending**, a delta re-audit runs after this change. Design points: no network and no HTML parsing; announcements and labels go through `textContent`, and `data-nowo-ui-sortable-labels` reads only fixed keys; the moving copy is built in a document with no browsing context, with custom elements and shadow hosts replaced and hooks, names, ids and media removed, `inert` and `aria-hidden`; a `style` attribute the CSP blocked on the original is not copied; attributes, DOM calls and document members go through the prototypes, and importing touches no DOM; the end of a gesture is read in the capture phase on `window`. Keys and move buttons only reach lists that are rendered, not inert and in the item's modal. The move is client-side only: `move` and `moved` are hints for the host, every value in them is forgeable, and a scope is not a boundary. The host's endpoint must authorize, validate ids and positions, and check CSRF as for any other write. Hosts should strip `data-nowo-ui-sortable*`, `name` and `id` from user HTML on such pages ([USAGE](USAGE.md#sortable)). Known limit: a drag continues through a morph that keeps the item, and commits at the drop.

## Secrets and cryptography

The bundle does **not** implement cryptography or store API keys. Nothing secret belongs in `config/packages/nowo_ui_kit.yaml` or the Flex recipe.

## Logging

No request logging or PII collection is performed by this bundle.

## Dependencies and updates

Keep Composer and pnpm lockfiles current. Run `composer audit` before releases. Dependabot PRs are welcome after CI green.

## Permissions and exposure

Ui Kit has **no routes**. Integrators who embed shell/aside chrome in an admin area must apply their own Symfony Security `access_control`.

## Reporting a vulnerability

Prefer private disclosure to the maintainers listed in `composer.json` / GitHub security advisories for [nowo-tech/UiKitBundle](https://github.com/nowo-tech/UiKitBundle).

## Release security checklist (12.4.1)

Before tagging a release, confirm:

| Item | Notes |
|------|--------|
| **SECURITY.md** | This document is current and linked from the README where applicable. |
| **`.gitignore` and `.env`** | `.env` and local env files are ignored; no committed secrets. |
| **No secrets in repo** | No API keys, passwords, or tokens in tracked files. |
| **Recipe / Flex** | Default recipe does not ship production secrets. |
| **Input / output** | Outputs escaped in Twig where host data is rendered. |
| **Dependencies** | `composer audit` run; issues triaged. |
| **Logging** | Bundle does not log secrets. |
| **Cryptography** | Not used. |
| **Permissions / exposure** | No routes; admin embedding documented in USAGE. |
| **Limits / DoS** | N/A for presentation-only helpers. |

Record confirmation in the release PR or tag notes.
