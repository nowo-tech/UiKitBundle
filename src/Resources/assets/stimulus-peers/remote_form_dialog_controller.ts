/**
 * Optional Stimulus peer (2.28) — a form page opened in a native <dialog> and posted back with fetch (the remote
 * form dialog of src/nowo-ui-remote-form-core.ts; same contract as the IIFE mode `data-nowo-ui-dialog-form`).
 *
 * Identifier: `remote-form-dialog` (symfony-beacon's attributes, so a host copy is replaced without markup changes).
 * Targets: `dialog` (the <dialog>), `title` (its heading, `tabindex="-1"`), `body` (receives the fragment; carries
 *          `data-action="submit->remote-form-dialog#submit"`).
 * Values: `loading`, `error`, `loadError` (texts), `requestHeader` (default X-Nowo-Dialog), `redirectHeader` (default
 *         X-Nowo-Redirect), `locationHeader` (2.33, default X-Nowo-Dialog-Location), `invalidSelector` (after
 *         `[aria-invalid="true"]`), `followOnLoadError` (default true), `closeOnBackdrop` (default false: a stray
 *         click must not lose a typed form). A header value not set on the controller falls back to the dialog
 *         target's `data-nowo-ui-remote-form-*-header` (printed by the partial for configured names), then the default.
 * Actions: `open` (on an opener link: `href` = the full page, `data-remote-form-dialog-title` optional), `close`,
 *          `submit`.
 * Events: `remote-form-dialog:loaded` {url}, `:invalid` {status}, `:saved` {redirect, status} (cancelable: prevent it
 *         to handle success yourself; the dialog still closes), `:step` {url, status} (2.33, a 204 + location answer:
 *         the next step loads into the same dialog; cancelable), `:failed` {stage, status}, `:closed`.
 * The partial `partials/_remote_form_dialog.html.twig` (`stimulus: true`) renders the dialog with these targets.
 *
 * Delegated mode (2.69, `delegate` value; the partial with `runtime: 'stimulus'` or nowo_ui_kit.dialogs.runtime:
 * stimulus): the controller sits on the <dialog> itself and opens it from any opener of the page with the IIFE opener
 * attributes — `data-nowo-ui-dialog-open="<dialog id>"` + `data-nowo-ui-dialog-src` + `data-nowo-ui-dialog-form`
 * (nowo_ui_remote_form_opener(), the `confirm_dialog` partial with dialog_src, many rows sharing one shell) — exactly
 * as js/nowo-ui-dialog.js does, so the markup is the same for both runtimes. A setting whose value attribute the host
 * did not set is read from the dialog's `data-nowo-ui-remote-form-*` attributes (the IIFE settings the partial prints),
 * and every event is also dispatched as the IIFE's `nowo-ui:remote-form:<name>` on the dialog (cancelable ones count
 * as prevented when either was prevented), so host listeners work whatever the runtime.
 *
 * Runtime ownership (2.69): on connect the controller claims its dialog (`data-nowo-ui-runtime-owner="stimulus"`), so
 * js/nowo-ui-dialog.js, when also loaded, leaves the dialog's openers, close buttons and backdrop to the controller;
 * the controller closes on `[data-nowo-ui-dialog-close]` (the partial's ×) itself. Disconnecting releases it.
 */

import { Controller } from '@hotwired/stimulus';
import {
  DEFAULT_SETTINGS,
  headerName,
  isPlainClick,
  LOCATION_HEADER,
  openerTitle,
  REDIRECT_HEADER,
  remoteFormSettings,
  RemoteFormSession,
  REQUEST_HEADER,
  type RemoteFormEvent,
  type RemoteFormSettings,
} from '../src/nowo-ui-remote-form-core';
import { claim, release } from '../src/nowo-ui-runtime-owner';

export { remoteNavigation as navigation } from '../src/nowo-ui-remote-form-core';

export default class extends Controller<HTMLElement> {
  static override targets = ['dialog', 'title', 'body'];

  static override values = {
    loading: { type: String, default: '' },
    error: { type: String, default: '' },
    loadError: { type: String, default: '' },
    requestHeader: { type: String, default: REQUEST_HEADER },
    redirectHeader: { type: String, default: REDIRECT_HEADER },
    locationHeader: { type: String, default: LOCATION_HEADER },
    invalidSelector: { type: String, default: '' },
    followOnLoadError: { type: Boolean, default: true },
    closeOnBackdrop: { type: Boolean, default: false },
    /** 2.69: open from the page's IIFE-style openers of this dialog (see the module comment). */
    delegate: { type: Boolean, default: false },
  };

  declare readonly dialogTarget: HTMLDialogElement;
  declare readonly hasTitleTarget: boolean;
  declare readonly titleTarget: HTMLElement;
  declare readonly hasBodyTarget: boolean;
  declare readonly bodyTarget: HTMLElement;
  declare readonly loadingValue: string;
  declare readonly errorValue: string;
  declare readonly loadErrorValue: string;
  declare readonly requestHeaderValue: string;
  declare readonly redirectHeaderValue: string;
  declare readonly locationHeaderValue: string;
  declare readonly invalidSelectorValue: string;
  declare readonly followOnLoadErrorValue: boolean;
  declare readonly closeOnBackdropValue: boolean;
  declare readonly delegateValue: boolean;

