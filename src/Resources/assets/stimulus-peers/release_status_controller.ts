/**
 * Optional Stimulus peer — lazy release-version dialog (`release_check` feature).
 *
 * Prefer kit IIFE `nowo-ui-release.js` when no Stimulus (it skips dialogs that declare this controller).
 * Put the controller on the `<dialog>` itself: `confirm-dialog` portals the dialog to
 * `document.body`, which would detach targets from a parent controller.
 *
 * Identifier: `release-status`
 * Values: url, messages (Object) — fall back to `data-nowo-ui-release-url` / `-messages`.
 * Parts: Stimulus targets (loading, content, summary, detail, meta, error, compare, release)
 *        or `[data-nowo-ui-release-part="…"]` (Twig `_release_version.html.twig`).
 *
 *   <dialog data-controller="release-status" data-action="toggle->release-status#onToggle" …>
 */

import { Controller } from '@hotwired/stimulus';

import {
  fetchReleaseStatus,
  parseReleaseMessages,
  renderReleaseStatus,
  resolveReleaseParts,
  showReleaseError,
  showReleaseIdle,
  showReleaseLoading,
  type ReleaseMessages,
  type ReleaseParts,
} from '../src/nowo-ui-release-core';

export default class extends Controller {
  static targets = ['loading', 'content', 'error', 'summary', 'detail', 'meta', 'compare', 'release'];

  static values = {
    url: String,
    messages: Object,
  };

  declare readonly urlValue: string;
  declare readonly hasUrlValue: boolean;
  declare readonly messagesValue: ReleaseMessages;
  declare readonly hasMessagesValue: boolean;

  private loaded = false;
  private inflight: Promise<void> | null = null;

  connect(): void {
    // Start idle — only "checking" after the dialog actually opens.
    showReleaseIdle(this.parts());
  }

  /** HTMLDialogElement `toggle` — fires when open state changes (incl. after portal). */
  onToggle(): void {
    if (!(this.element instanceof HTMLDialogElement) || !this.element.open) {
      return;
    }
    void this.load();
  }

  load(): Promise<void> {
    if (this.loaded) {
      return Promise.resolve();
    }
    if (this.inflight) {
      return this.inflight;
    }

    const parts = this.parts();
    const messages = this.messages();
    const url = this.url();
    if (url === '') {
      showReleaseError(parts, messages);
      return Promise.resolve();
    }

    showReleaseLoading(parts);
    this.inflight = fetchReleaseStatus(url)
      .then((payload) => {
        renderReleaseStatus(parts, payload, messages);
        this.loaded = true;
      })
      .catch(() => {
        showReleaseError(parts, messages);
      })
      .finally(() => {
        this.inflight = null;
      });
    return this.inflight;
  }

  private url(): string {
    if (this.hasUrlValue && this.urlValue !== '') {
      return this.urlValue;
    }
    return this.element.getAttribute('data-nowo-ui-release-url') ?? '';
  }

  private messages(): ReleaseMessages {
    if (this.hasMessagesValue && Object.keys(this.messagesValue).length > 0) {
      return this.messagesValue;
    }
    return parseReleaseMessages(this.element.getAttribute('data-nowo-ui-release-messages'));
  }

  private parts(): ReleaseParts {
    const overrides: Partial<ReleaseParts> = {};
    (['loading', 'content', 'error', 'summary', 'detail', 'meta', 'compare', 'release'] as const).forEach((name) => {
      const el = this.element.querySelector(`[data-release-status-target~="${name}"]`);
      if (el instanceof HTMLElement) {
        (overrides as Record<string, HTMLElement>)[name] = el;
      }
    });
    return resolveReleaseParts(this.element, overrides);
  }
}
