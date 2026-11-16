import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ConfirmDialogController from '../stimulus-peers/confirm_dialog_controller';
import RemoteFormDialogController from '../stimulus-peers/remote_form_dialog_controller';
import {
  bindFocusReturn,
  forgetOpener,
  handOverOpener,
  INVOKER_TTL_MS,
  isFocusable,
  menuToggles,
  openerCandidates,
  recordInvoker,
  rememberOpener,
  resetFocusReturn,
  returnFocus,
  takeInvoker,
} from './nowo-ui-focus-return';
import { bindNowoUiConfirm, openConfirm } from './nowo-ui-confirm';
import { bindNowoUiDialog, openDialog, replaceDialog } from './nowo-ui-dialog';

/** happy-dom without the dialog API: a minimal showModal / close that fires `close` like a browser. */
function polyfill(dialog: HTMLDialogElement): HTMLDialogElement {
  if (typeof dialog.showModal !== 'function') {
    dialog.showModal = function showModal(this: HTMLDialogElement) {
      this.setAttribute('open', '');
    };
  }
  const nativeClose = dialog.close;
  dialog.close = function close(this: HTMLDialogElement) {
    const wasOpen = this.hasAttribute('open');
    if (!wasOpen) {
      return;
    }
    // A browser restores the focus it had before showModal(); a hidden element cannot take it: body.
    (document.activeElement as HTMLElement | null)?.blur?.();
    let fired = false;
    const seen = (): void => {
      fired = true;
    };
    this.addEventListener('close', seen, { once: true });
    if (typeof nativeClose === 'function') {
      nativeClose.call(this);
    }
    this.removeAttribute('open');
    if (!fired) {
      this.removeEventListener('close', seen);
      this.dispatchEvent(new Event('close'));
    }
  };
  return dialog;
}

/** Overrides `matches()` of one element (the `:popover-open` answer of an engine). */
function stubMatches(el: Element, fn: (selector: string, original: (selector: string) => boolean) => boolean): void {
  const original = Element.prototype.matches.bind(el) as (selector: string) => boolean;
  Object.defineProperty(el, 'matches', { configurable: true, value: (selector: string): boolean => fn(selector, original) });
}

const byId = <T extends HTMLElement = HTMLElement>(id: string): T => document.getElementById(id) as T;

/** A page-actions popover menu whose items forward their click to the page's hidden dialog triggers (symfony-beacon `opens:`). */
const PAGE = `
  <div class="nowo-ui-menu" data-nowo-ui-menu data-nowo-ui-popover-menu id="pa">
    <button type="button" id="toggle" popovertarget="pa-panel" data-nowo-ui-popover-trigger>Page actions</button>
    <ul id="pa-panel" popover data-nowo-ui-popover-panel>
      <li><button type="button" id="item" data-nowo-ui-menu-button data-opens="#hidden-trigger">Archive</button></li>
      <li><a id="link-item" href="/x">Export</a></li>
    </ul>
  </div>
  <button type="button" id="hidden-trigger" hidden data-nowo-confirm-open data-nowo-confirm-target="c1">Archive</button>
  <button type="button" id="visible-trigger" data-nowo-confirm-open data-nowo-confirm-target="c1">Archive (visible)</button>
  <dialog id="c1" data-nowo-ui-confirm><button type="button" id="c1-cancel" data-nowo-confirm-close>Cancel</button></dialog>
  <button type="button" id="hidden-dialog-trigger" hidden data-nowo-ui-dialog-open="d1">Invoices</button>
  <dialog id="d1" data-nowo-ui-dialog><button type="button" id="d1-close" data-nowo-ui-dialog-close>Close</button></dialog>
  <dialog id="d2" data-nowo-ui-dialog><button type="button" data-nowo-ui-dialog-close>Close</button></dialog>
  <button type="button" id="outside">Outside</button>
`;

/** The menu is closed by the time the dialog closes: its panel is not rendered. */
function closeMenu(): void {
  byId('pa-panel').hidden = true;
}

/** What a host page-actions controller does: forward the item's click to the trigger its selector names. */
function forward(event: Event): void {
  const item = (event.target as Element).closest<HTMLElement>('[data-opens]');
  if (!item) {
    return;
  }
  closeMenu();
  document.querySelector<HTMLElement>(item.dataset.opens ?? '')?.click();
}

