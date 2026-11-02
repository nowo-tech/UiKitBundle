import { Application } from '@hotwired/stimulus';
import { afterEach, describe, expect, it, vi } from 'vitest';

import VisibleWhenController, { fieldValues, parseValues, setRowVisible } from './visible_when_controller';

const flush = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));

let app: Application | null = null;

async function mount(html: string): Promise<HTMLElement> {
  document.body.innerHTML = html;
  app = Application.start();
  app.register('visible-when', VisibleWhenController);
  await flush();

  return document.querySelector<HTMLElement>('[data-controller="visible-when"]')!;
}

afterEach(() => {
  app?.stop();
  app = null;
  document.body.innerHTML = '';
  vi.restoreAllMocks();
});

const FORM = `
  <form data-controller="visible-when">
    <div id="provider-row"><select name="settings[provider]"><option value="smtp">SMTP</option><option value="ses" selected>SES</option><option value="null">None</option></select></div>
    <div id="host-row" class="row" data-nowo-ui-visible-when-field="settings[provider]" data-nowo-ui-visible-when-in='["smtp"]'>
      <input name="settings[smtp_host]" value="mail.example.com">
    </div>
    <div id="key-row" data-nowo-ui-visible-when-field="settings[provider]" data-nowo-ui-visible-when-in="ses, sendgrid">
      <input type="password" name="settings[api_key]" value="">
    </div>
    <div id="orphan-row" data-nowo-ui-visible-when-field="missing" data-nowo-ui-visible-when-in='["x"]'></div>
  </form>`;

const row = (id: string): HTMLElement => document.getElementById(id)!;

describe('visible-when peer', () => {
  it('hides the rows of other providers on connect and re-syncs on change, never disabling or clearing inputs', async () => {
    const form = await mount(FORM);
    const synced = vi.fn();
    form.addEventListener('visible-when:synced', synced);
    expect(row('host-row').hidden).toBe(true);
    expect(row('host-row').hasAttribute('inert')).toBe(true);
    expect(row('host-row').getAttribute('aria-hidden')).toBe('true');
    expect(row('key-row').hidden).toBe(false);
    expect(row('orphan-row').hidden).toBe(false);

    const select = form.querySelector('select')!;
    select.value = 'smtp';
    select.dispatchEvent(new Event('change', { bubbles: true }));
    expect(row('host-row').hidden).toBe(false);
    expect(row('host-row').hasAttribute('inert')).toBe(false);
    expect(row('host-row').hasAttribute('aria-hidden')).toBe(false);
    expect(row('key-row').hidden).toBe(true);
    expect(synced).toHaveBeenCalledTimes(1);

    const host = form.querySelector<HTMLInputElement>('[name="settings[smtp_host]"]')!;
    select.value = 'null';
    select.dispatchEvent(new Event('change', { bubbles: true }));
    expect(row('host-row').hidden).toBe(true);
    expect(host.disabled).toBe(false);
    expect(host.value).toBe('mail.example.com');
    // hidden fields are still submitted
    expect(new FormData(form as HTMLFormElement).get('settings[smtp_host]')).toBe('mail.example.com');
  });

  it('reads radios, checkboxes, multiple selects and ids', async () => {
    document.body.innerHTML = `
      <form id="f">
        <input type="radio" name="mode" value="a"><input type="radio" name="mode" value="b" checked>
        <input type="checkbox" name="enabled" value="1">
        <select name="tags" multiple><option value="x" selected>x</option><option value="y" selected>y</option></select>
        <textarea name="note">hello</textarea>
      </form>
      <div id="loose"><input id="by-id" value="z"><input name="loose" value="l"></div>`;
    const form = document.getElementById('f')!;
    expect(fieldValues(form, 'mode')).toEqual(['b']);
    expect(fieldValues(form, 'enabled')).toEqual(['']);
    expect(fieldValues(form, 'tags')).toEqual(['x', 'y']);
    expect(fieldValues(form, 'note')).toEqual(['hello']);
    expect(fieldValues(form, 'by-id')).toEqual(['z']);
    expect(fieldValues(document.getElementById('loose')!, 'loose')).toEqual(['l']);
    expect(fieldValues(form, 'nope')).toBeNull();
  });

  it('parses JSON and comma lists', () => {
    expect(parseValues('["smtp","ses"]')).toEqual(['smtp', 'ses']);
    expect(parseValues('[1, true]')).toEqual(['1', 'true']);
    expect(parseValues('smtp, ses')).toEqual(['smtp', 'ses']);
    expect(parseValues('[broken')).toEqual([]);
    expect(parseValues('{"a":1}')).toEqual(['{"a":1}']);
    expect(parseValues('')).toEqual([]);
    expect(parseValues(null)).toEqual([]);
  });

  it('toggles a row', () => {
    const el = document.createElement('div');
    setRowVisible(el, false);
    expect(el.outerHTML).toBe('<div hidden="" inert="" aria-hidden="true"></div>');
    setRowVisible(el, true);
    expect(el.outerHTML).toBe('<div></div>');
  });

  it('stops listening on disconnect', async () => {
    const form = await mount(FORM);
    (app!.getControllerForElementAndIdentifier(form, 'visible-when') as VisibleWhenController).disconnect();
    const select = form.querySelector('select')!;
    select.value = 'smtp';
    select.dispatchEvent(new Event('change', { bubbles: true }));
    expect(row('host-row').hidden).toBe(true);
  });
});
