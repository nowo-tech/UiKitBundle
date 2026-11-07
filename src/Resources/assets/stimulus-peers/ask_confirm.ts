/**
 * Promise-based confirmation in a native `<dialog>` instead of `window.confirm()` (2.8): themed, translated buttons,
 * focus kept in the dialog, Escape / backdrop cancel. Focus starts on Cancel so a stray Enter never runs the action;
 * `danger` paints Confirm as a destructive button. The markup uses the kit confirm classes (`nowo-ui-confirm*`, the
 * same as partials/_confirm.html.twig); pass `classes` to use your own.
 *
 * 2.31 (dialog rule v2): a close button (×) in the header, after the title, like the `confirm` / `confirm_dialog`
 * partials (`dismissible`, default true). It cancels (resolves false). Its accessible name is `closeLabel`, else
 * `<html data-confirm-close="…">` (e.g. the NowoUiKitBundle `action.close` message), else "Close". Turn it off per
 * call (`dismissible: false`) or for the page (`<html data-confirm-dismissible="false">`; only "false" or "0" turn it off).
 *
 *   import { askConfirm, pageConfirmLabels } from '…/stimulus-peers/ask_confirm';
 *   if (await askConfirm('Delete this key?', { ...pageConfirmLabels(), danger: true })) { … }
 */
import { closeWithEffect } from '../src/nowo-ui-motion-core';
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
  /** 2.31: the close button (×) of the header. Optional so a 2.30 host class map still type-checks. */
  close?: string;
}

export interface ConfirmOptions {
  confirm?: string | undefined;
  cancel?: string | undefined;
  /** Short heading; the message then becomes the body text. */
  title?: string | undefined;
  danger?: boolean | undefined;
  classes?: Partial<ConfirmClasses> | undefined;
  /** 2.31: the close button (×) in the header; default `<html data-confirm-dismissible>` ("false" turns it off), else true. */
  dismissible?: boolean | undefined;
  /** 2.31: accessible name of the close button; default {@link pageConfirmCloseLabel}. */
  closeLabel?: string | undefined;
}

/**
 * A property of `document` from its prototype chain (audit HG-I7): an injected `<img name="body">` becomes an own property
 * of the document and hides the real one, so the dialog would land inside it, invisible, with the promise never answered.
 */
function documentProperty(name: string): PropertyDescriptor | undefined {
  for (let proto = Object.getPrototypeOf(document); proto !== null; proto = Object.getPrototypeOf(proto)) {
    const descriptor = Object.getOwnPropertyDescriptor(proto, name);
    if (descriptor) {
      return descriptor;
    }
  }

  return undefined;
}

function documentPart(part: 'documentElement' | 'body'): HTMLElement {
  return documentProperty(part)?.get?.call(document) as HTMLElement;
}

function create<K extends keyof HTMLElementTagNameMap>(tag: K): HTMLElementTagNameMap[K] {
  return documentProperty('createElement')?.value.call(document, tag) as HTMLElementTagNameMap[K];
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
  close: 'nowo-ui-confirm__close',
};

/**
 * The button labels the layout prints on `<html data-confirm-ok="…" data-confirm-cancel="…">` (e.g. the
 * NowoUiKitBundle `confirm.ok` / `confirm.cancel` messages), with English fallbacks.
 */
export function pageConfirmLabels(): { confirm: string; cancel: string } {
  const root = documentPart('documentElement');

  return {
    confirm: root.dataset.confirmOk || 'Confirm',
    cancel: root.dataset.confirmCancel || 'Cancel',
  };
}

/**
 * Accessible name of the close button (2.31): `<html data-confirm-close="…">` (e.g. the NowoUiKitBundle `action.close`
 * message), with an English fallback.
 */
export function pageConfirmCloseLabel(): string {
  return documentPart('documentElement').dataset.confirmClose || 'Close';
}

/**
 * Whether the confirm dialog shows its close button by default (2.31): true unless `<html data-confirm-dismissible>` is
 * exactly "false" or "0" (any other value, empty included, keeps the ×).
 */
export function pageConfirmDismissible(): boolean {
  const value = documentPart('documentElement').dataset.confirmDismissible;

  return value !== 'false' && value !== '0';
}

let sequence = 0;

export function askConfirm(message: string, options: ConfirmOptions = {}): Promise<boolean> {
  const classes = { ...KIT_CONFIRM_CLASSES, ...options.classes };
  const labels = { ...pageConfirmLabels(), ...pick(options) };

  const dialog = create('dialog');
  dialog.className = classes.dialog;
  const titleId = `nowo-ui-ask-confirm-${++sequence}`;
  dialog.setAttribute('aria-labelledby', titleId);

  const panel = create('div');
  panel.className = classes.panel;
  const header = create('div');
  header.className = classes.header;
  const title = create('h2');
  title.id = titleId;
  title.className = classes.title;
  title.textContent = options.title ?? message;
  header.append(title);
  let close: HTMLButtonElement | null = null;
  if (options.dismissible ?? pageConfirmDismissible()) {
    const closeLabel = options.closeLabel || pageConfirmCloseLabel();
    close = create('button');
    close.type = 'button';
    close.className = classes.close ?? 'nowo-ui-confirm__close';
    close.setAttribute('aria-label', closeLabel);
    close.title = closeLabel;
    const glyph = create('span');
    glyph.setAttribute('aria-hidden', 'true');
    glyph.textContent = '\u00d7';
    close.append(glyph);
    header.append(close);
  }
  if (options.title) {
    const body = create('p');
    body.className = classes.body;
    body.textContent = message;
    header.append(body);
  }
  const actions = create('div');
  actions.className = classes.actions;
  const cancel = create('button');
  cancel.type = 'button';
  cancel.className = classes.cancel;
  cancel.textContent = labels.cancel;
  const confirm = create('button');
  confirm.type = 'button';
  confirm.className = options.danger ? classes.danger : classes.confirm;
  confirm.textContent = labels.confirm;
  actions.append(cancel, confirm);
  panel.append(header, actions);
  dialog.append(panel);
  documentPart('body').append(dialog);

  return new Promise<boolean>((resolve) => {
    let answered = false;
    const finish = (value: boolean): void => {
      if (answered) {
        return;
      }
      answered = true;
      if (dialog.open && typeof dialog.close === 'function') {
        closeWithEffect(dialog, () => {
          dialog.close();
          dialog.remove();
        });
      } else {
        dialog.remove();
      }
      resolve(value);
    };
    confirm.addEventListener('click', () => finish(true));
    cancel.addEventListener('click', () => finish(false));
    close?.addEventListener('click', () => finish(false));
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
