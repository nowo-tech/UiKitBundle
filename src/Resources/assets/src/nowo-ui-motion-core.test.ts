import { afterEach, describe, expect, it, vi } from 'vitest';
import { CLOSING_ATTR, afterEffect, bindMotion, closeDialogWithEffect, closeWithEffect, effectMs, finishClosing, isClosing } from './nowo-ui-motion-core';

const styled = (duration: string, delay = '0s'): void => {
  vi.spyOn(window, 'getComputedStyle').mockReturnValue({ transitionDuration: duration, transitionDelay: delay } as CSSStyleDeclaration);
};

describe('nowo-ui-motion-core', () => {
  afterEach(() => {
    vi.restoreAllMocks();
    vi.useRealTimers();
  });

  it('reads the longest transition, with delay, in s or ms', () => {
    const el = document.createElement('div');
    styled('0.15s, 200ms', '0s, 50ms');
    expect(effectMs(el)).toBe(250);
    styled('', '');
    expect(effectMs(el)).toBe(0);
    styled('bad', 'bad');
    expect(effectMs(el)).toBe(0);
  });

  it('with animations off, it runs at once', () => {
    styled('0s');
    const done = vi.fn();
    afterEffect(document.createElement('div'), done);
    expect(done).toHaveBeenCalledTimes(1);
  });

  it('waits for transitionend on the element, not on a child, and runs once', () => {
    styled('0.2s');
    const el = document.createElement('div');
    const child = document.createElement('i');
    el.append(child);
    const done = vi.fn();
    afterEffect(el, done);
    expect(done).not.toHaveBeenCalled();
    child.dispatchEvent(new Event('transitionend', { bubbles: true }));
    expect(done).not.toHaveBeenCalled();
    el.dispatchEvent(new Event('transitionend'));
    el.dispatchEvent(new Event('transitionend'));
    expect(done).toHaveBeenCalledTimes(1);
  });

  it('a timer finishes it when transitionend never fires', () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
    styled('0.2s');
    const done = vi.fn();
    afterEffect(document.createElement('div'), done);
    vi.advanceTimersByTime(249);
    expect(done).not.toHaveBeenCalled();
    vi.advanceTimersByTime(2);
    expect(done).toHaveBeenCalledTimes(1);
  });

  it('the returned function runs it now, and the timer then does nothing', () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
    styled('0.2s');
    const done = vi.fn();
    const now = afterEffect(document.createElement('div'), done);
    now();
    now();
    vi.advanceTimersByTime(1000);
    expect(done).toHaveBeenCalledTimes(1);
  });

  describe('closeWithEffect', () => {
    it('with animations off, closes at once, with nothing left behind', () => {
      styled('0s');
      const el = document.createElement('div');
      const close = vi.fn();
      closeWithEffect(el, close);
      expect(close).toHaveBeenCalledTimes(1);
      expect(el.hasAttribute(CLOSING_ATTR)).toBe(false);
      expect(isClosing(el)).toBe(false);
    });

    it('marks the fade, closes when it ends, and a second call changes nothing', () => {
      styled('0.15s');
      const el = document.createElement('div');
      const close = vi.fn();
      closeWithEffect(el, close);
      closeWithEffect(el, close);
      expect(el.hasAttribute(CLOSING_ATTR)).toBe(true);
      expect(isClosing(el)).toBe(true);
      expect(close).not.toHaveBeenCalled();
      el.dispatchEvent(new Event('transitionend'));
      expect(close).toHaveBeenCalledTimes(1);
      expect(el.hasAttribute(CLOSING_ATTR)).toBe(false);
      expect(isClosing(el)).toBe(false);
    });

    it('the timer closes it when transitionend never fires', () => {
      vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
      styled('0.15s');
      const close = vi.fn();
      closeWithEffect(document.createElement('div'), close);
      vi.advanceTimersByTime(300);
      expect(close).toHaveBeenCalledTimes(1);
    });

    it('finishClosing ends it now, for a reopen; on an idle element it does nothing', () => {
      styled('0.15s');
      const el = document.createElement('div');
      const close = vi.fn();
      finishClosing(el);
      closeWithEffect(el, close);
      finishClosing(el);
      expect(close).toHaveBeenCalledTimes(1);
      expect(isClosing(el)).toBe(false);
    });
  });

  describe('dialogs', () => {
    const dialogWith = (className: string): HTMLDialogElement => {
      const dialog = document.createElement('dialog');
      dialog.className = className;
      dialog.close = vi.fn();
      document.body.append(dialog);
      return dialog;
    };

    afterEach(() => {
      document.body.innerHTML = '';
    });

    it.each(['nowo-ui-dialog', 'nowo-ui-confirm-dialog', 'nowo-ui-confirm'])('a %s fades', (name) => {
      styled('0.15s');
      const dialog = dialogWith(name);
      closeDialogWithEffect(dialog);
      expect(dialog.close).not.toHaveBeenCalled();
      dialog.dispatchEvent(new Event('transitionend'));
      expect(dialog.close).toHaveBeenCalledTimes(1);
    });

    it.each(['nowo-ui-dialog nowo-ui-dialog--end', 'nowo-ui-dialog nowo-ui-dialog--start', 'host-dialog'])('a %s closes as before', (name) => {
      styled('0.15s');
      const dialog = dialogWith(name);
      closeDialogWithEffect(dialog);
      expect(dialog.close).toHaveBeenCalledTimes(1);
    });

    it('Escape: an unrefused cancel becomes the fade', () => {
      styled('0.15s');
      bindMotion();
      bindMotion();
      const dialog = dialogWith('nowo-ui-dialog');
      const cancel = new Event('cancel', { cancelable: true });
      dialog.dispatchEvent(cancel);
      expect(cancel.defaultPrevented).toBe(true);
      expect(dialog.hasAttribute(CLOSING_ATTR)).toBe(true);
      dialog.dispatchEvent(new Event('transitionend'));
      expect(dialog.close).toHaveBeenCalledTimes(1);
    });

    it('Escape: a listener on the dialog that refuses it keeps it open', () => {
      styled('0.15s');
      bindMotion();
      const dialog = dialogWith('nowo-ui-dialog');
      dialog.addEventListener('cancel', (e) => e.preventDefault());
      dialog.dispatchEvent(new Event('cancel', { cancelable: true }));
      expect(dialog.hasAttribute(CLOSING_ATTR)).toBe(false);
    });

    it('Escape: with animations off, or on another dialog, the browser closes it as ever', () => {
      styled('0s');
      bindMotion();
      const kit = new Event('cancel', { cancelable: true });
      dialogWith('nowo-ui-dialog').dispatchEvent(kit);
      expect(kit.defaultPrevented).toBe(false);
      styled('0.15s');
      const other = new Event('cancel', { cancelable: true });
      dialogWith('host-dialog').dispatchEvent(other);
      expect(other.defaultPrevented).toBe(false);
      const notDialog = new Event('cancel', { cancelable: true });
      document.body.dispatchEvent(notDialog);
      expect(notDialog.defaultPrevented).toBe(false);
    });
  });
});
