/**
 * Remote form dialog core (2.28): a form page loaded into a native <dialog> and posted back with `fetch`. Shared by
 * the IIFE mode of nowo-ui-dialog.js (`data-nowo-ui-dialog-form`) and the `remote-form-dialog` Stimulus peer.
 *
 * Contract (docs/USAGE.md "Remote form dialog"):
 * - open: GET the opener's same-origin href with `X-Nowo-Dialog: 1` (+ `X-Requested-With`, `Accept: text/html`),
 *   `mode: 'same-origin'`, `redirect: 'manual'`; a 200 HTML fragment fills the body, anything else closes the dialog
 *   and follows the link (the full page is the fallback);
 * - submit (POST forms only, same-origin action): `204` or a valid `X-Nowo-Redirect` → cancelable `saved`, then close
 *   and go there (reload without a valid target); `422` / 200 HTML → re-render and focus the first invalid control;
 *   errors → keep the typed form with a `role="alert"` line;
 * - close: abort the request, empty the body (Stimulus controllers of the fragment disconnect), release the overlay,
 *   return focus to the opener.
 * - footer (2.29): when the dialog has a `[data-nowo-ui-dialog-footer]` (partial `remote_form_dialog`), the fragment's
 *   form actions (`[data-nowo-ui-dialog-actions]`, else the last `.nowo-ui-form__actions` of a form) move into it and
 *   each control there is bound to its form with `form="…"`, so the submit stays visible while the body scrolls; a
 *   form with `data-nowo-ui-dialog-keep-actions` keeps them in place.
 * Redirect targets are path-absolute same-origin URLs only (never absolute, never `//` or `\`). Titles and messages
 * are `textContent`; the server's fragment is parsed in an inert <template> and its <script> elements are dropped.
 */

import { overlayRegistry, type OverlayHandle } from './nowo-ui-overlay';

export const REQUEST_HEADER = 'X-Nowo-Dialog';
export const REDIRECT_HEADER = 'X-Nowo-Redirect';
export const ALERT_ATTR = 'data-nowo-ui-remote-form-alert';
export const NATIVE_ATTR = 'data-nowo-ui-remote-form-native';
export const FOOTER_ATTR = 'data-nowo-ui-dialog-footer';
export const ACTIONS_ATTR = 'data-nowo-ui-dialog-actions';
export const KEEP_ACTIONS_ATTR = 'data-nowo-ui-dialog-keep-actions';
const FORM_CONTROLS = 'button, input, select, textarea, output, fieldset, object';
let formSequence = 0;
const MAX_TARGET = 2048;
const SNIFF = 1024;

export type RemoteFormEvent = 'loaded' | 'invalid' | 'saved' | 'failed' | 'closed';

/** Page navigation (separate object so tests can observe it). */
export const remoteNavigation = {
  assign(url: string): void {
    window.location.assign(url);
  },
  reload(): void {
    window.location.reload();
  },
};

/**
 * Whether a raw path (before `?` / `#`) holds a dot segment (`.`, `..`, or their `%2e` forms, either case), or turns
 * into `//…` / `/\…` once decoded. Browsers normalise those segments, so `/.//evil.example` would resolve to
 * `//evil.example` (protocol-relative, another origin): such targets are refused, never normalised (ES-M1).
 */
export function hasUnsafeSegments(rawPath: string): boolean {
  // An encoded dot has no business in a redirect path (KIT-68): refused anywhere, either case.
  if (/%2e/i.test(rawPath)) {
    return true;
  }
  let decoded: string;
  try {
    decoded = decodeURIComponent(rawPath);
  } catch {
    return true;
  }
  if (decoded.startsWith('//') || decoded.startsWith('/\\') || decoded.includes('\\')) {
    return true;
  }
  return [rawPath, decoded].some((path) => path.split('/').some((segment) => /^(\.|%2e){1,2}$/i.test(segment)));
}

/**
 * A redirect target the dialog may follow: a path-absolute URL (one leading `/`, no `//`, no backslash, no control
 * character, no dot segment, at most 2048 characters) that stays on this origin. Returns path + query + hash, or null.
 */
