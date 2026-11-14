/**
 * Keyboard shortcut helpers shared by the side panel (2.25) and the rail toggle (2.48): side-effect free, so an IIFE
 * entry can import them without binding another runtime.
 */

export type Shortcut = { ctrl: boolean; alt: boolean; shift: boolean; meta: boolean; key: string };

/** Parses `Alt+Shift+C` (modifiers Control / Ctrl, Alt, Shift, Meta; the last part is the key). */
export function parseShortcut(value: string | null | undefined): Shortcut | null {
  const parts = (value ?? '').split('+').map((part) => part.trim()).filter((part) => part !== '');
  const key = parts.pop();
  if (!key) return null;
  const mods = parts.map((part) => part.toLowerCase());
  return { ctrl: mods.includes('control') || mods.includes('ctrl'), alt: mods.includes('alt'), shift: mods.includes('shift'), meta: mods.includes('meta'), key: key.toLowerCase() };
}

/** Whether a key event is the shortcut (letters and digits by physical key, so Alt / Option layouts still match). */
export function matchesShortcut(event: KeyboardEvent, shortcut: Shortcut): boolean {
  if (event.ctrlKey !== shortcut.ctrl || event.altKey !== shortcut.alt || event.shiftKey !== shortcut.shift || event.metaKey !== shortcut.meta) return false;
  if (/^[a-z]$/.test(shortcut.key)) return event.code === `Key${shortcut.key.toUpperCase()}` || event.key.toLowerCase() === shortcut.key;
  if (/^[0-9]$/.test(shortcut.key)) return event.code === `Digit${shortcut.key}` || event.key === shortcut.key;
  return event.key.toLowerCase() === shortcut.key;
}
