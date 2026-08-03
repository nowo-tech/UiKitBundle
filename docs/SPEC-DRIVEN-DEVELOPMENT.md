# Spec-driven development

In this repository, **spec-driven development** has three layers that stay in sync:

1. **GitHub Spec Kit baseline** — [`specs/001-baseline/`](../specs/001-baseline/) ([`spec.md`](../specs/001-baseline/spec.md), [`code-inventory.md`](../specs/001-baseline/code-inventory.md)), initialized with [GitHub Spec Kit](https://github.com/github/spec-kit) (`.specify/`, **Cursor Agent** skills in `.cursor/skills/speckit-*`). The inventory maps **100%** of production code in `src/`. **How to install, initialize, and use Spec Kit:** [`SPEC-KIT.md`](SPEC-KIT.md).
2. **Product behavior** — what **UiKitBundle** guarantees to applications that integrate it (see [`USAGE.md`](USAGE.md), [`CONFIGURATION.md`](CONFIGURATION.md), [`INSTALLATION.md`](INSTALLATION.md)). **PHPUnit** and **PHPStan** (and **Vitest**) enforce contracts in CI where applicable.
3. **Traceability anchors** — stable **`REQ-*`** identifiers in Makefiles and demos (when present) so changes to scripts, ports, and demo workflows stay discoverable from issues and PRs.

There is no separate executable spec language (for example Gherkin); Spec Kit specs, tests, and static analysis are the mechanical proof alongside this document.

---

## Table of contents

- [User stories](#user-stories)
- [Bundle functional scope](#bundle-functional-scope)
- [Validating the functional spec](#validating-the-functional-spec)
- [Requirement identifiers (`REQ-*`)](#requirement-identifiers-req-)
- [Suggested workflow for contributors](#suggested-workflow-for-contributors)
- [Relationship to Engram / external checklists](#relationship-to-engram-external-checklists)
- [GitHub Spec Kit (summary)](#github-spec-kit-summary)
- [See also](#see-also)

## User stories

| ID | Story |
| --- | --- |
| US-01 | **As a** Twig author, **I want** framework-aware macros (`ui.btn`, toolbar, table, …) **so that** admin UIs share one look across Nowo bundles. |
| US-02 | **As an** integrator, **I want** `css_framework` / `icon_set` configuration **so that** Bootstrap, Tailwind, Foundation, or custom stacks work without forking templates. |
| US-03 | **As an** admin host, **I want** shell chrome partials (aside, burger, avatar, footer) plus `nowo-ui-shell.js` **so that** drawers and menus behave consistently. |
| US-04 | **As an** integrator on custom/Tailwind stacks, **I want** `_modal_shell` + `nowo-ui-modal.js` **so that** accessible modals work without Bootstrap JS. |
| US-05 | **As a** multilingual host, **I want** domain `NowoUiKitBundle` translations with seven locales **so that** I can override strings in my app. |
| US-06 | **As a** maintainer, **I want** behavior covered by PHPUnit + Vitest **so that** regressions are caught in CI. |
| US-07 | **As a** contributor, **I want** `REQ-*` anchors on scripted flows **so that** PRs cite the same identifiers as this document. |

**Out of scope:** manage/CRUD routes, Doctrine entities, cookie consent flows, and guarantees outside the documented public Twig/CSS/JS API.

---

## Bundle functional scope

**Goal:** Canonical Nowo admin UI kit for Symfony — Twig macros/partials, semantic `nowo-ui-*` CSS, multi-framework class helpers, and small Vite TypeScript helpers for modal/shell (**REQ-UI-001**). No manage routes (**REQ-UI-002** N/A).

**In scope**

- Documented integration (root `README.md` and `docs/`).
- Configuration in [`CONFIGURATION.md`](CONFIGURATION.md) and usage in [`USAGE.md`](USAGE.md).
- Frontend entries `nowo-ui-modal.js` / `nowo-ui-shell.js` built from TypeScript via Vite.
- Consumer-facing changes in [`CHANGELOG.md`](CHANGELOG.md) and [`UPGRADING.md`](UPGRADING.md).

**Explicit non-goals**

- Admin CRUD or consent CMP functionality.
- Replacing host CSS frameworks entirely (Ui Kit emits dual / semantic classes; hosts load Bootstrap/Tailwind/etc.).
- **`demo/`** kitchen sink: illustrative unless explicitly documented as stable API.

---

## Validating the functional spec

- Run **`make qa`** or **`make release-check`** as documented in [`CONTRIBUTING.md`](CONTRIBUTING.md).
- Run **PHPUnit** and **Vitest** locally and in CI for code changes.
- New or changed behavior should add or adjust tests under `tests/` and `src/Resources/assets/src/*.test.ts`.

---

## Requirement identifiers (`REQ-*`)

| ID | Where | What it marks |
| --- | --- | --- |
| `REQ-DEMO-005` | `demo/symfony8/Makefile`, `demo/symfony8-tailwind/Makefile` | Canonical `make up`: ends with `Demo started at:` from `PORT`. |
| `REQ-DEMO-007` | `demo/Makefile`, demo Makefiles | `update-bundle` before demo tests in `release-check`. |
| `REQ-DEMO-010` | demo `.env.example`, entrypoint | `FRANKENPHP_MODE=classic\|worker`. |
| `REQ-MAKE-001` | Root `Makefile` | Docker-driven development workflow for the bundle. |
| `REQ-MAKE-008` | Root `Makefile` | `update-deps` via shared `.scripts/`. |
| `REQ-GIT-001` | `.githooks/commit-msg`, `.scripts/check-no-cursor-coauthor.sh` | No Cursor co-author trailers in git history. |
| `REQ-UI-001` | Spec / Configuration | Multi-framework macros + semantic `nowo-ui-*`. |

When you change scripted behavior, update the existing `REQ-*` comment or add a new ID and document it here.

---

## Suggested workflow for contributors

1. Read [`USAGE.md`](USAGE.md) / [`CONFIGURATION.md`](CONFIGURATION.md) and the baseline [`spec.md`](../specs/001-baseline/spec.md).
2. Implement with tests (PHPUnit / Vitest).
3. Update `code-inventory.md` if new production files appear under `src/`.
4. Run `make release-check` (or the subset that applies).
5. Document consumer-visible changes in CHANGELOG / UPGRADING.

---

## Relationship to Engram / external checklists

See [`ENGRAM.md`](ENGRAM.md) for memory / checklist pointers used across Nowo bundles. Parent monorepo checklists (`BUNDLES_FULL_SPECS_*`) may reference this package; do not edit those files from this repository unless explicitly asked.

---

## GitHub Spec Kit (summary)

- Constitution: `.specify/memory/constitution.md`
- Skills: `.cursor/skills/speckit-*`
- Details: [`SPEC-KIT.md`](SPEC-KIT.md)

---

## See also

- [`CONTRIBUTING.md`](CONTRIBUTING.md)
- [`GITHUB_CI.md`](GITHUB_CI.md)
- [`DEMO-FRANKENPHP.md`](DEMO-FRANKENPHP.md)
- [`COVERAGE.md`](COVERAGE.md)
