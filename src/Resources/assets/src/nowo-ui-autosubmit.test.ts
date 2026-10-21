import { afterEach, describe, expect, it, vi } from 'vitest';
import * as mod from './nowo-ui-autosubmit';

describe('nowo-ui-autosubmit', () => {
  afterEach(() => {
    document.body.innerHTML = '';
    vi.restoreAllMocks();
  });

  it('submits the form of a changed [data-nowo-ui-autosubmit] select with requestSubmit()', () => {
    document.body.innerHTML = '<form id="f"><select id="s" data-nowo-ui-autosubmit><option>10</option><option>25</option></select></form>';
    const form = document.getElementById('f') as HTMLFormElement;
    const request = vi.fn();
    form.requestSubmit = request;
    mod.bindNowoUiAutosubmit();
    mod.bindNowoUiAutosubmit();
    document.getElementById('s')!.dispatchEvent(new Event('change', { bubbles: true }));
    expect(request).toHaveBeenCalledTimes(1);
  });

  it('ignores other controls and controls outside a form', () => {
    document.body.innerHTML = '<form id="f"><select id="plain"></select></form><select id="orphan" data-nowo-ui-autosubmit></select>';
    const form = document.getElementById('f') as HTMLFormElement;
    const request = vi.fn();
    form.requestSubmit = request;
    document.getElementById('plain')!.dispatchEvent(new Event('change', { bubbles: true }));
    document.getElementById('orphan')!.dispatchEvent(new Event('change', { bubbles: true }));
    document.dispatchEvent(new Event('change'));
    expect(request).not.toHaveBeenCalled();
    expect(mod.autosubmit(document.getElementById('orphan')!)).toBe(false);
  });

  it('falls back to submit() and finds the form of a custom element through closest()', () => {
    document.body.innerHTML = '<form id="f"><div id="d" data-nowo-ui-autosubmit></div></form>';
    const form = document.getElementById('f') as HTMLFormElement;
    const submit = vi.fn();
    Object.defineProperty(form, 'requestSubmit', { value: undefined, configurable: true });
    form.submit = submit;
    expect(mod.autosubmit(document.getElementById('d')!)).toBe(true);
    expect(submit).toHaveBeenCalledTimes(1);
  });
});
