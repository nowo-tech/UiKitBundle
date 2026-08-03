# Security

If you discover a security-related issue, please report it privately (e.g. by email to the maintainers) rather than opening a public issue. We will address it as soon as possible.

## Scope

**nowo-tech/ui-kit-bundle** is a presentation kit: Twig macros/partials, semantic `nowo-ui-*` CSS, and small TypeScript helpers for modal/shell chrome. It does **not** ship HTTP manage/CRUD routes, consent cookies, Doctrine entities, or admin controllers.

**In scope:** Twig output escaping expectations, trusted markup options passed into macros/partials, published JS helpers, Flex recipe defaults.

**Out of scope:** Host application authentication, CSP policy of the integrator, and third-party CSS frameworks loaded by the host layout.

## Attack surface

| Input / surface | Description |
| --- | --- |
| **Twig macros / partials** | Labels, hrefs, and optional HTML fragments passed by the host app |
| **Configuration** | `nowo_ui_kit.css_framework` / `icon_set` (compile-time enums) |
| **Frontend JS** | `nowo-ui-modal.js` / `nowo-ui-shell.js` (DOM open/close, no network I/O) |
| **Translations** | Domain `NowoUiKitBundle` strings rendered in Twig |

The bundle does **not** expose CLI commands that mutate production data, outbound HTTP integrations, file uploads, or cookie writers.

## Threat model

| Category | Risk | Applicability |
| --- | --- | --- |
| **XSS** | Unescaped HTML passed into macros/partials | Host must treat user-controlled strings as unsafe; prefer plain text / Twig auto-escape |
| **Injection** | N/A (no SQL / no form POST handlers) | Not applicable |
| **CSRF** | N/A (no state-changing routes) | Not applicable |
| **Authz** | N/A (no admin routes) | Host protects its own admin area |
| **Supply chain** | Compromised dependency | `composer audit` / Dependabot |

## Mitigations

- Twig auto-escaping remains enabled; do not pipe untrusted input through `|raw` in host overrides.
- Modal/shell JS only toggles DOM attributes and focus; it does not call `eval` or load remote scripts.
- Recipe defaults contain no secrets.

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
