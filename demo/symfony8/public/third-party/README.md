# Vendored demo assets

Served from the repo so the demo loads no CDN (REQ-DEMO-014 exemption; the UiKit demo is a host-theme demo).

| Asset | Version | Source |
| --- | --- | --- |
| Bootstrap CSS + JS bundle | 5.3.3 | `bootstrap@5.3.3` on npm (`dist/css/bootstrap.min.css`, `dist/js/bootstrap.bundle.min.js`), MIT, see `bootstrap/LICENSE`. The trailing `sourceMappingURL` comments were removed (no maps are vendored). |
| Bootstrap Icons | 1.11.3 | `bootstrap-icons@1.11.3` on npm (`font/bootstrap-icons.min.css`, `font/fonts/*`), MIT, see `bootstrap-icons/LICENSE`. |

To upgrade, replace the files from the npm tarball of the new version and update this table and the version in `e2e/tests/card-intro-contrast.spec.ts`.

## Inter (font)

| Asset | Version | Source |
| --- | --- | --- |
| Inter variable (weight axis 100-900, covers 400, 500, 600 and 700), `latin` and `latin-ext` subsets | 5.3.0 | `@fontsource-variable/inter@5.3.0` on npm (`files/inter-latin-wght-normal.woff2`, `files/inter-latin-ext-wght-normal.woff2`), which repackages the official rsms/inter release. SIL Open Font License 1.1, see `inter/LICENSE`. |

`inter/inter.css` declares the `@font-face` rules (family `Inter`, `font-display: swap`, same `unicode-range` split as the npm package) and is loaded with a `<link>` from the page_contract admin and guest layouts, so no inline style is needed under a CSP. The demo no longer loads fonts from Google Fonts.

To upgrade, replace the two woff2 files and `LICENSE` from the npm tarball of the new version and update this table and the header comment in `inter/inter.css`.