describe('nowo-ui-focus-return (2.52.2)', () => {
  beforeEach(() => {
    resetFocusReturn();
    document.body.innerHTML = PAGE;
    document.querySelectorAll('dialog').forEach((d) => polyfill(d as HTMLDialogElement));
    bindFocusReturn();
  });

  afterEach(() => {
    vi.useRealTimers();
    document.body.removeEventListener('click', forward);
    document.body.innerHTML = '';
    resetFocusReturn();
  });

  describe('isFocusable', () => {
    it('accepts a visible control and rejects hidden, inert, disabled, detached or undisplayed ones', () => {
      expect(isFocusable(byId('toggle'))).toBe(true);
      expect(isFocusable(byId('hidden-trigger'))).toBe(false);
      expect(isFocusable(null)).toBe(false);
      expect(isFocusable(document.body)).toBe(false);
      expect(isFocusable(document.createElement('button'))).toBe(false);
      const outside = byId<HTMLButtonElement>('outside');
      outside.disabled = true;
      expect(isFocusable(outside)).toBe(false);
      outside.disabled = false;
      outside.setAttribute('inert', '');
      expect(isFocusable(outside)).toBe(false);
      outside.removeAttribute('inert');
      outside.style.display = 'none';
      expect(isFocusable(outside)).toBe(false);
      outside.style.display = '';
      outside.style.visibility = 'hidden';
      expect(isFocusable(outside)).toBe(false);
    });

    it('rejects a control inside a closed <details> but not its summary, nor a fieldset-disabled one', () => {
      document.body.insertAdjacentHTML(
        'beforeend',
        '<details id="m"><summary id="s">More</summary><button type="button" id="in">In</button></details><fieldset disabled><button type="button" id="fs">F</button></fieldset>',
      );
      expect(isFocusable(byId('s'))).toBe(true);
      expect(isFocusable(byId('in'))).toBe(false);
      byId<HTMLDetailsElement>('m').open = true;
      expect(isFocusable(byId('in'))).toBe(true);
      expect(isFocusable(byId('fs'))).toBe(false);
    });

    it('trusts checkVisibility() when the engine has it', () => {
      const toggle = byId('toggle') as HTMLElement & { checkVisibility?: () => boolean };
      toggle.checkVisibility = () => false;
      expect(isFocusable(toggle)).toBe(false);
    });

    it('rejects a control inside a popover that is not open', () => {
      const panel = byId('pa-panel');
      stubMatches(panel, (selector, matches) => (selector === ':popover-open' ? false : matches(selector)));
      expect(isFocusable(byId('item'))).toBe(false);
      stubMatches(panel, (selector, matches) => {
        if (selector === ':popover-open') {
          throw new SyntaxError('unsupported');
        }
        return matches(selector);
      });
      expect(isFocusable(byId('item'))).toBe(true);
    });
  });

  describe('menuToggles', () => {
    it('lists the toggles of nested popover menus and <details> menus, innermost first', () => {
      document.body.insertAdjacentHTML(
        'beforeend',
        `<details id="outer" data-nowo-ui-menu><summary id="outer-s">Menu</summary>
          <div data-nowo-ui-popover-menu><button type="button" id="sub-t" data-nowo-ui-popover-trigger popovertarget="sub">Sub</button>
            <ul id="sub" popover data-nowo-ui-popover-panel><li><button type="button" id="deep">Deep</button></li></ul></div>
        </details>
        <button type="button" id="loose-t" popovertarget="loose">Loose</button>
        <div id="loose" popover><button type="button" id="loose-i">I</button></div>
        <div id="anon" popover><button type="button" id="anon-i">I</button></div>`,
      );
      expect(menuToggles(byId('deep')).map((el) => el.id)).toEqual(['sub-t', 'outer-s']);
      expect(menuToggles(byId('item')).map((el) => el.id)).toEqual(['toggle']);
      expect(menuToggles(byId('loose-i')).map((el) => el.id)).toEqual(['loose-t']);
      expect(menuToggles(byId('anon-i'))).toEqual([]);
      expect(menuToggles(byId('outer-s'))).toEqual([]);
    });
  });

  describe('invoker recording', () => {
    it('records the item, its toggles and a fallback once; stale or consumed records are dropped', () => {
      vi.useFakeTimers();
      recordInvoker(byId('item'), byId('outside'));
      expect(takeInvoker().map((el) => el.id)).toEqual(['item', 'toggle', 'outside']);
      expect(takeInvoker()).toEqual([]);
      recordInvoker(byId('item'), byId('toggle'));
      vi.advanceTimersByTime(INVOKER_TTL_MS + 1);
      expect(takeInvoker()).toEqual([]);
      recordInvoker(null);
      expect(takeInvoker()).toEqual([]);
    });

    it('records menu item clicks in the capture phase (not items inside a dialog) and publishes window.nowoUiRecordOpener', () => {
      byId('item').click();
      expect(takeInvoker().map((el) => el.id)).toEqual(['item', 'toggle']);
      byId('outside').click();
      expect(takeInvoker()).toEqual([]);
      byId('c1').insertAdjacentHTML('beforeend', '<ul data-nowo-ui-popover-panel><li><button type="button" id="in-dialog">X</button></li></ul>');
      byId('in-dialog').click();
      expect(takeInvoker()).toEqual([]);
      expect(typeof window.nowoUiRecordOpener).toBe('function');
      window.nowoUiRecordOpener!(byId('outside'));
      expect(takeInvoker().map((el) => el.id)).toEqual(['outside']);
      bindFocusReturn();
    });
  });

  describe('rememberOpener / returnFocus', () => {
    it('falls back from a hidden opener to the recorded item, then to the menu toggle', () => {
      const dialog = byId<HTMLDialogElement>('c1');
      recordInvoker(byId('item'));
      rememberOpener(dialog, byId('hidden-trigger'));
      expect(openerCandidates(dialog).map((el) => el.id)).toEqual(['hidden-trigger', 'item', 'toggle']);
      closeMenu();
      expect(returnFocus(dialog)?.id).toBe('toggle');
      expect(document.activeElement).toBe(byId('toggle'));
      expect(openerCandidates(dialog)).toEqual([]);
      expect(returnFocus(dialog)).toBeNull();
    });

    it('focuses the item itself when it is still visible', () => {
      const dialog = byId<HTMLDialogElement>('c1');
      stubMatches(byId('pa-panel'), (selector, matches) => selector === ':popover-open' || matches(selector));
      recordInvoker(byId('item'));
      rememberOpener(dialog, byId('hidden-trigger'));
      expect(returnFocus(dialog)?.id).toBe('item');
    });

    it('keeps a visible opener first, uses the focused element without an opener, and keeps an earlier list', () => {
      const dialog = byId<HTMLDialogElement>('c1');
      rememberOpener(dialog, byId('visible-trigger'));
      expect(returnFocus(dialog)?.id).toBe('visible-trigger');

      byId('outside').focus();
      rememberOpener(dialog);
      expect(openerCandidates(dialog).map((el) => el.id)).toEqual(['outside']);
      rememberOpener(dialog);
      expect(openerCandidates(dialog).map((el) => el.id)).toEqual(['outside']);
      forgetOpener(dialog);

      (document.activeElement as HTMLElement).blur();
      rememberOpener(dialog);
      expect(openerCandidates(dialog)).toEqual([]);
    });

    it('does nothing while the dialog is open, when no candidate can take focus, or when another modal holds focus', () => {
      const c1 = byId<HTMLDialogElement>('c1');
      const d1 = byId<HTMLDialogElement>('d1');
      rememberOpener(c1, byId('hidden-trigger'));
      c1.setAttribute('open', '');
      expect(returnFocus(c1)).toBeNull();
      c1.removeAttribute('open');
      expect(returnFocus(c1)).toBeNull();

      rememberOpener(c1, byId('visible-trigger'));
      d1.setAttribute('open', '');
      byId('d1-close').focus();
      expect(returnFocus(c1)).toBeNull();
      expect(document.activeElement).toBe(byId('d1-close'));
    });

    it('hands a list over to a replacing dialog', () => {
      const c1 = byId<HTMLDialogElement>('c1');
      const d2 = byId<HTMLDialogElement>('d2');
      rememberOpener(c1, byId('visible-trigger'));
      handOverOpener(c1, d2);
      expect(openerCandidates(c1)).toEqual([]);
      expect(openerCandidates(d2).map((el) => el.id)).toEqual(['visible-trigger']);
      handOverOpener(c1, d2);
      expect(openerCandidates(d2).map((el) => el.id)).toEqual(['visible-trigger']);
    });
  });

  describe('kit dialogs opened from a page-actions item', () => {
    beforeEach(() => {
      bindNowoUiConfirm();
      bindNowoUiDialog();
      document.body.addEventListener('click', forward);
    });

    it('confirm (nowo-ui-confirm.js): Cancel returns focus to the toggle, never the hidden trigger', () => {
      byId('item').click();
      const dialog = byId<HTMLDialogElement>('c1');
      expect(dialog.open).toBe(true);
      byId('c1-cancel').focus();
      dialog.close();
      expect(document.activeElement).toBe(byId('toggle'));
    });

    it('confirm: a visible trigger keeps getting the focus back', () => {
      byId('visible-trigger').click();
      const dialog = byId<HTMLDialogElement>('c1');
      dialog.close();
      expect(document.activeElement).toBe(byId('visible-trigger'));
    });

    it('confirm: openConfirm() without an opener restores the focused element', () => {
      byId('outside').focus();
      openConfirm('c1');
      byId('c1-cancel').focus();
      byId<HTMLDialogElement>('c1').close();
      expect(document.activeElement).toBe(byId('outside'));
    });

    it('dialog (nowo-ui-dialog.js): closing returns focus to the toggle', () => {
      byId('item').dataset.opens = '#hidden-dialog-trigger';
      byId('item').click();
      const dialog = byId<HTMLDialogElement>('d1');
      expect(dialog.open).toBe(true);
      byId('d1-close').focus();
      dialog.close();
      expect(document.activeElement).toBe(byId('toggle'));
    });

    it('dialog: a replacing dialog takes over the focus-return list', () => {
      openDialog('d1', byId('outside'));
      replaceDialog('d2', byId('d1-close'));
      expect(openerCandidates(byId<HTMLDialogElement>('d2')).map((el) => el.id)).toEqual(['outside']);
      byId<HTMLDialogElement>('d2').close();
      expect(document.activeElement).toBe(byId('outside'));
    });
  });

  describe('Stimulus peers opened from a page-actions item', () => {
    let application: Application;
    const tick = (): Promise<void> => new Promise((r) => setTimeout(r, 0));
    const frames = (): Promise<void> => new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(() => setTimeout(r, 0))));

    beforeEach(async () => {
      document.body.insertAdjacentHTML(
        'beforeend',
        `<div data-controller="confirm-dialog" id="cd">
           <button type="button" id="cd-hidden" hidden data-action="confirm-dialog#open">Delete</button>
           <button type="button" id="cd-visible" data-action="confirm-dialog#open">Delete (visible)</button>
           <dialog data-confirm-dialog-target="dialog" id="cd-dialog"><button type="button" id="cd-cancel" data-confirm-dialog-close>Cancel</button></dialog>
         </div>
         <section data-controller="remote-form-dialog">
           <a id="rf-hidden" hidden href="/destinations/new" data-action="remote-form-dialog#open">New</a>
           <dialog data-remote-form-dialog-target="dialog" id="rf-dialog">
             <h2 data-remote-form-dialog-target="title" tabindex="-1"></h2>
             <button type="button" id="rf-x" data-action="remote-form-dialog#close">×</button>
             <div data-remote-form-dialog-target="body"></div>
           </dialog>
         </section>`,
      );
      polyfill(byId<HTMLDialogElement>('cd-dialog'));
      polyfill(byId<HTMLDialogElement>('rf-dialog'));
      vi.stubGlobal('fetch', vi.fn(() => new Promise(() => undefined)));
      document.body.addEventListener('click', forward);
      application = Application.start();
      application.register('confirm-dialog', ConfirmDialogController);
      application.register('remote-form-dialog', RemoteFormDialogController);
      await tick();
    });

    afterEach(() => {
      application.stop();
      vi.unstubAllGlobals();
    });

    it('confirm-dialog: the dialog opens two frames later; Cancel returns focus to the toggle', async () => {
      byId('item').dataset.opens = '#cd-hidden';
      byId('item').click();
      await frames();
      const dialog = byId<HTMLDialogElement>('cd-dialog');
      expect(dialog.open).toBe(true);
      byId('cd-cancel').click();
      await frames();
      expect(dialog.open).toBe(false);
      expect(document.activeElement).toBe(byId('toggle'));
    });

    it('confirm-dialog: a visible trigger gets the focus back', async () => {
      byId('cd-visible').click();
      await frames();
      byId<HTMLDialogElement>('cd-dialog').close();
      expect(document.activeElement).toBe(byId('cd-visible'));
    });

    it('remote-form-dialog: closing returns focus to the toggle', async () => {
      byId('item').dataset.opens = '#rf-hidden';
      byId('item').click();
      const dialog = byId<HTMLDialogElement>('rf-dialog');
      expect(dialog.open).toBe(true);
      byId('rf-x').click();
      await frames();
      expect(dialog.open).toBe(false);
      expect(document.activeElement).toBe(byId('toggle'));
    });
  });
});
