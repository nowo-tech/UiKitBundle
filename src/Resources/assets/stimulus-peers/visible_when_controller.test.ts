import { Application } from '@hotwired/stimulus';
import { afterEach, describe, expect, it, vi } from 'vitest';

import VisibleWhenController, { fieldElements, fieldValues, parseValues, setRowVisible } from './visible_when_controller';

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

  // 2.43.1: rules chain — a row controlled by a field inside a hidden row is hidden too (spec 222: region "Other")
  const CHAIN = `
    <form data-controller="visible-when">
      <div id="custom-row" data-nowo-ui-visible-when-field="s[region]" data-nowo-ui-visible-when-in='["other"]'>
        <input name="s[custom_region]" value="eu-x-1">
      </div>
      <div id="region-row" data-nowo-ui-visible-when-field="s[provider]" data-nowo-ui-visible-when-in='["s3"]'>
        <select name="s[region]"><option value="eu-west-1">eu</option><option value="other" selected>Other</option></select>
      </div>
      <div id="deep-row" data-nowo-ui-visible-when-field="s[custom_region]" data-nowo-ui-visible-when-in='["eu-x-1"]'></div>
      <div id="provider-row"><select name="s[provider]"><option value="s3" selected>S3</option><option value="r2">R2</option></select></div>
      <div id="a-row" data-nowo-ui-visible-when-field="s[b]" data-nowo-ui-visible-when-in='["1"]'><input name="s[a]" value="1"></div>
      <div id="b-row" data-nowo-ui-visible-when-field="s[a]" data-nowo-ui-visible-when-in='["1"]'><input name="s[b]" value="1"></div>
    </form>`;

  it('hides the dependents of a hidden controlling row, whatever the row order', async () => {
    const form = await mount(CHAIN);
    const synced = vi.fn();
    form.addEventListener('visible-when:synced', synced);
    expect(row('region-row').hidden).toBe(false);
    expect(row('custom-row').hidden).toBe(false);
    expect(row('deep-row').hidden).toBe(false);

    const provider = form.querySelector<HTMLSelectElement>('[name="s[provider]"]')!;
    provider.value = 'r2';
    provider.dispatchEvent(new Event('change', { bubbles: true }));
    expect(row('region-row').hidden).toBe(true);
    expect(row('custom-row').hidden).toBe(true);
    expect(row('custom-row').getAttribute('aria-hidden')).toBe('true');
    expect(row('deep-row').hidden).toBe(true);
    expect(synced.mock.calls[0][0].detail.hidden.map((el: HTMLElement) => el.id)).toEqual(['custom-row', 'region-row', 'deep-row']);
    // values untouched, still submitted
    expect(new FormData(form as HTMLFormElement).get('s[custom_region]')).toBe('eu-x-1');
    // a cycle of rows controlling each other keeps what their own values say
    expect(row('a-row').hidden).toBe(false);
    expect(row('b-row').hidden).toBe(false);

    provider.value = 's3';
    provider.dispatchEvent(new Event('change', { bubbles: true }));
    expect(row('region-row').hidden).toBe(false);
    expect(row('custom-row').hidden).toBe(false);
    expect(row('deep-row').hidden).toBe(false);

    const region = form.querySelector<HTMLSelectElement>('[name="s[region]"]')!;
    region.value = 'eu-west-1';
    region.dispatchEvent(new Event('change', { bubbles: true }));
    expect(row('custom-row').hidden).toBe(true);
    expect(row('deep-row').hidden).toBe(true);
    expect(row('region-row').hidden).toBe(false);
  });

  it('keeps a dependent visible while one of its controlling inputs is outside hidden rows', async () => {
    await mount(`
      <form data-controller="visible-when">
        <select name="p"><option value="x" selected>x</option><option value="y">y</option></select>
        <div id="r1" data-nowo-ui-visible-when-field="p" data-nowo-ui-visible-when-in='["y"]'><input type="radio" name="m" value="a"></div>
        <input type="radio" name="m" value="a" checked>
        <div id="dep" data-nowo-ui-visible-when-field="m" data-nowo-ui-visible-when-in='["a"]'></div>
      </form>`);
    expect(row('r1').hidden).toBe(true);
    expect(row('dep').hidden).toBe(false);
    expect(fieldElements(document.body, 'nope')).toBeNull();
  });

  it('reads radios, checkboxes, multiple selects and ids', async () => {
    document.body.innerHTML = `
      <form id="f">
        <input type="radio" name="mode" value="a"><input type="radio" name="mode" value="b" checked>
        <input type="checkbox" name="enabled" value="1">
        <select name="tags" multiple><option value="x" selected>x</option><option value="y" selected>y</option></select>
        <textarea name="note">hello</textarea>
        <input id="in-form" value="f">
      </form>
      <div id="loose"><input id="by-id" value="z"><input name="loose" value="l"></div>`;
    const form = document.getElementById('f')!;
    expect(fieldValues(form, 'mode')).toEqual(['b']);
    expect(fieldValues(form, 'enabled')).toEqual(['']);
    expect(fieldValues(form, 'tags')).toEqual(['x', 'y']);
    expect(fieldValues(form, 'note')).toEqual(['hello']);
    // audit CS I3: ids resolve inside the form only
    expect(fieldValues(form, 'in-form')).toEqual(['f']);
    expect(fieldValues(form, 'by-id')).toBeNull();
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

  // audit CS I3: a hidden required field must not block the submit; required comes back when shown, values untouched
  it('lifts required while a row is hidden and restores it when shown', () => {
    const el = document.createElement('div');
    el.innerHTML = '<input name="a" required value="kept"><input name="b">';
    setRowVisible(el, false);
    expect(el.querySelector('[name="a"]')!.hasAttribute('required')).toBe(false);
    expect(el.querySelector('[name="a"]')!.hasAttribute('data-nowo-ui-visible-when-required')).toBe(true);
    expect((el.querySelector('[name="a"]') as HTMLInputElement).value).toBe('kept');
    setRowVisible(el, true);
    expect(el.querySelector('[name="a"]')!.hasAttribute('required')).toBe(true);
    expect(el.querySelector('[name="a"]')!.hasAttribute('data-nowo-ui-visible-when-required')).toBe(false);
    expect(el.querySelector('[name="b"]')!.hasAttribute('required')).toBe(false);
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
