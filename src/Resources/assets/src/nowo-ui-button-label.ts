/**
 * Change the label of a button without touching its icon.
 *
 * Text-only button: the whole text is replaced, as `textContent` always did. With child elements only the label changes:
 * the kit's label nodes (`.nowo-ui-action__label`, `.visually-hidden`, the `.nowo-ui-icon--text` of icon_set none) or a
 * host's `[data-nowo-ui-label]`, else the first text outside an `<svg>`. An icon-only button gets no visible text; its
 * accessible name stays with the caller's `aria-label`. `title`, when there is one on a button with children, follows.
 *
 * Everything goes through the prototypes, so a `<form>` target with inputs named `querySelector` and the like
 * cannot clobber these calls.
 */

const LABEL_SELECTOR = '[data-nowo-ui-label], .nowo-ui-action__label, .visually-hidden, .nowo-ui-icon--text';

/** The `textContent` setter from the prototype chain: a named form control cannot shadow it. */
function setText(node: Node, value: string): void {
  let proto: object | null = Object.getPrototypeOf(node);
  while (proto !== null) {
    const setter = Object.getOwnPropertyDescriptor(proto, 'textContent')?.set;
    if (setter) {
      setter.call(node, value);
      return;
    }
    proto = Object.getPrototypeOf(proto);
  }
}

export function setButtonLabel(button: HTMLElement, label: string): void {
  if (Element.prototype.querySelector.call(button, '*') === null) {
    setText(button, label);
    return;
  }

  if (Element.prototype.hasAttribute.call(button, 'title')) {
    Element.prototype.setAttribute.call(button, 'title', label);
  }

  const nodes = Element.prototype.querySelectorAll.call(button, LABEL_SELECTOR);
  if (nodes.length > 0) {
    nodes.forEach((node) => setText(node, label));
    return;
  }

  const walker = document.createTreeWalker(button, NodeFilter.SHOW_TEXT);
  while (walker.nextNode()) {
    const node = walker.currentNode as Text;
    if (node.data.trim() !== '' && node.parentElement?.closest('svg') == null) {
      node.data = label;
      return;
    }
  }
}
