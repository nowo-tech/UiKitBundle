import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as mod from './nowo-ui-secret-once';

const SECRET = 'example-one-time-value-for-tests';

function markup(attrs = '', secret = SECRET): string {
  return `
    <div class="nowo-ui-secret-once" role="group" aria-labelledby="s-label" data-nowo-ui-secret-once
      data-nowo-ui-secret-once-copied-label="Copied!" data-nowo-ui-secret-once-copy-failed-label="Nope"
      data-nowo-ui-secret-once-cleared-label="Gone" ${attrs}>
      <span id="s-label">API token</span>
      <div><code id="s" data-nowo-ui-secret-once-value>${secret}</code>
        <span data-nowo-ui-secret-once-mask><span aria-hidden="true">••••</span></span>
        <span data-nowo-ui-secret-once-cleared>Gone</span></div>
      <div data-nowo-ui-secret-once-controls hidden>
        <button type="button" aria-pressed="false" data-nowo-ui-secret-once-toggle>Show</button>
        <button type="button" data-nowo-ui-secret-once-copy>Copy</button>
        <span role="status" data-nowo-ui-secret-once-status></span>
      </div>
    </div>`;
}

function count(): number {
  return document.documentElement.outerHTML.split(SECRET).length - 1;
}

function el<T extends HTMLElement = HTMLElement>(selector: string): T {
  return document.querySelector<T>(selector)!;
}

function root(): HTMLElement {
  return el('[data-nowo-ui-secret-once]');
}

function stubClipboard(writeText: ((text: string) => Promise<void>) | undefined): ReturnType<typeof vi.fn> {
  const spy = vi.fn(writeText ?? (async () => undefined));
  Object.defineProperty(navigator, 'clipboard', { configurable: true, value: writeText ? { writeText: spy } : undefined });

  return spy;
}

function stubExecCommand(impl: (cmd: string) => boolean): ReturnType<typeof vi.fn> {
  const spy = vi.fn(impl);
  Object.defineProperty(document, 'execCommand', { configurable: true, writable: true, value: spy });

  return spy;
}

/** A `copy` event with a writable clipboardData, as a browser fires it during execCommand('copy'). */
function fireCopy(data: Record<string, string>, withData = true): boolean {
  const event = new Event('copy', { cancelable: true }) as ClipboardEvent;
  Object.defineProperty(event, 'clipboardData', {
    value: withData ? { setData: (type: string, value: string) => { data[type] = value; } } : null,
  });
  document.dispatchEvent(event);

  return event.defaultPrevented;
}

