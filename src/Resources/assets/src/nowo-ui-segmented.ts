/**
 * Segmented control (2.4, partial `segmented`): a two-option choice backed by one checkbox (e.g. *All issues* /
 * *Involved only*). Both options stay visible as `role="radio"` buttons inside a `role="radiogroup"`
 * (`[data-nowo-ui-segmented]`); the selected one gets `aria-checked="true"`, `is-active` and the roving
 * `tabindex="0"`. Choosing an option sets the checkbox (`[data-nowo-ui-segmented-input]`) and dispatches a bubbling
 * `change`, so Live Component `data-model` bindings and the form submission keep the checkbox field. Arrow keys move
 * and select like a native radio group; Home / End pick the first / last option. Options:
 * `[data-nowo-ui-segmented-option][data-nowo-ui-segmented-checked="true|false"]`. Delegated listeners (works for
 * markup re-rendered by Turbo / Live Components), no inline handlers (CSP). Ported from the symfony-beacon
 * `checkbox_segment` Stimulus controller.
 */

export const GROUP = '[data-nowo-ui-segmented]';
export const OPTION = '[data-nowo-ui-segmented-option]';
export const INPUT = '[data-nowo-ui-segmented-input]';

function optionsOf(group: Element): HTMLElement[] {
  return [...group.querySelectorAll<HTMLElement>(OPTION)].filter((option) => option.closest(GROUP) === group);
}

function checkedOf(option: HTMLElement): boolean {
  return option.getAttribute('data-nowo-ui-segmented-checked') === 'true';
}

/** Marks the option matching `checked` selected and syncs the checkbox (dispatching `change` when it changes). */
export function applySegmented(group: Element, checked: boolean): void {
  for (const option of optionsOf(group)) {
    const selected = checkedOf(option) === checked;
    option.setAttribute('aria-checked', selected ? 'true' : 'false');
    option.tabIndex = selected ? 0 : -1;
    option.classList.toggle('is-active', selected);
  }
  const input = group.querySelector<HTMLInputElement>(INPUT);
  if (input && input.checked !== checked) {
    input.checked = checked;
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }
}

function eventOption(event: Event): { group: Element; option: HTMLElement } | null {
  const target = event.target;
  const option = target instanceof Element ? target.closest<HTMLElement>(OPTION) : null;
  const group = option?.closest(GROUP);
  return option && group ? { group, option } : null;
}

function onClick(event: MouseEvent): void {
  const hit = eventOption(event);
  if (hit) {
    event.preventDefault();
    applySegmented(hit.group, checkedOf(hit.option));
  }
}

function onKeydown(event: KeyboardEvent): void {
  const hit = eventOption(event);
  if (!hit) {
    return;
  }
  const options = optionsOf(hit.group);
  const current = options.indexOf(hit.option);
  let next: number;
  switch (event.key) {
    case 'ArrowRight':
    case 'ArrowDown':
      next = (current + 1) % options.length;
      break;
    case 'ArrowLeft':
    case 'ArrowUp':
      next = (current - 1 + options.length) % options.length;
      break;
    case 'Home':
      next = 0;
      break;
    case 'End':
      next = options.length - 1;
      break;
    default:
      return;
  }
  event.preventDefault();
  const option = options[next];
  option.focus();
  applySegmented(hit.group, checkedOf(option));
}

let bound = false;

export function bindNowoUiSegmented(): void {
  if (bound) {
    return;
  }
  bound = true;
  document.addEventListener('click', onClick);
  document.addEventListener('keydown', onKeydown);
}

bindNowoUiSegmented();
