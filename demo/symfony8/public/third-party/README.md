# Vendored demo assets

Served from the repo so the demo loads no CDN. The demo loads no CSS framework and no icon font: the only vendored
asset is the Inter font of the page contract host theme (`/page-contract`, the demo's one host-theme preview).

The Bootstrap 5.3.3 stylesheet that `e2e/tests/card-intro-contrast.spec.ts` injects (a Bootstrap host, layered and
unlayered) is an e2e fixture: `e2e/fixtures/bootstrap-5.3.3.min.css` (`bootstrap@5.3.3` on npm,
`dist/css/bootstrap.min.css`, MIT, see `e2e/fixtures/bootstrap-LICENSE`).

## Inter (font)

| Asset | Version | Source |
| --- | --- | --- |
| Inter variable (weight axis 100-900, covers 400, 500, 600 and 700), `latin` and `latin-ext` subsets | 5.3.0 | `@fontsource-variable/inter@5.3.0` on npm (`files/inter-latin-wght-normal.woff2`, `files/inter-latin-ext-wght-normal.woff2`), which repackages the official rsms/inter release. SIL Open Font License 1.1, see `inter/LICENSE`. |

`inter/inter.css` declares the `@font-face` rules (family `Inter`, `font-display: swap`, same `unicode-range` split as the npm package) and is loaded with a `<link>` from the page_contract admin and guest layouts, so no inline style is needed under a CSP. The demo no longer loads fonts from Google Fonts.

To upgrade, replace the two woff2 files and `LICENSE` from the npm tarball of the new version and update this table and the header comment in `inter/inter.css`.
