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
 */

import { Controller } from '@hotwired/stimulus';
import {
  DEFAULT_SETTINGS,
  headerName,
  isPlainClick,
  LOCATION_HEADER,
  openerTitle,
  REDIRECT_HEADER,
  RemoteFormSession,
  REQUEST_HEADER,
  type RemoteFormSettings,
} from '../src/nowo-ui-remote-form-core';

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

  private session: RemoteFormSession | null = null;

  override connect(): void {
    this.session = new RemoteFormSession(
      this.dialogTarget,
      () => (this.hasTitleTarget ? this.titleTarget : null),
      () => (this.hasBodyTarget ? this.bodyTarget : null),
      () => this.settings(),
      (name, detail, cancelable = false) => !this.dispatch(name, { detail, cancelable }).defaultPrevented,
      false,
    );
    this.session.bind();
  }

  override disconnect(): void {
    this.session?.destroy();
    this.session = null;
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

  private settings(): RemoteFormSettings {
    return {
      ...DEFAULT_SETTINGS,
      loading: this.loadingValue,
      error: this.errorValue,
      loadError: this.loadErrorValue,
      requestHeader: this.header(this.requestHeaderValue, 'request', REQUEST_HEADER),
      redirectHeader: this.header(this.redirectHeaderValue, 'redirect', REDIRECT_HEADER),
      locationHeader: this.header(this.locationHeaderValue, 'location', LOCATION_HEADER),
      invalidSelector: this.invalidSelectorValue,
      followOnLoadError: this.followOnLoadErrorValue,
      closeOnBackdrop: this.closeOnBackdropValue,
    };
  }
}