  private session: RemoteFormSession | null = null;
  private dialogElement: HTMLDialogElement | null = null;
  private readonly onDocumentClick = (event: MouseEvent): void => this.openFromPage(event);
  private readonly onDialogClick = (event: MouseEvent): void => {
    const target = event.target;
    if (target instanceof Element && target.closest('[data-nowo-ui-dialog-close]') && this.dialogElement?.contains(target)) {
      this.close(event);
    }
  };

  override connect(): void {
    const dialog = this.dialogTarget;
    this.dialogElement = dialog;
    this.session = new RemoteFormSession(
      dialog,
      () => (this.hasTitleTarget ? this.titleTarget : null),
      () => (this.hasBodyTarget ? this.bodyTarget : null),
      () => this.settings(),
      (name, detail, cancelable = false) => this.emit(name, detail, cancelable),
      false,
    );
    this.session.bind();
    claim(dialog);
    dialog.addEventListener('click', this.onDialogClick);
    if (this.delegateValue) {
      document.addEventListener('click', this.onDocumentClick);
    }
  }

  override disconnect(): void {
    document.removeEventListener('click', this.onDocumentClick);
    this.dialogElement?.removeEventListener('click', this.onDialogClick);
    release(this.dialogElement);
    this.dialogElement = null;
    this.session?.destroy();
    this.session = null;
  }

  /** Delegated mode: a plain click on an IIFE-style opener of this dialog opens it (same rules as js/nowo-ui-dialog.js). */
  private openFromPage(event: MouseEvent): void {
    const target = event.target;
    const dialog = this.dialogElement;
    if (!(target instanceof Element) || !dialog || dialog.id === '' || !this.session) {
      return;
    }
    const opener = target.closest('[data-nowo-ui-dialog-open]');
    if (!(opener instanceof HTMLElement) || opener.getAttribute('data-nowo-ui-dialog-open') !== dialog.id) {
      return;
    }
    if (!opener.hasAttribute('data-nowo-ui-dialog-form') || !opener.hasAttribute('data-nowo-ui-dialog-src') || !isPlainClick(event)) {
      return;
    }
    if (this.session.open(opener.getAttribute('data-nowo-ui-dialog-src'), opener, openerTitle(opener, opener.getAttribute('data-nowo-ui-dialog-title') ?? undefined))) {
      event.preventDefault();
    }
  }

  /** The Stimulus event, and in delegated mode the IIFE's `nowo-ui:remote-form:<name>` too; false when prevented. */
  private emit(name: RemoteFormEvent, detail: Record<string, unknown>, cancelable: boolean): boolean {
    let go = !this.dispatch(name, { detail, cancelable }).defaultPrevented;
    if (this.delegateValue && this.dialogElement) {
      const legacy = new CustomEvent(`nowo-ui:remote-form:${name}`, { bubbles: true, cancelable, detail });
      go = this.dialogElement.dispatchEvent(legacy) && go;
    }
    return go;
  }

  open(event: Event): void {
    const trigger = event.currentTarget;
    if (!(trigger instanceof HTMLElement) || !this.session || !isPlainClick(event)) {
      return;
    }
    if (this.session.open(trigger.getAttribute('href'), trigger, openerTitle(trigger, trigger.dataset.remoteFormDialogTitle))) {
      event.preventDefault();
    }
  }

  close(event?: Event): void {
    event?.preventDefault();
    this.session?.close();
  }

  submit(event: Event): void {
    void this.session?.submit(event);
  }

  /** A header name: the controller value when set, else the dialog's `data-nowo-ui-remote-form-<role>-header`, else the default. */
  private header(value: string, role: 'request' | 'redirect' | 'location', fallback: string): string {
    // `has…Value` is always true for a value with a default: the attribute itself says whether the host set it.
    if (value !== '' && this.element.hasAttribute(`data-${this.identifier}-${role}-header-value`)) {
      return headerName(value, fallback);
    }
    return headerName(this.dialogTarget.getAttribute(`data-nowo-ui-remote-form-${role}-header`), fallback);
  }

  /** Whether a value comes from the dialog's IIFE setting: delegated mode and the host did not set the value (2.69). */
  private inherits(name: string): boolean {
    return this.delegateValue && !this.element.hasAttribute(`data-${this.identifier}-${name}-value`);
  }

  private text(name: 'loading' | 'error' | 'load-error' | 'invalid-selector', value: string, fallback: string): string {
    return this.inherits(name) ? fallback : value;
  }

  private flag(name: 'follow-on-load-error' | 'close-on-backdrop', value: boolean, fallback: boolean): boolean {
    return this.inherits(name) ? fallback : value;
  }

  private settings(): RemoteFormSettings {
    const dialog = remoteFormSettings(this.dialogTarget);
    return {
      ...DEFAULT_SETTINGS,
      loading: this.text('loading', this.loadingValue, dialog.loading),
      error: this.text('error', this.errorValue, dialog.error),
      loadError: this.text('load-error', this.loadErrorValue, dialog.loadError),
      requestHeader: this.header(this.requestHeaderValue, 'request', REQUEST_HEADER),
      redirectHeader: this.header(this.redirectHeaderValue, 'redirect', REDIRECT_HEADER),
      locationHeader: this.header(this.locationHeaderValue, 'location', LOCATION_HEADER),
      invalidSelector: this.text('invalid-selector', this.invalidSelectorValue, dialog.invalidSelector),
      followOnLoadError: this.flag('follow-on-load-error', this.followOnLoadErrorValue, dialog.followOnLoadError),
      closeOnBackdrop: this.flag('close-on-backdrop', this.closeOnBackdropValue, dialog.closeOnBackdrop),
    };
  }
}
