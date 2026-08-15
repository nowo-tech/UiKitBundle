# Stimulus peer controllers (optional)

TypeScript sources for hosts that already run **Symfony UX Stimulus** / AssetMapper / Vite.
They are **not** built into the kit IIFEs. Prefer the no-build scripts under `src/Resources/public/js/` unless you need Stimulus actions/values.

| File | Stimulus identifier | Kit IIFE (default) |
|------|---------------------|--------------------|
| `clipboard_copy_controller.ts` | `clipboard-copy` | `nowo-ui-clipboard.js` |
| `confirm_dialog_controller.ts` | `confirm-dialog` | `nowo-ui-confirm.js` |
| `confirm_submit_controller.ts` | `confirm-submit` | — (peer only) |
| `page_loader_controller.ts` | `page-loader` | `nowo-ui-page-loader.js` |
| `toast_stack_controller.ts` | `toast-stack` | `nowo-ui-toast.js` |
| `tabs_controller.ts` | `tabs` | `nowo-ui-tabs.js` |

See [docs/STIMULUS.md](../../../../docs/STIMULUS.md) for data attributes and import examples.
Requires `@hotwired/stimulus` in the host app (not a kit runtime dependency).
