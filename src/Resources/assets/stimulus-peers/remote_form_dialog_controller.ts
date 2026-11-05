/**
 * Optional Stimulus peer (2.28) — a form page opened in a native <dialog> and posted back with fetch (the remote
 * form dialog of src/nowo-ui-remote-form-core.ts; same contract as the IIFE mode `data-nowo-ui-dialog-form`).
 *
 * Identifier: `remote-form-dialog` (symfony-beacon's attributes, so a host copy is replaced without markup changes).
 * Targets: `dialog` (the <dialog>), `title` (its heading, `tabindex="-1"`), `body` (receives the fragment; carries
 *          `data-action="submit->remote-form-dialog#submit"`).
 * Values: `loading`, `error`, `loadError` (texts), `requestHeader` (default X-Nowo-Dialog), `redirectHeader` (default
 *         X-Nowo-Redirect), `invalidSelector` (after `[aria-invalid="true"]`), `followOnLoadError` (default true),
 *         `closeOnBackdrop` (default false: a stray click must not lose a typed form).
 * Actions: `open` (on an opener link: `href` = the full page, `data-remote-form-dialog-title` optional), `close`,
 *          `submit`.
 * Events: `remote-form-dialog:loaded` {url}, `:invalid` {status}, `:saved` {redirect, status} (cancelable: prevent it
 *         to handle success yourself; the dialog still closes), `:failed` {stage, status}, `:closed`.
 * The partial `partials/_remote_form_dialog.html.twig` (`stimulus: true`) renders the dialog with these targets.
 */

import { Controller } from '@hotwired/stimulus';
import {
  DEFAULT_SETTINGS,
  isPlainClick,
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

  private settings(): RemoteFormSettings {
    return {
      ...DEFAULT_SETTINGS,
      loading: this.loadingValue,
      error: this.errorValue,
      loadError: this.loadErrorValue,
      requestHeader: this.requestHeaderValue || REQUEST_HEADER,
      redirectHeader: this.redirectHeaderValue || REDIRECT_HEADER,
      invalidSelector: this.invalidSelectorValue,
      followOnLoadError: this.followOnLoadErrorValue,
      closeOnBackdrop: this.closeOnBackdropValue,
    };
  }
}
