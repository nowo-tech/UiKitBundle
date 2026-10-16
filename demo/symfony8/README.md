# Ui Kit Bundle — Symfony 8 demo (Bootstrap)

Runs with **FrankenPHP** (Caddy + PHP). Default mode: `FRANKENPHP_MODE=worker` (see [DEMO-FRANKENPHP.md](../../docs/DEMO-FRANKENPHP.md)).

```bash
cp .env.example .env   # PORT=8092
make up
# open http://localhost:8092/
```

Kitchen sink: macros, shell chrome, modal, pagination, tabs.

## Page contract host-theme demo (REQ-UI-003)

`/page-contract` is an "Acme Shop" back office built only from the UiKit 2.0 page scaffolds
(`page/list`, `show`, `form`, `settings`, `dashboard`, `confirm`, `wizard`, `auth`) and view models
(`src/Controller/PageContractController.php`). The host theme is configured **once** in
`config/packages/nowo_ui_kit.yaml`:

- `layouts.admin` / `layouts.guest` → `templates/page_contract/layouts/*.html.twig` (one-file bridges into
  `base.html.twig` that render every canonical block, add `data-nowo-ui-host-tokens` and call
  `nowo_ui_tokens()`); `base.html.twig` imports Bootstrap into the `base` cascade layer below `nowo-ui`;
- `theme.tokens` (palette + radius), `theme.classes` (class map → `public/css/acme-theme.css`, incl. a
  `@layer utilities` class on `kpi_value` and `action_danger` for delete row actions), `theme.attributes.table`,
  `theme.pages.list` (page-type attribute), `theme.partials.page_header`
  (`templates/page_contract/theme/_page_header.html.twig`).

The list shows filter field options and an icon apply button, `data-testid` / `data-nowo-ui-region` hooks and a
responsive table (cards on phones, priority column hidden on mid widths); the form puts its secondary action in the
header; the dashboard's Orders KPI is a link tile; the delete page is type-to-confirm.

`/page-contract/default/…` renders the same pages with UiKit's default theme: a per-page layout without the host
tokens (`ListPage(layout: …)`) plus the `DemoDefaultTheme` per-bundle exception that resets the class map, the
table attribute and the partial.

| Page | URL |
| --- | --- |
| Overview (dashboard scaffold) | `/page-contract` |
| List / show / form / confirm | `/page-contract/orders`, `/orders/1042`, `/orders/1042/edit`, `/orders/1041/delete` |
| Dashboard / settings / wizard | `/page-contract/dashboard`, `/settings`, `/onboarding` |
| Auth (guest layout) | `/page-contract/sign-in` |

`tests/PageContractTest.php` runs `Nowo\UiKitBundle\Test\PageContractTestCase` against the demo templates.
`make test-e2e` (Playwright) checks every scaffold in both themes; `make demo-screenshots` refreshes
`docs/images/demo/page-contract-*.webp`.
