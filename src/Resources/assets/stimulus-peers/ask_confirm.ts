/**
 * Promise-based confirmation in a native `<dialog>` instead of `window.confirm()` (2.8): themed, translated buttons,
 * focus kept in the dialog, Escape / backdrop cancel. Focus starts on Cancel so a stray Enter never runs the action;
 * `danger` paints Confirm as a destructive button. The markup uses the kit confirm classes (`nowo-ui-confirm*`, the
 * same as partials/_confirm.html.twig); pass `classes` to use your own.
 *
 *   import { askConfirm, pageConfirmLabels } from '…/stimulus-peers/ask_confirm';
 *   if (await askConfirm('Delete this key?', { ...pageConfirmLabels(), danger: true })) { … }
 */
export interface ConfirmClasses {
  dialog: string;
  panel: string;
  header: string;
  title: string;
  body: string;
  actions: string;
  cancel: string;
  confirm: string;
  danger: string;
}

export interface ConfirmOptions {
  confirm?: string | undefined;
  cancel?: string | undefined;
  /** Short heading; the message then becomes the body text. */
  title?: string | undefined;
  danger?: boolean | undefined;
  classes?: Partial<ConfirmClasses> | undefined;
}

export const KIT_CONFIRM_CLASSES: ConfirmClasses = {
  dialog: 'nowo-ui-confirm',
  panel: 'nowo-ui-confirm__panel',
  header: 'nowo-ui-confirm__header',
  title: 'nowo-ui-confirm__title',
  body: 'nowo-ui-confirm__body',
  actions: 'nowo-ui-confirm__footer',
  cancel: 'nowo-ui-btn',
  confirm: 'nowo-ui-btn nowo-ui-btn--primary',
  danger: 'nowo-ui-btn nowo-ui-btn--danger',
};

/**
 * The button labels the layout prints on `<html data-confirm-ok="…" data-confirm-cancel="…">` (e.g. the
 * NowoUiKitBundle `confirm.ok` / `confirm.cancel` messages), with English fallbacks.
 */
export function pageConfirmLabels(): { confirm: string; cancel: string } {
  const root = document.documentElement;

  return {
    confirm: root.dataset.confirmOk || 'Confirm',
    cancel: root.dataset.confirmCancel || 'Cancel',
  };
}

let sequence = 0;

export function askConfirm(message: string, options: ConfirmOptions = {}): Promise<boolean> {
  const classes = { ...KIT_CONFIRM_CLASSES, ...options.classes };
  const labels = { ...pageConfirmLabels(), ...pick(options) };

  const dialog = document.createElement('dialog');
  dialog.className = classes.dialog;
  const titleId = `nowo-ui-ask-confirm-${++sequence}`;
  dialog.setAttribute('aria-labelledby', titleId);

  const panel = document.createElement('div');
  panel.className = classes.panel;
  const header = document.createElement('div');
  header.className = classes.header;
  const title = document.createElement('h2');
  title.id = titleId;
  title.className = classes.title;
  title.textContent = options.title ?? message;
  header.append(title);
  if (options.title) {
    const body = document.createElement('p');
    body.className = classes.body;
    body.textContent = message;
    header.append(body);
  }
  const actions = document.createElement('div');
  actions.className = classes.actions;
  const cancel = document.createElement('button');
  cancel.type = 'button';
  cancel.className = classes.cancel;
  cancel.textContent = labels.cancel;
  const confirm = document.createElement('button');
  confirm.type = 'button';
  confirm.className = options.danger ? classes.danger : classes.confirm;
  confirm.textContent = labels.confirm;
  actions.append(cancel, confirm);
  panel.append(header, actions);
  dialog.append(panel);
  document.body.append(dialog);

  return new Promise<boolean>((resolve) => {
    let answered = false;
    const finish = (value: boolean): void => {
      if (answered) {
        return;
      }
      answered = true;
      if (dialog.open && typeof dialog.close === 'function') {
        dialog.close();
      }
      dialog.remove();
      resolve(value);
    };
    confirm.addEventListener('click', () => finish(true));
    cancel.addEventListener('click', () => finish(false));
    // Escape fires `cancel`, then `close`; a backdrop click lands on the <dialog> itself.
    dialog.addEventListener('close', () => finish(false));
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) {
        finish(false);
      }
    });

    if (typeof dialog.showModal === 'function') {
      dialog.showModal();
    } else {
      dialog.setAttribute('open', '');
    }
    cancel.focus();
  });
}

function pick(options: ConfirmOptions): Partial<{ confirm: string; cancel: string }> {
  const labels: Partial<{ confirm: string; cancel: string }> = {};
  if (options.confirm) {
    labels.confirm = options.confirm;
  }
  if (options.cancel) {
    labels.cancel = options.cancel;
  }

  return labels;
}
