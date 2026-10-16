/**
 * Optional Stimulus peer (1.17) — disclosure chevron that follows its toggle (`partials/_disclosure_icon.html.twig`
 * inside the toggle; CSS paints the static icon of the state, an installed icon handler animates it).
 *
 * On a `<summary>` it follows its `<details>` (`toggle` event); on any other element it follows the element's
 * `aria-expanded` (e.g. the `collapse-panel` button), so the state stays owned by whoever toggles it.
 *
 * Identifier: `disclosure-icon` (on the toggle itself).
 * Reports `disclosure` / `open` | `closed` through `icon_state.ts`.
 */

import { Controller } from '@hotwired/stimulus';

import { syncIconState } from './icon_state';

export default class DisclosureIconController extends Controller<HTMLElement> {
  private observer: MutationObserver | null = null;
  private details: HTMLDetailsElement | null = null;
  private readonly onToggle = (): void => this.sync(true);

  connect(): void {
    const parent = this.element.parentElement;
    if ('SUMMARY' === this.element.tagName && parent instanceof HTMLDetailsElement) {
      this.details = parent;
      this.details.addEventListener('toggle', this.onToggle);
    } else {
      this.observer = new MutationObserver(() => this.sync(true));
      this.observer.observe(this.element, { attributes: true, attributeFilter: ['aria-expanded'] });
    }
    this.sync(false);
  }

  disconnect(): void {
    this.details?.removeEventListener('toggle', this.onToggle);
    this.details = null;
    this.observer?.disconnect();
    this.observer = null;
  }

  isOpen(): boolean {
    return null !== this.details ? this.details.open : 'false' !== this.element.getAttribute('aria-expanded');
  }

  private sync(animate: boolean): void {
    syncIconState('disclosure', this.element, this.isOpen() ? 'open' : 'closed', animate);
  }
}
