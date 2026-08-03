## AI contribution guidelines — Ui Kit Bundle

Follow these rules when contributing PHP, TypeScript, Twig, and documentation to this repository.

---

## Project scope

- **Type:** Standalone Symfony bundle (`nowo-tech/ui-kit-bundle`).
- **PHP:** `>=8.1 <8.6` with `declare(strict_types=1);` in every PHP file.
- **Symfony:** Support **6.x, 7.x, and 8.x** (`^6.0 || ^7.0 || ^8.0` on `symfony/*` constraints).
- **Mandatory Symfony minors for CI:** **7.4**, **8.0**, **8.1** (Symfony 8 requires PHP 8.4+).
- **Frontend:** TypeScript + Vite in `src/Resources/assets/`; built IIFEs in `src/Resources/public/js/`.
- **Language:** PHPDoc, inline comments, and user-facing docs in **English** only.
- **No CRUD admin routes** — presentation kit only (Twig macros, CSS, modal/shell JS).

---

## PHP standards

- PSR-12 + Symfony coding standards; run `make cs-check` before finishing.
- Use **strict comparisons** (`===`) and constructor injection.
- Prefer `final` classes; keep BC on public config keys and Twig macro/partial contracts.
- Do **not** use service autowiring in the bundle; wire services in `src/Resources/config/services.yaml`.
- Preserve the DI extension alias: `nowo_ui_kit`.

---

## Bundle-specific conventions

- Twig namespace: `NowoUiKitBundle` (see `TwigPathsPass`).
- Macros: `@NowoUiKitBundle/macros/ui.html.twig` (`ui.btn`, toolbar, pagination, tabs, modal attrs, …).
- Semantic CSS classes: `nowo-ui-*` (plus framework dual classes when `css_framework` ≠ `custom`/`none`).
- Config enums: `css_framework`, `icon_set` (see `Enum\CssFramework` / `Enum\IconSet`).
- Public assets publish to `public/bundles/nowouikit/` via package `nowo_ui_kit`.
- Vite TS entries: `nowo-ui-modal.ts`, `nowo-ui-shell.ts` → `js/nowo-ui-modal.js`, `js/nowo-ui-shell.js`.
- YAML translations under `src/Resources/translations/NowoUiKitBundle.*.yaml` (7 locales).

---

## Tests and quality

- PHPUnit target: **~100% line coverage** on `src/`; justify exclusions in `phpunit.xml.dist`.
- TypeScript tests via Vitest: `make test-ts`.
- Full gate: `make release-check` (style, static analysis, PHP + TS coverage, demos).
- Use real collaborators or focused test doubles; avoid mocking `final` classes when PHPUnit 10 blocks it.

---

## Documentation

- Keep `README.md` badges and the **Documentation** section aligned with `docs/`.
- Update `docs/CHANGELOG.md` and `docs/UPGRADING.md` for user-visible changes.
- Flex recipe lives in `.symfony/recipe/nowo-tech/ui-kit-bundle/1.0/`; document Flex steps in `docs/INSTALLATION.md`.

---

## Do not

- Add manage/CRUD routes or Doctrine entities without an explicit major-version plan and spec update.
- Introduce breaking changes to public Twig macro signatures or config keys without a major release note.
- Commit secrets, `.env` files, or demo `var/` caches.
- Add Spanish PHPDoc or comments in `src/`.
- Touch unrelated files when fixing a focused issue.
