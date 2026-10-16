/**
 * Icon state bridge (1.17) shared by the state-toggling peers (`responsive-table`, `filters-collapse`,
 * `disclosure-icon`).
 *
 * The peers never import an icon library: the static icons painted by Twig follow the state through CSS. A host
 * that wants animated icons installs a handler once (e.g. `installMorphiconPeers()` from
 * `assets/morphicons/index.ts`), and every peer reports its state changes to it.
 *
 * Kinds and states:
 * - `table-mode`: `scroll` | `responsive`
 * - `filters-collapse`: `expanded` | `collapsed`
 * - `disclosure`: `open` | `closed`
 */

export type IconStateKind = 'table-mode' | 'filters-collapse' | 'disclosure';

export type IconStateHandler = (kind: IconStateKind, host: HTMLElement, state: string, animate: boolean) => void;

let handler: IconStateHandler | null = null;

/** Installs (or with `null` removes) the host's icon handler. */
export function setIconStateHandler(next: IconStateHandler | null): void {
  handler = next;
}

/** Reports a state to the installed handler (no-op without one). */
export function syncIconState(kind: IconStateKind, host: HTMLElement, state: string, animate: boolean): void {
  handler?.(kind, host, state, animate);
}
