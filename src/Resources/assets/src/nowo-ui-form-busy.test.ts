import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as mod from './nowo-ui-form-busy';

describe('nowo-ui-form-busy', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    document.body.innerHTML = `
      <div class="nowo-ui-error-summary" tabindex="-1" data-nowo-ui-error-summary>
        <ul><li><a id="l1" href="#email">Email: required</a></li><li><a id="l2" href="#missing">x</a></li></ul>
      </div>
      <form id="f" data-nowo-ui-busy-on-submit action="/x"><input id="email" name="email"><button type="submit" id="save">Save</button></form>
      <form id="plain"><button type="submit" id="p">Go</button></form>`;
    mod.bindNowoUiFormBusy();
  });

  afterEach(() => {
    vi.useRealTimers();
    document.body.innerHTML = '';
  });

  const submit = (form: HTMLElement, submitter: HTMLElement | null = null) => {
    const event = new Event('submit', { bubbles: true, cancelable: true });
    Object.defineProperty(event, 'submitter', { value: submitter });
    form.dispatchEvent(event);
    return event;
  };

  it('focuses the error summary once on load', () => {
    const summary = document.querySelector<HTMLElement>('[data-nowo-ui-error-summary]')!;
    expect(document.activeElement).toBe(summary);
    document.getElementById('email')!.focus();
    mod.focusErrorSummary();
    expect(document.activeElement?.id).toBe('email');
  });

  it('summary links focus their field', () => {
    const click = new MouseEvent('click', { bubbles: true, cancelable: true });
    document.getElementById('l1')!.dispatchEvent(click);
    expect(click.defaultPrevented).toBe(true);
    expect(document.activeElement?.id).toBe('email');
    const missing = new MouseEvent('click', { bubbles: true, cancelable: true });
    document.getElementById('l2')!.dispatchEvent(missing);
    expect(missing.defaultPrevented).toBe(false);
    document.body.click();
    document.dispatchEvent(new MouseEvent('click'));
  });

  it('marks the submitter busy and ignores a second submit', () => {
    const form = document.getElementById('f')!;
    expect(submit(form, document.getElementById('save')).defaultPrevented).toBe(false);
    vi.runAllTimers();
    expect(document.getElementById('save')!.getAttribute('aria-busy')).toBe('true');
    expect(document.getElementById('save')!.getAttribute('aria-disabled')).toBe('true');
    expect(submit(form).defaultPrevented).toBe(true);
    window.dispatchEvent(new Event('pageshow'));
    expect(document.getElementById('save')!.hasAttribute('aria-busy')).toBe(false);
    expect(form.hasAttribute('data-nowo-ui-busy')).toBe(false);
  });

  it('falls back to the first submit button, and leaves cancelled or plain forms alone', () => {
    const form = document.getElementById('f')!;
    submit(form);
    vi.runAllTimers();
    expect(document.getElementById('save')!.getAttribute('aria-busy')).toBe('true');
    mod.clearBusy();
    form.addEventListener('submit', (e) => e.preventDefault(), { once: true });
    submit(form);
    vi.runAllTimers();
    expect(document.getElementById('save')!.hasAttribute('aria-busy')).toBe(false);
    submit(document.getElementById('plain')!);
    vi.runAllTimers();
    expect(document.getElementById('p')!.hasAttribute('aria-busy')).toBe(false);
    document.dispatchEvent(new Event('submit'));
    const bare = document.createElement('form');
    bare.setAttribute('data-nowo-ui-busy-on-submit', '');
    document.body.appendChild(bare);
    submit(bare);
    vi.runAllTimers();
    expect(bare.hasAttribute('data-nowo-ui-busy')).toBe(true);
  });
});
