/**
 * Optional Stimulus peer (1.17) — folds a filter card to its corner tab and an "N active filters" line.
 *
 * Folding only hides the fields (CSS: `form:has(> .nowo-ui-filters-collapse.is-collapsed)`); they stay in the form
 * with their values, so a folded card submits exactly what it shows when unfolded. The state is a cookie of
 * comma-separated page keys that `partials/_filters_collapse.html.twig` reads, so a folded card is rendered folded
 * (no unfold-then-fold flash): the controller only changes it on click.
 *
 * Identifier: `filters-collapse`
 * Targets: `toggle` (button, gets `aria-expanded` / label), `summary` (the "N active filters" line).
 * Values: `key` (page key, default empty), `cookie` (default `nowo_ui_filters_collapsed`), `cookiePath`
 *         (default `/`), `maxAge` (seconds, default one year),
 *         `messages` (`{collapse, expand, none, one, many}`; `many` gets `%count%`).
 * Actions: `filters-collapse#toggle`.
 * Root class: `is-collapsed`. Event: `filters-collapse:toggled` (`detail.collapsed`, `detail.key`, `detail.count`).
 */

import { Controller } from '@hotwired/stimulus';

import { syncIconState } from './icon_state';

export const FILTERS_COLLAPSE_COOKIE = 'nowo_ui_filters_collapsed';

/** Fields that do not count as a filter (paging, CSRF, sort). */
const IGNORED = /(^|\[)(page|per_page|_token|sort|dir)\]?$/;

type Messages = { collapse?: string; expand?: string; none?: string; one?: string; many?: string };

/** Page keys currently folded (from the cookie). */
export function foldedKeys(cookie: string = FILTERS_COLLAPSE_COOKIE): string[] {
  const raw = document.cookie.split('; ').find((part) => part.startsWith(`${cookie}=`))?.slice(cookie.length + 1) ?? '';

  return decodeURIComponent(raw).split(',').filter((key) => '' !== key);
}

/**
 * Number of fields of a form that filter the list (non-empty, checked; paging / CSRF / sort / hidden excluded). 2.15:
 * the fields inside one `[data-nowo-ui-filter-group]` element (a date range's from / to) count once together.
 */
export function activeFilterCount(form: HTMLFormElement | null): number {
  if (!form) {
    return 0;
  }
  let count = 0;
  const groups = new Set<Element>();
  for (const field of [...form.elements]) {
    if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) {
      continue;
    }
    if ('' === field.name || IGNORED.test(field.name) || field.disabled || (field instanceof HTMLInputElement && 'hidden' === field.type)) {
      continue;
    }
    const active = field instanceof HTMLInputElement && ('checkbox' === field.type || 'radio' === field.type) ? field.checked : '' !== field.value.trim();
    if (!active) {
      continue;
    }
    const group = field.closest('[data-nowo-ui-filter-group]');
    if (group) {
      if (groups.has(group)) {
        continue;
      }
      groups.add(group);
    }
    count += 1;
  }

  return count;
}

export default class FiltersCollapseController extends Controller<HTMLElement> {
  static override targets = ['toggle', 'summary'];

  static override values = {
    key: { type: String, default: '' },
    cookie: { type: String, default: FILTERS_COLLAPSE_COOKIE },
    cookiePath: { type: String, default: '/' },
    maxAge: { type: Number, default: 31536000 },
    messages: { type: Object, default: {} },
  };

  declare readonly toggleTarget: HTMLButtonElement;
  declare readonly hasSummaryTarget: boolean;
  declare readonly summaryTarget: HTMLElement;
  declare readonly keyValue: string;
  declare readonly cookieValue: string;
  declare readonly cookiePathValue: string;
  declare readonly maxAgeValue: number;
  declare readonly messagesValue: Messages;

  override connect(): void {
    // Server-rendered state: only paint the icon (no visual change).
    syncIconState('filters-collapse', this.toggleTarget, this.isCollapsed() ? 'collapsed' : 'expanded', false);
  }

  toggle(): void {
    const collapsed = !this.isCollapsed();
    this.element.classList.toggle('is-collapsed', collapsed);
    this.toggleTarget.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    const label = (collapsed ? this.messagesValue.expand : this.messagesValue.collapse) ?? '';
    if ('' !== label) {
      this.toggleTarget.setAttribute('aria-label', label);
      this.toggleTarget.title = label;
    }
    syncIconState('filters-collapse', this.toggleTarget, collapsed ? 'collapsed' : 'expanded', true);

    const count = this.activeCount();
    if (this.hasSummaryTarget) {
      const text = (0 === count ? this.messagesValue.none : 1 === count ? this.messagesValue.one : this.messagesValue.many) ?? '';
      this.summaryTarget.textContent = text.replace('%count%', String(count));
      this.summaryTarget.hidden = !collapsed;
    }
    this.remember(collapsed);
    this.dispatch('toggled', { detail: { collapsed, key: this.keyValue, count } });
  }

  isCollapsed(): boolean {
    return this.element.classList.contains('is-collapsed');
  }

  /** Number of fields of the enclosing form that filter the list. */
  activeCount(): number {
    return activeFilterCount(this.element.closest('form'));
  }

  private remember(collapsed: boolean): void {
    if ('' === this.keyValue) {
      return;
    }
    const keys = foldedKeys(this.cookieValue).filter((key) => key !== this.keyValue);
    if (collapsed) {
      keys.push(this.keyValue);
    }
    document.cookie = `${this.cookieValue}=${encodeURIComponent(keys.join(','))}; path=${this.cookiePathValue}; max-age=${this.maxAgeValue}; SameSite=Lax`;
  }
}