describe('nowo-ui-secret-once', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    vi.stubGlobal('requestAnimationFrame', (cb: FrameRequestCallback) => {
      cb(0);
      return 0;
    });
    mod.resetLastMessage();
  });

  afterEach(() => {
    document.body.innerHTML = '';
    vi.useRealTimers();
    vi.unstubAllGlobals();
    Reflect.deleteProperty(navigator, 'clipboard');
    Reflect.deleteProperty(document, 'execCommand');
  });

  it('masks on upgrade, shows the controls, keeps the secret once in the DOM', () => {
    document.body.innerHTML = markup();
    expect(count()).toBe(1);
    mod.bindNowoUiSecretOnce();
    expect(mod.stateOf(root())).toBe('masked');
    expect(el('[data-nowo-ui-secret-once-controls]').hidden).toBe(false);
    expect(el('[data-nowo-ui-secret-once-toggle]').getAttribute('aria-pressed')).toBe('false');
    expect(count()).toBe(1);
    // idempotent
    mod.upgrade(root());
    mod.bindNowoUiSecretOnce();
    expect(root().hasAttribute('data-nowo-ui-secret-once-ready')).toBe(true);
  });

  it('starts revealed when asked and toggles with aria-pressed', () => {
    document.body.innerHTML = markup('data-nowo-ui-secret-once-revealed');
    mod.upgradeAll();
    const toggle = el('[data-nowo-ui-secret-once-toggle]');
    expect(mod.stateOf(root())).toBe('revealed');
    expect(toggle.getAttribute('aria-pressed')).toBe('true');
    toggle.click();
    expect(mod.stateOf(root())).toBe('masked');
    expect(toggle.getAttribute('aria-pressed')).toBe('false');
    toggle.click();
    expect(mod.stateOf(root())).toBe('revealed');
    expect(count()).toBe(1);
    expect(el('[data-nowo-ui-secret-once-value]').textContent).toBe(SECRET);
  });

  it('reads no state from an unknown attribute value', () => {
    document.body.innerHTML = markup('data-nowo-ui-secret-once-state="weird"');
    expect(mod.stateOf(root())).toBeNull();
  });

  it('copies the node text with the Clipboard API and announces it', async () => {
    document.body.innerHTML = markup();
    mod.upgradeAll();
    const write = stubClipboard(async () => undefined);
    const copyBtn = el('[data-nowo-ui-secret-once-copy]');
    copyBtn.click();
    await vi.waitFor(() => expect(write).toHaveBeenCalledWith(SECRET));
    await vi.waitFor(() => expect(el('[data-nowo-ui-secret-once-status]').textContent).toBe('Copied!'));
    expect(copyBtn.hasAttribute('data-copied')).toBe(true);
    expect(count()).toBe(1);
    // a repeat gets a no-break space so VoiceOver announces it again
    await mod.copy(root());
    expect(el('[data-nowo-ui-secret-once-status]').textContent).toBe('Copied! ');
    vi.advanceTimersByTime(2000);
    expect(el('[data-nowo-ui-secret-once-status]').textContent).toBe('');
    expect(copyBtn.hasAttribute('data-copied')).toBe(false);
    expect(count()).toBe(1);
  });

  it('falls back to execCommand fed by a copy listener (no DOM copy) when the Clipboard API rejects', async () => {
    document.body.innerHTML = markup();
    mod.upgradeAll();
    stubClipboard(async () => {
      throw new Error('denied');
    });
    const data: Record<string, string> = {};
    let htmlDuringCopy = -1;
    stubExecCommand(() => {
      htmlDuringCopy = count();
      return fireCopy(data);
    });
    expect(await mod.copy(root())).toBe(true);
    expect(data['text/plain']).toBe(SECRET);
    expect(htmlDuringCopy).toBe(1);
    expect(el('[data-nowo-ui-secret-once-status]').textContent).toBe('Copied!');
  });

  it('uses the fallback at once without the Clipboard API and reports a failure', async () => {
    document.body.innerHTML = markup();
    mod.upgradeAll();
    stubClipboard(undefined);
    stubExecCommand(() => false);
    const copyBtn = el('[data-nowo-ui-secret-once-copy]');
    expect(await mod.copy(root(), copyBtn)).toBe(false);
    expect(el('[data-nowo-ui-secret-once-status]').textContent).toBe('Nope');
    expect(copyBtn.hasAttribute('data-failed')).toBe(true);
  });

  it('legacyCopy: no clipboardData or a throwing execCommand is a failure', () => {
    stubExecCommand(() => !fireCopy({}, false));
    expect(mod.legacyCopy('x')).toBe(false);
    stubExecCommand(() => {
      throw new Error('nope');
    });
    expect(mod.legacyCopy('x')).toBe(false);
  });

  it('clear_on_hide wipes the text in place, disables the buttons and announces it', async () => {
    document.body.innerHTML = markup('data-nowo-ui-secret-once-revealed data-nowo-ui-secret-once-clear-on-hide');
    mod.upgradeAll();
    const toggle = el('[data-nowo-ui-secret-once-toggle]');
    const copyBtn = el('[data-nowo-ui-secret-once-copy]');
    toggle.click();
    expect(mod.stateOf(root())).toBe('cleared');
    expect(count()).toBe(0);
    expect(el('[data-nowo-ui-secret-once-value]').textContent).toBe('');
    expect(toggle.getAttribute('aria-disabled')).toBe('true');
    expect(copyBtn.getAttribute('aria-disabled')).toBe('true');
    expect(toggle.getAttribute('aria-pressed')).toBe('false');
    expect(el('[data-nowo-ui-secret-once-status]').textContent).toBe('Gone');
    // disabled controls do nothing
    const write = stubClipboard(async () => undefined);
    copyBtn.click();
    toggle.click();
    expect(write).not.toHaveBeenCalled();
    expect(mod.stateOf(root())).toBe('cleared');
    expect(await mod.copy(root())).toBe(false);
    mod.reveal(root());
    mod.hide(root());
    expect(mod.stateOf(root())).toBe('cleared');
  });

  it('clear_after wipes the text after the delay; clearing twice is harmless', () => {
    document.body.innerHTML = markup('data-nowo-ui-secret-once-clear-after="5000"');
    mod.upgradeAll();
    vi.advanceTimersByTime(4999);
    expect(count()).toBe(1);
    vi.advanceTimersByTime(1);
    expect(count()).toBe(0);
    expect(mod.stateOf(root())).toBe('cleared');
    mod.clear(root(), false);
    expect(mod.stateOf(root())).toBe('cleared');
  });

  it('an explicit clear cancels the pending clear_after timer', () => {
    document.body.innerHTML = markup('data-nowo-ui-secret-once-clear-after="5000"');
    mod.upgradeAll();
    mod.clear(root());
    expect(count()).toBe(0);
    vi.advanceTimersByTime(6000);
    expect(mod.stateOf(root())).toBe('cleared');
  });

  it('an empty secret starts cleared; an invalid clear_after is ignored', async () => {
    document.body.innerHTML = markup('data-nowo-ui-secret-once-clear-after="soon"', '  ');
    mod.upgradeAll();
    expect(mod.stateOf(root())).toBe('cleared');
    expect(el('[data-nowo-ui-secret-once-status]').textContent).toBe('');

    document.body.innerHTML = markup('data-nowo-ui-secret-once-clear-after="soon"');
    mod.upgradeAll();
    vi.advanceTimersByTime(60_000);
    expect(mod.stateOf(root())).toBe('masked');
    // a value emptied by someone else: copy does nothing
    el('[data-nowo-ui-secret-once-value]').textContent = '';
    expect(await mod.copy(root())).toBe(false);
  });

  it('works without labels, status, controls or value', async () => {
    document.body.innerHTML = '<div data-nowo-ui-secret-once><button data-nowo-ui-secret-once-copy>Copy</button></div>';
    expect(await mod.copy(root())).toBe(false);
    mod.upgradeAll();
    expect(mod.stateOf(root())).toBe('cleared');
    mod.report(root(), 'x');

    document.body.innerHTML = `<div data-nowo-ui-secret-once data-nowo-ui-secret-once-clear-on-hide><code data-nowo-ui-secret-once-value>${SECRET}</code><button data-nowo-ui-secret-once-copy>Copy</button></div>`;
    stubClipboard(async () => undefined);
    mod.upgradeAll();
    expect(await mod.copy(root())).toBe(true);
    mod.toggle(root());
    expect(mod.stateOf(root())).toBe('revealed');
    mod.toggle(root());
    expect(mod.stateOf(root())).toBe('cleared');
    expect(count()).toBe(0);
  });

  it('upgrades a root on its first click and on turbo:load; ignores other clicks', async () => {
    mod.bindNowoUiSecretOnce();
    document.body.innerHTML = markup();
    el('[data-nowo-ui-secret-once-toggle]').click();
    expect(mod.stateOf(root())).toBe('revealed');

    document.body.innerHTML = markup();
    document.dispatchEvent(new Event('turbo:load'));
    expect(mod.stateOf(root())).toBe('masked');

    document.body.innerHTML = `${markup()}<button id="other">x</button><button id="orphan" data-nowo-ui-secret-once-toggle>y</button>`;
    el('#other').click();
    el('#orphan').click();
    document.dispatchEvent(new MouseEvent('click'));
    expect(mod.stateOf(root())).toBeNull();
  });

  it('keeps one feedback timer per root', async () => {
    document.body.innerHTML = markup();
    mod.upgradeAll();
    const status = el('[data-nowo-ui-secret-once-status]');
    mod.report(root(), 'one');
    vi.advanceTimersByTime(1500);
    mod.report(root(), 'two', el('[data-nowo-ui-secret-once-copy]'), false);
    vi.advanceTimersByTime(1500);
    expect(status.textContent).toBe('two');
    vi.advanceTimersByTime(500);
    expect(status.textContent).toBe('');
  });

  it('wipes the secret when its enclosing dialog closes (default), keeps it with keep-on-leave', () => {
    mod.bindNowoUiSecretOnce();
    document.body.innerHTML = `<dialog id="d1">${markup()}</dialog><dialog id="d2">${markup('data-nowo-ui-secret-once-keep-on-leave').replace(SECRET, 'kept-secret')}</dialog>`;
    mod.upgradeAll();
    expect(count()).toBe(1);
    el('#d1').dispatchEvent(new Event('close'));
    expect(count()).toBe(0);
    expect(mod.stateOf(el('#d1 [data-nowo-ui-secret-once]'))).toBe('cleared');
    el('#d2').dispatchEvent(new Event('close'));
    expect(document.documentElement.outerHTML).toContain('kept-secret');
    // a close event from something else is ignored
    el('#d2 [data-nowo-ui-secret-once]').dispatchEvent(new Event('close'));
    expect(document.documentElement.outerHTML).toContain('kept-secret');
  });

  it('wipes every secret on pagehide (bfcache), even before the upgrade, except keep-on-leave', () => {
    mod.bindNowoUiSecretOnce();
    document.body.innerHTML = `${markup()}${markup('data-nowo-ui-secret-once-keep-on-leave').replace(SECRET, 'kept-secret')}`;
    expect(count()).toBe(1);
    window.dispatchEvent(new Event('pagehide'));
    expect(count()).toBe(0);
    expect(document.documentElement.outerHTML).toContain('kept-secret');
    // already cleared roots are left alone (no second announcement)
    window.dispatchEvent(new Event('pagehide'));
    expect(count()).toBe(0);
  });
});
