# Feature Specification: Unified dialog sizes and anatomy

**Feature Branch**: `feat/dialog-sizes`  
**Created**: 2026-11-05  
**Status**: Implemented (target 2.29.0)  
**Input**: Owner rule 2026-11-05 ("debemos estandarizar todos los modales", "el ancho y alto depende del contenido,
unifícalos"), symfony-beacon `docs/ops/BUNDLES-REQUESTS/2026-11-05-dialog-sizes.md` and `docs/identity/UI-PATTERNS.md`
§16. UiKit goes first: the other kits (TaskBoard, Yopass, UptimeMonitor, Wiki, Vault) consume its tokens.

**Related docs**: [`docs/PAGE_CONTRACT.md`](../../docs/PAGE_CONTRACT.md#dialog-sizes-and-anatomy-229),
[`docs/THEMING.md`](../../docs/THEMING.md#dialog-sizes-229), [`docs/UPGRADING.md`](../../docs/UPGRADING.md#unreleased)

---

## Summary

Two dialog sizes set by shared tokens, the body as the only scrolling part and the footer outside it. Existing options
and blocks render the same markup (fixtures `tests/Fixtures/bc-2.27`); the change is visual.

## Requirements

- **FR-DIALOG-SIZE-001** — Tokens `--nowo-dialog-width-md` `min(40rem, 100vw - 2rem)`, `--nowo-dialog-height-md`
  `min(36rem, 100dvh - 2rem)`, `--nowo-dialog-width-wide` `min(56rem, 100vw - 2rem)`, `--nowo-dialog-height-wide`
  `min(52rem, 100dvh - 2rem)`, read with these fallbacks and never declared on `:root`. `dialog`, `confirm_dialog` and
  `confirm` are medium; `--lg` / `--xl` / `--wide` are wide; `--sm` is medium. Width and height are the size's; a plain
  yes / no (no field, list, table or fold) keeps its natural height; side panels keep their own sizes.
- **FR-DIALOG-ANATOMY-001** — Header (title, optional status badge `nowo-ui-dialog__title-row`, close × with
  `aria-label`), body (the only part that scrolls), footer (outside the body, always visible). `dialog` partial:
  `size: 'wide'`, `status`, `footer_target`. `confirm_dialog` partial: `title` switches to the anatomy with `body` /
  `footer` blocks, `size`, `status`, `close_label`, `dismissible`, `title_id`.
- **FR-DIALOG-REMOTE-001** — The remote form dialog has a footer; the fragment's `[data-nowo-ui-dialog-actions]` (else
  the form's last `.nowo-ui-form__actions`) move into it, each control bound with `form="…"`; opt-out
  `data-nowo-ui-dialog-keep-actions`; footer emptied on loading, load error and close. Phones: the screen width with a
  16 px gutter (`--nowo-ui-remote-dialog-phone-inset` default `1rem`).
- **FR-DIALOG-BC-001** — Focus trap, Escape and focus return unchanged; markup without the new options unchanged.

## User Scenarios & Testing

- **US-1** — A host sets `--nowo-dialog-width-md` once and every kit's medium dialog follows. Tests: `DialogSizesTest`.
- **US-2** — A long form in a dialog: the body scrolls, Save stays visible in the footer at 1280 and 390 px. Tests:
  demo e2e `dialog-sizes.spec.ts`, Vitest `nowo-ui-remote-form-core.test.ts` (footer), `remote-form-dialog.spec.ts`.
- **US-3** — Existing callers render byte-identical markup. Tests: `RemoteDialogOverlay228BcTest`,
  `ConfirmDialogPartialTest`, `DialogPartialTest`.