export function sameOriginPath(value: string | null | undefined): string | null {
  if (typeof value !== 'string' || value === '' || value.length > MAX_TARGET) {
    return null;
  }
  // eslint-disable-next-line no-control-regex
  if (!value.startsWith('/') || value.startsWith('//') || value.includes('\\') || /[\x00-\x1F\x7F]/.test(value)) {
    return null;
  }
  const rawPath = value.split(/[?#]/, 1)[0] ?? '';
  if (hasUnsafeSegments(rawPath)) {
    return null;
  }
  try {
    const url = new URL(value, window.location.origin);
    if (url.origin !== window.location.origin || url.pathname.startsWith('//') || url.pathname.startsWith('/\\')) {
      return null;
    }
    return `${url.pathname}${url.search}${url.hash}`;
  } catch {
    return null;
  }
}

/** Same-origin absolute URL of a fetch target (opener href, form action), null for another origin or garbage. */
export function sameOriginHref(value: string | null | undefined): string | null {
  if (typeof value !== 'string' || value.trim() === '') {
    return null;
  }
  try {
    const url = new URL(value, window.location.href);
    return url.origin === window.location.origin && (url.protocol === 'http:' || url.protocol === 'https:') ? url.href : null;
  } catch {
    return null;
  }
}

/** Whether a response body is a fragment (HTML, not a whole document a server answered by ignoring the header). */
export function isFragment(contentType: string | null, text: string): boolean {
  if (!(contentType ?? '').toLowerCase().startsWith('text/html')) {
    return false;
  }
  const head = text.slice(0, SNIFF).toLowerCase();
  return !head.includes('<html') && !head.includes('<!doctype');
}

/** Elements dropped from a fragment before insertion: they run code or navigate on their own (ES-I2). */
export const DROPPED_ELEMENTS = 'script, iframe[srcdoc], meta[http-equiv], base, object, embed';

/**
 * Parses server HTML in an inert template and strips what could run or navigate on insertion: <script>,
 * `iframe[srcdoc]`, `meta[http-equiv]` (refresh), <base>, <object> / <embed>, and every inline `on*` handler. The
 * fragment must still be the server's own trusted form (same trust as the page; keep a `script-src` CSP): this is
 * defence in depth, not a sanitizer for user HTML.
 */
export function parseFragment(html: string): DocumentFragment {
  const template = document.createElement('template');
  template.innerHTML = html;
  template.content.querySelectorAll(DROPPED_ELEMENTS).forEach((element) => element.remove());
  template.content.querySelectorAll('*').forEach((element) => {
    Array.from(element.attributes).forEach((attribute) => {
      if (attribute.name.toLowerCase().startsWith('on')) {
        element.removeAttribute(attribute.name);
      }
    });
  });
  return template.content;
}

/** The submit target of a form and its submitter: action, method and encoding, honouring `formaction` & co. */
export interface SubmitTarget {
  action: string | null;
  method: string;
  enctype: string;
}

/** Resolves where and how a submit goes (submitter `formaction` / `formmethod` / `formenctype` win, as natively). */
export function submitTarget(form: HTMLFormElement, submitter: HTMLElement | null, fallbackAction: string | null): SubmitTarget {
  const button = submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement ? submitter : null;
  const rawAction = button?.hasAttribute('formaction') ? button.getAttribute('formaction') : form.getAttribute('action');
  const action =
    rawAction === null || rawAction.trim() === ''
      ? fallbackAction
      : button?.hasAttribute('formaction')
        ? button.formAction
        : form.action;
  const method = (button?.hasAttribute('formmethod') ? button.formMethod : form.method).toLowerCase();
  const enctype = (button?.hasAttribute('formenctype') ? button.formEnctype : form.enctype).toLowerCase();
  return { action, method, enctype };
}

/** FormData with the submitter's `name=value`, like a native submit (`new FormData(form, submitter)` when supported). */
export function formDataWith(form: HTMLFormElement, submitter: HTMLElement | null): FormData {
  const button = submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement ? submitter : null;
  if (button) {
    try {
      const native = new FormData(form, button);
      if (button.name === '' || native.getAll(button.name).includes(button.value)) {
        return native;
      }
    } catch {
      // older engines: fall back below
    }
  }
  const data = new FormData(form);
  if (button && button.name !== '' && !button.disabled) {
    data.append(button.name, button.value);
  }
  return data;
}

/** The first invalid control: `[aria-invalid="true"]`, then the host selector. */
export function firstInvalid(root: ParentNode, extra = ''): HTMLElement | null {
  const own = root.querySelector<HTMLElement>('[aria-invalid="true"]');
  if (own || extra.trim() === '') {
    return own;
  }
  try {
    return root.querySelector<HTMLElement>(extra);
  } catch {
    return null;
  }
}

/** Whether an Escape aimed at the dialog belongs to a nested layer that must close first. */
export function nestedLayerOwnsEscape(dialog: HTMLDialogElement, active: Element | null): boolean {
  if (!(active instanceof Element) || !dialog.contains(active)) {
    return false;
  }
  if (active.closest('.ts-wrapper.dropdown-active')) {
    return true;
  }
  let layer: Element | null = null;
  try {
    layer = active.closest('[popover]:popover-open');
  } catch {
    layer = null;
  }
  const inner = active.closest('dialog[open]');
  return (layer !== null && dialog.contains(layer)) || (inner !== null && inner !== dialog);
}

/**
 * Moves the fragment's form actions into the dialog footer (2.29): the explicit `[data-nowo-ui-dialog-actions]`
 * container, else the last `.nowo-ui-form__actions` inside a form, unless its form carries
 * `data-nowo-ui-dialog-keep-actions`. Every form control moved is bound to that form with `form="<id>"`, so clicking
 * the submit in the footer still submits the form in the body (and the `submit` event still fires there). An
 * anonymous form gets a generated id; so does a form whose id is not its own in the document (another element of the
 * page holds it first), and the fragment's `form="<old id>"` references are rebound to the new id. The actions move
 * only when every bound control then resolves to that form (`control.form === form`); otherwise they stay in place.
 * The footer is emptied and hidden first; it is shown only when it received something. Returns whether actions were
 * moved.
 */
export function liftActions(body: HTMLElement, footer: HTMLElement | null): boolean {
  if (!footer) {
    return false;
  }
  footer.replaceChildren();
  footer.hidden = true;
  const explicit = body.querySelector<HTMLElement>(`[${ACTIONS_ATTR}]`);
  const candidates = explicit ? [explicit] : Array.from(body.querySelectorAll<HTMLElement>('form .nowo-ui-form__actions'));
  const actions = candidates.filter((element) => !element.closest(`[${KEEP_ACTIONS_ATTR}]`)).pop();
  if (!actions || !actions.hasChildNodes()) {
    return false;
  }
  const form = actions.closest('form');
  const bound: HTMLElement[] = [];
  if (form) {
    ensureOwnFormId(form, body);
    actions.querySelectorAll<HTMLElement>(FORM_CONTROLS).forEach((control) => {
      if (!control.hasAttribute('form')) {
        control.setAttribute('form', form.id);
        bound.push(control);
      }
    });
  }
  const nodes = Array.from(actions.childNodes);
  footer.append(...nodes);
  if (form && !bound.every((control) => (control as HTMLElement & { form?: HTMLFormElement | null }).form === form)) {
    bound.forEach((control) => control.removeAttribute('form'));
    actions.append(...nodes);
    footer.hidden = true;
    return false;
  }
  actions.remove();
  footer.hidden = false;
  return true;
}

/**
 * Gives the form an id that resolves to it in its document (2.29): kept when `getElementById()` returns the form
 * itself, else a fresh `nowo-ui-remote-form-<n>` free in the document; the fragment's `form="<old id>"` references are
 * rebound to the new id.
 */
function ensureOwnFormId(form: HTMLFormElement, body: HTMLElement): void {
  const doc = form.ownerDocument;
  const old = form.id;
  if (old !== '' && (!form.isConnected || doc.getElementById(old) === form)) {
    return;
  }
  let id: string;
  do {
    formSequence += 1;
    id = `nowo-ui-remote-form-${formSequence}`;
  } while (doc.getElementById(id) !== null);
  form.id = id;
  if (old !== '') {
    body.querySelectorAll<HTMLElement>('[form]').forEach((control) => {
      if (control.getAttribute('form') === old) {
        control.setAttribute('form', id);
      }
    });
  }
}

/** The footer of a remote form dialog (`[data-nowo-ui-dialog-footer]`, direct child), null without one. */
export function dialogFooter(dialog: HTMLDialogElement): HTMLElement | null {
  return Array.from(dialog.children).find((child): child is HTMLElement => child instanceof HTMLElement && child.hasAttribute(FOOTER_ATTR)) ?? null;
}

/** The enabled submit buttons of a form, including those bound to it with `form="…"` outside it (the footer). */
export function submitButtons(form: HTMLFormElement): Array<HTMLButtonElement | HTMLInputElement> {
  return Array.from(form.elements).filter(
    (element): element is HTMLButtonElement | HTMLInputElement =>
      ((element instanceof HTMLButtonElement && element.type === 'submit') || (element instanceof HTMLInputElement && element.type === 'submit')) && !element.disabled,
  );
}

export interface RemoteFormSettings {
  loading: string;
  error: string;
  /** Alert of a failed load when followOnLoadError is false (falls back to `error`). */
  loadError: string;
  requestHeader: string;
  redirectHeader: string;
  invalidSelector: string;
  followOnLoadError: boolean;
  closeOnBackdrop: boolean;
}

export const DEFAULT_SETTINGS: RemoteFormSettings = {
  loading: '',
  error: '',
  loadError: '',
  requestHeader: REQUEST_HEADER,
  redirectHeader: REDIRECT_HEADER,
  invalidSelector: '',
  followOnLoadError: true,
  closeOnBackdrop: false,
};

/** Emits an event; returns false when a listener prevented it (cancelable events only). */
export type RemoteFormEmitter = (name: RemoteFormEvent, detail: Record<string, unknown>, cancelable?: boolean) => boolean;

/**
 * One dialog driven as a remote form. `bind()` attaches the dialog listeners (close, cancel, backdrop click, submit
 * on the body); `destroy()` removes them.
 */
export class RemoteFormSession {
  private opener: HTMLElement | null = null;
  private controller: AbortController | null = null;
  private busy = false;
  private overlay: OverlayHandle | null = null;
  private url: string | null = null;
  private readonly onClose = (): void => this.closed();
  private readonly onCancel = (event: Event): void => {
    if (nestedLayerOwnsEscape(this.dialog, document.activeElement)) {
      event.preventDefault();
    }
  };
  private readonly onClick = (event: MouseEvent): void => {
    if (event.target !== this.dialog || !this.settings().closeOnBackdrop) {
      return;
    }
    const rect = this.dialog.getBoundingClientRect();
    if (rect.width === 0 && rect.height === 0) {
      return;
    }
    if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) {
      this.close();
    }
  };
  private readonly onSubmit = (event: Event): void => {
    void this.submit(event);
  };

  constructor(
    readonly dialog: HTMLDialogElement,
    private readonly title: () => HTMLElement | null,
    private readonly body: () => HTMLElement | null,
    private readonly settings: () => RemoteFormSettings,
    private readonly emit: RemoteFormEmitter,
    private readonly listenSubmit = true,
  ) {}

  bind(): void {
    this.dialog.addEventListener('close', this.onClose);
    this.dialog.addEventListener('cancel', this.onCancel);
    this.dialog.addEventListener('click', this.onClick);
    if (this.listenSubmit) {
      this.dialog.addEventListener('submit', this.onSubmit);
    }
  }

  destroy(): void {
    this.dialog.removeEventListener('close', this.onClose);
    this.dialog.removeEventListener('cancel', this.onCancel);
    this.dialog.removeEventListener('click', this.onClick);
    this.dialog.removeEventListener('submit', this.onSubmit);
    this.controller?.abort();
    this.overlay?.release();
    this.overlay = null;
  }

  /** Whether the dialog can run (native modal dialog support). */
  supported(): boolean {
    return typeof this.dialog.showModal === 'function';
  }

  /**
   * Opens the dialog for `href` (resolved same-origin). Returns false when nothing was done (the caller then lets the
   * link navigate).
   */
  open(href: string | null, opener: HTMLElement | null, titleText: string): boolean {
    const url = sameOriginHref(href);
    const body = this.body();
    if (!url || !body || !this.supported()) {
      return false;
    }
    this.controller?.abort();
    const controller = new AbortController();
    this.controller = controller;
    this.opener = opener;
    this.url = url;
    const title = this.title();
    if (title) {
      title.textContent = titleText;
      if (!title.hasAttribute('tabindex')) {
        title.setAttribute('tabindex', '-1');
      }
    }
    this.message(body, this.settings().loading);
    if (!this.dialog.open) {
      this.dialog.showModal();
    }
    if (!this.overlay) {
      this.overlay = overlayRegistry().acquire(this.dialog, { kind: 'dialog', inertShell: false, closeOnBackdrop: this.settings().closeOnBackdrop });
    }
    title?.focus();
    void this.load(url, controller.signal, href ?? url);
    return true;
  }

  close(): void {
    this.controller?.abort();
    if (this.dialog.open) {
      this.dialog.close();
    }
  }

  /** Handles a submit event that reached the dialog body. Returns whether it was intercepted. */
  async submit(event: Event): Promise<boolean> {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !this.dialog.open || form.hasAttribute(NATIVE_ATTR)) {
      return false;
    }
    const submitter = (event as SubmitEvent).submitter ?? null;
    // An action-less form posts to the URL loaded in the dialog, not to the host page (ES-I1).
    const target = submitTarget(form, submitter, this.url);
    const action = sameOriginHref(target.action);
    if (!action || target.method !== 'post' || target.enctype === 'text/plain') {
      return false;
    }
    event.preventDefault();
    if (this.busy) {
      return true;
    }
    const body = this.body();
    if (!body) {
      return true;
    }
    this.busy = true;
    this.controller?.abort();
    const controller = new AbortController();
    this.controller = controller;
    const buttons = submitButtons(form);
    buttons.forEach((button) => {
      button.disabled = true;
    });
    body.setAttribute('aria-busy', 'true');
    const data = formDataWith(form, submitter);
    const payload: FormData | URLSearchParams =
      target.enctype === 'application/x-www-form-urlencoded' && submitter?.hasAttribute('formenctype') ? urlEncoded(data) : data;
    try {
      const response = await fetch(action, {
        method: 'POST',
        body: payload,
        headers: this.headers(),
        credentials: 'same-origin',
        mode: 'same-origin',
        redirect: 'manual',
        signal: controller.signal,
      });
      if (controller.signal.aborted) {
        return true;
      }
      const settings = this.settings();
      // X-Nowo-Redirect counts on a 2xx answer only (ES-I5).
      const success = response.status >= 200 && response.status < 300;
      const header = response.type === 'opaqueredirect' || !success ? null : response.headers.get(settings.redirectHeader);
      const redirect = sameOriginPath(header);
      if (header !== null && redirect === null) {
        console.warn(`UiKit remote form dialog: ignored an invalid ${settings.redirectHeader} header.`);
      }
      if (response.type === 'opaqueredirect' || response.status === 204 || redirect !== null) {
        this.saved(redirect, response.status);
        return true;
      }
      const text = await response.text();
      if (controller.signal.aborted) {
        return true;
      }
      if ((response.status === 422 || response.status === 200) && isFragment(response.headers.get('content-type'), text)) {
        this.inject(body, text);
        const invalid = firstInvalid(body, settings.invalidSelector);
        (invalid ?? this.title())?.focus();
        this.emit(response.status === 422 ? 'invalid' : 'loaded', response.status === 422 ? { status: 422 } : { url: action });
        return true;
      }
      this.alert(body);
      this.emit('failed', { stage: 'submit', status: response.status });
    } catch {
      if (!controller.signal.aborted) {
        this.alert(body);
        this.emit('failed', { stage: 'submit', status: null });
      }
    } finally {
      this.busy = false;
      body.removeAttribute('aria-busy');
      buttons.forEach((button) => {
        button.disabled = false;
      });
    }
    return true;
  }

  private headers(): Record<string, string> {
    return { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest', [this.settings().requestHeader]: '1' };
  }

  private async load(url: string, signal: AbortSignal, fallback: string): Promise<void> {
    const body = this.body();
    try {
      const response = await fetch(url, { headers: this.headers(), credentials: 'same-origin', mode: 'same-origin', redirect: 'manual', signal });
      if (signal.aborted) {
        return;
      }
      const text = response.status === 200 ? await response.text() : '';
      if (signal.aborted) {
        return;
      }
      if (response.status !== 200 || !body || !isFragment(response.headers.get('content-type'), text)) {
        throw new Error(`HTTP ${response.status}`);
      }
      this.inject(body, text);
      const autofocus = body.querySelector<HTMLElement>('[autofocus]');
      (autofocus ?? this.title())?.focus();
      this.emit('loaded', { url });
    } catch {
      if (signal.aborted) {
        return;
      }
      const status = null;
      this.emit('failed', { stage: 'load', status });
      if (this.settings().followOnLoadError) {
        this.close();
        remoteNavigation.assign(fallback);
      } else if (body) {
        this.clearFooter();
        body.removeAttribute('aria-busy');
        body.replaceChildren();
        this.alert(body, true);
      }
    }
  }

  private saved(redirect: string | null, status: number): void {
    const go = this.emit('saved', { redirect, status }, true);
    this.close();
    if (!go) {
      return;
    }
    if (redirect !== null) {
      remoteNavigation.assign(redirect);
    } else {
      remoteNavigation.reload();
    }
  }

  private inject(body: HTMLElement, html: string): void {
    body.replaceChildren(parseFragment(html));
    body.removeAttribute('aria-busy');
    liftActions(body, dialogFooter(this.dialog));
  }

  /** Empties and hides the footer (loading, load error, close). */
  private clearFooter(): void {
    const footer = dialogFooter(this.dialog);
    if (footer) {
      footer.replaceChildren();
      footer.hidden = true;
    }
  }

  private message(body: HTMLElement, text: string): void {
    this.clearFooter();
    const paragraph = document.createElement('p');
    paragraph.className = 'nowo-ui-remote-form__status';
    paragraph.setAttribute('role', 'status');
    paragraph.textContent = text;
    body.replaceChildren(paragraph);
    body.setAttribute('aria-busy', 'true');
  }

  /** The save (or, with followOnLoadError false, the load) failed: one alert line on top, the form stays as typed. */
  private alert(body: HTMLElement, load = false): void {
    body.querySelector(`[${ALERT_ATTR}]`)?.remove();
    const paragraph = document.createElement('p');
    paragraph.className = 'nowo-ui-remote-form__alert';
    paragraph.setAttribute('role', 'alert');
    paragraph.setAttribute(ALERT_ATTR, load ? 'load' : 'submit');
    const settings = this.settings();
    paragraph.textContent = load && settings.loadError !== '' ? settings.loadError : settings.error;
    body.prepend(paragraph);
  }

  private closed(): void {
    // A close event queued before a reopen in the same task must not tear down the new session (ES-I3).
    if (this.dialog.open) {
      return;
    }
    this.controller?.abort();
    this.controller = null;
    this.busy = false;
    const body = this.body();
    if (body) {
      body.replaceChildren();
      body.removeAttribute('aria-busy');
    }
    this.clearFooter();
    const opener = this.opener;
    this.opener = null;
    this.url = null;
    if (opener?.isConnected) {
      opener.focus();
    }
    this.overlay?.release();
    this.overlay = null;
    this.emit('closed', {});
  }

  /** The URL loaded (or being loaded) in the dialog, null when closed. */
  currentUrl(): string | null {
    return this.url;
  }
}

/** URL-encoded body of a FormData (file entries are sent by name only, as a native urlencoded submit does). */
export function urlEncoded(data: FormData): URLSearchParams {
  const params = new URLSearchParams();
  data.forEach((value, name) => {
    params.append(name, typeof value === 'string' ? value : value.name);
  });
  return params;
}

/** The title of an opener: an explicit title, else its accessible name, else its text. */
export function openerTitle(opener: HTMLElement, explicit: string | undefined): string {
  return explicit ?? opener.getAttribute('aria-label') ?? opener.textContent?.trim() ?? '';
}

/** Whether a click should open in place (plain primary click; modified clicks keep the browser's new-tab behaviour). */
export function isPlainClick(event: Event): boolean {
  if (!(event instanceof MouseEvent)) {
    return true;
  }
  return event.button === 0 && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey;
}
