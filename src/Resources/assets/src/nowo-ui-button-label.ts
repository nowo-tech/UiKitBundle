/**
 * Change the label of a button without touching its icon.
 *
 * Text-only button: the whole text is replaced, as `textContent` always did. With child elements only the label changes:
 * the first match of a host's `[data-nowo-ui-label]`, the kit's `.nowo-ui-action__label`, `.nowo-ui-icon--text` or
 * `.visually-hidden`; else the last text that is not in an icon, a badge or an `aria-hidden` node. An icon-only button
 * gets no visible text; its accessible name stays with the caller's `aria-label`. A `title` follows only when it mirrors
 * the label; any other `title` is a hint and stays.
 *
 * Calls go through the prototypes, so a `<form>` target with controls named `querySelector` and the like cannot
 * clobber them.
 */

const LABEL_SELECTORS = ['[data-nowo-ui-label]', '.nowo-ui-action__label', '.nowo-ui-icon--text', '.visually-hidden'];
const NOT_A_LABEL = 'svg, i, .badge, [aria-hidden="true"], [class*="material-"]';
const ICON = 'svg, i, .nowo-ui-icon';
const SVG_TEXT = 'svg title, svg desc, svg text';

const proto = Element.prototype;

/** The accessor from the prototype chain: a named form control cannot shadow it. */
function accessor(node: Node, name: string): PropertyDescriptor | undefined {
  let current: object | null = Object.getPrototypeOf(node);
  while (current !== null) {
    const descriptor = Object.getOwnPropertyDescriptor(current, name);
    if (descriptor) {
      return descriptor;
    }
    current = Object.getPrototypeOf(current);
  }
  return undefined;
}

function setText(node: Node, value: string): void {
  accessor(node, 'textContent')?.set?.call(node, value);
}

function getText(node: Node): string {
  return String(accessor(node, 'textContent')?.get?.call(node) ?? '');
}

function childNodes(node: Node): Node[] {
  return Array.from(accessor(node, 'childNodes')?.get?.call(node) as NodeListOf<Node>);
}

function hasChildElement(button: Element): boolean {
  return proto.querySelector.call(button, '*') !== null;
}

function findLabel(button: Element): Element | null {
  for (const selector of LABEL_SELECTORS) {
    const label = proto.querySelector.call(button, selector);
    if (label !== null) {
      return label;
    }
  }
  return null;
}

/** Whether the text node sits in an icon, a badge or an aria-hidden node inside the button. */
function isOutsideLabel(text: Text, button: Element): boolean {
  let element = text.parentElement;
  while (element !== null && element !== button) {
    if (proto.matches.call(element, NOT_A_LABEL)) {
      return true;
    }
    element = element.parentElement;
  }
  return false;
}

function findLastText(button: Element): Text | null {
  const walker = document.createTreeWalker(button, NodeFilter.SHOW_TEXT);
  let last: Text | null = null;
  while (walker.nextNode()) {
    const text = walker.currentNode as Text;
    if (text.data.trim() !== '' && !isOutsideLabel(text, button)) {
      last = text;
    }
  }
  return last;
}

export function setButtonLabel(button: HTMLElement, label: string): void {
  if (!hasChildElement(button)) {
    setText(button, label);
    return;
  }

  const node = findLabel(button);
  const text = node === null ? findLastText(button) : null;
  const current = node !== null ? getText(node).trim() : text?.data.trim();

  const title = proto.getAttribute.call(button, 'title');
  if (title !== null && (title === current || title === proto.getAttribute.call(button, 'aria-label'))) {
    proto.setAttribute.call(button, 'title', label);
  }

  if (node !== null) {
    setText(node, label);
  } else if (text !== null) {
    text.data = text.data.replace(text.data.trim(), () => label);
  }
}

function isIcon(element: Element): boolean {
  return proto.matches.call(element, ICON) || (proto.querySelector.call(element, ICON) !== null && getText(element).trim() === '');
}

/**
 * For a button that must no longer hold anything but its chrome (a cleared secret): remove every child other than the
 * icon and one label node, and any text inside an inline SVG, then set the label. A text-only button ends up as the
 * label alone; an icon-only button stays without visible text unless something with text was removed.
 */
export function keepOnlyIconAndLabel(button: HTMLElement, label: string): void {
  const node = findLabel(button);
  let removedText = false;

  childNodes(button).forEach((child) => {
    if (child === node || (child instanceof Element && isIcon(child))) {
      return;
    }
    removedText = removedText || getText(child).trim() !== '';
    Node.prototype.removeChild.call(button, child);
  });
  proto.querySelectorAll.call(button, SVG_TEXT).forEach((svgText) => svgText.remove());

  if (node !== null && node.parentNode !== button) {
    Node.prototype.appendChild.call(button, node);
  }

  if (node === null && removedText && hasChildElement(button)) {
    Node.prototype.appendChild.call(button, document.createTextNode(label));
    return;
  }
  setButtonLabel(button, label);
}
