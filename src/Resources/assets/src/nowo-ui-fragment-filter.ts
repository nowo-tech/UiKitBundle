/**
 * The fragment filter (2.33.1): what must not reach the page from server HTML that is about to be inserted. Shared by
 * parseFragment() (remote form and plain dialogs), the `auto-refresh` peer and the `list-pagination` peer, so they
 * cannot drift apart (audit GT-L4, GT-I6). Defence in depth, not a sanitizer for user HTML: docs/USAGE.md "Fragment trust".
 *
 * Every DOM call goes through the prototype, read when the filter runs: a <form> exposes its controls as properties, so
 * a control named `attributes` or `removeAttribute` would hide the real one (GT-L3), and this module can be imported
 * where `Element` does not exist (GT-I8). No dependency, no state.
 */

/** Elements dropped from a fragment before insertion: they run code or navigate on their own (ES-I2). */
export const DROPPED_ELEMENTS = 'script, iframe[srcdoc], meta[http-equiv], base, object, embed';

/** Attributes that run a `javascript:` URL when the link or the form is used. */
const CLICK_URL_ATTRIBUTES = ['href', 'xlink:href', 'action', 'formaction'];
/** Schemes refused on those attributes. `mailto:`, `tel:` and `data:` images stay: they run nothing in the page. */
const SCRIPT_SCHEMES = ['javascript:', 'vbscript:'];

/** A caller's stricter rule: an attribute (lowercase name, raw value) to remove on top of the shared ones. */
export type AttributeRule = (name: string, value: string) => boolean;

/** The scheme of a URL as the browser reads it (blanks, tabs and case handled by the parser), null when unparsable. */
function urlScheme(value: string): string | null {
  try {
    return new URL(value, window.location.href).protocol;
  } catch {
    return null;
  }
}

/** An <iframe> whose `src` is not http(s) (`javascript:` runs on insertion, `data:` runs in its own origin). */
function isUnsafeFrame(element: Element): boolean {
  const dom = Element.prototype;
  const src = dom.matches.call(element, 'iframe') ? dom.getAttribute.call(element, 'src') : null;
  if (src === null) {
    return false;
  }
  const scheme = urlScheme(src);
  return scheme !== 'http:' && scheme !== 'https:';
}

/** An SVG <animate> / <set> that writes a link's `href`: its `to`, `from`, `by` or `values` become the URL on click. */
function isHrefAnimation(element: Element): boolean {
  const dom = Element.prototype;
  return dom.matches.call(element, 'animate, set') && /^\s*(?:[\w-]+:)?href\s*$/i.test(dom.getAttribute.call(element, 'attributeName') ?? '');
}

/** Filters one element in place; false when the element itself had to go. */
function filterElement(element: Element, extra: AttributeRule | undefined): boolean {
  const dom = Element.prototype;
  // Attributes first: a stricter rule may remove what would otherwise drop the element (`srcdoc`).
  dom.getAttributeNames.call(element).forEach((attribute) => {
    const name = attribute.toLowerCase();
    const value = dom.getAttribute.call(element, attribute) ?? '';
    if (name.startsWith('on') || (CLICK_URL_ATTRIBUTES.includes(name) && SCRIPT_SCHEMES.includes(urlScheme(value) ?? '')) || extra?.(name, value)) {
      dom.removeAttribute.call(element, attribute);
    }
  });
  if (dom.matches.call(element, DROPPED_ELEMENTS) || isUnsafeFrame(element) || isHrefAnimation(element)) {
    dom.remove.call(element);
    return false;
  }
  // querySelectorAll does not enter a <template>: a host script may clone its content later. An SVG <template> is not
  // an HTMLTemplateElement and has no content; its children are already in the walk.
  if (element instanceof HTMLTemplateElement) {
    sanitizeFragment(element.content, extra);
  }
  return true;
}

/** Filters everything in a fragment, nested <template> content included. */
export function sanitizeFragment(root: DocumentFragment, extra?: AttributeRule): void {
  root.querySelectorAll('*').forEach((element) => filterElement(element, extra));
}

const elementsOf = (root: Element | DocumentFragment): Element[] =>
  root instanceof Element ? [root, ...Element.prototype.querySelectorAll.call(root, '*')] : [...DocumentFragment.prototype.querySelectorAll.call(root, '*')];

/**
 * `document.importNode()` for a filtered node from an inert document. In Chromium and WebKit a `style` attribute that arrives
 * by cloning skips the page's CSP, so a fetched `style=` would apply under `style-src-attr 'none'`. Each attribute is set in
 * the page's document instead, where the policy decides as for any other script. The source loses its `style` attributes.
 */
export function importForPage<T extends Node>(node: T): T {
  if (!(node instanceof Element || node instanceof DocumentFragment)) {
    return Document.prototype.importNode.call(document, node, true) as T;
  }
  const dom = Element.prototype;
  const styles = new Map<number, string>();
  elementsOf(node).forEach((element, index) => {
    const value = dom.getAttribute.call(element, 'style');
    if (value !== null) {
      styles.set(index, value);
      dom.removeAttribute.call(element, 'style');
    }
  });
  const copy = Document.prototype.importNode.call(document, node, true) as Element | DocumentFragment;
  const copies = elementsOf(copy);
  styles.forEach((value, index) => dom.setAttribute.call(copies[index]!, 'style', value));
  return copy as unknown as T;
}

/**
 * Filters an element and its descendants. False when the element itself had to go (a <base>, a `meta` refresh…): it is
 * then detached and the caller must not insert it.
 */
export function sanitizeElement(root: Element, extra?: AttributeRule): boolean {
  if (!filterElement(root, extra)) {
    return false;
  }
  Element.prototype.querySelectorAll.call(root, '*').forEach((element) => filterElement(element, extra));
  return true;
}
