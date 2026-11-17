# Vendored demo assets

Served from the repo so the demo loads no CDN (REQ-DEMO-014 exemption; the UiKit demo is a host-theme demo).

| Asset | Version | Source |
| --- | --- | --- |
| Bootstrap CSS + JS bundle | 5.3.3 | `bootstrap@5.3.3` on npm (`dist/css/bootstrap.min.css`, `dist/js/bootstrap.bundle.min.js`), MIT, see `bootstrap/LICENSE`. The trailing `sourceMappingURL` comments were removed (no maps are vendored). |
| Bootstrap Icons | 1.11.3 | `bootstrap-icons@1.11.3` on npm (`font/bootstrap-icons.min.css`, `font/fonts/*`), MIT, see `bootstrap-icons/LICENSE`. |

To upgrade, replace the files from the npm tarball of the new version and update this table and the version in `e2e/tests/card-intro-contrast.spec.ts`.
