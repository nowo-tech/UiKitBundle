# Stimulus peer controllers (optional)

TypeScript sources for hosts that already run **Symfony UX Stimulus** / AssetMapper / Vite.
They are **not** built into the kit IIFEs. Prefer the no-build scripts under `src/Resources/public/js/` unless you need Stimulus actions/values.

| File | Stimulus identifier | Kit IIFE (default) |
|------|---------------------|--------------------|
| `clipboard_copy_controller.ts` | `clipboard-copy` | `nowo-ui-clipboard.js` |
| `confirm_dialog_controller.ts` | `confirm-dialog` | `nowo-ui-confirm.js` |
| `confirm_submit_controller.ts` | `confirm-submit` | — (peer only; form `submit` or button `click`; 2.8 `dialog` mode and `createConfirmSubmitController()`) |
| `ask_confirm.ts` | — (module: `askConfirm()`, `pageConfirmLabels()`, 2.8) | — |
| `page_loader_controller.ts` | `page-loader` | `nowo-ui-page-loader.js` |
| `toast_stack_controller.ts` | `toast-stack` | `nowo-ui-toast.js` |
| `tabs_controller.ts` | `tabs` | `nowo-ui-tabs.js` |
| `release_status_controller.ts` | `release-status` | `nowo-ui-release.js` (imports `../src/nowo-ui-release-core.ts`) |
| `auto_refresh_controller.ts` | `auto-refresh` | — (peer only) |
| `temporary_reveal_controller.ts` | `temporary-reveal` | — (peer only, 1.15) |
| `collapse_panel_controller.ts` | `collapse-panel` | — (peer only, 1.15) |
| `datatable_controller.ts` | `datatable` | — (peer only, 1.15; needs the optional `datatables.net-dt` + `datatables.net-responsive-dt`) |
| `responsive_table_controller.ts` | `responsive-table` | — (peer only, 1.17; `partials/_responsive_table.html.twig`) |
| `filters_collapse_controller.ts` | `filters-collapse` | — (peer only, 1.17; `partials/_filters_collapse.html.twig`) |
| `disclosure_icon_controller.ts` | `disclosure-icon` | — (peer only, 1.17; `partials/_disclosure_icon.html.twig`) |
| `listing_customizer_controller.ts` | `listing-customizer` | — (peer only; `partials/_listing_customizer.html.twig`, listing preferences cookie via `listing_cookie.ts`) |

`listing_cookie.ts` (module: `readListingCookie()`, `writeListingCookie()`, `patchListingCookie()`,
`deleteListingCookie()`) reads and writes the listing preference cookie shared by `listing-customizer` and
`responsive-table` (`cookie` value).

`icon_state.ts` (1.17) is the icon bridge of the last three peers; `../morphicons/index.ts` (optional `morphicons` +
`lucide`) animates them with `installMorphiconPeers()`.

See [docs/STIMULUS.md](../../../../docs/STIMULUS.md) for data attributes and import examples.
Requires `@hotwired/stimulus` in the host app (not a kit runtime dependency).
