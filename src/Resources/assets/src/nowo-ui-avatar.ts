/**
 * Avatar picture fallback (2.26, partial `avatar`): `partials/_avatar.html.twig` prints the initials and, when the URL
 * passed the policy, an `<img class="nowo-ui-avatar__image">` over them. Bundles that cannot require UiKit opt in
 * with `<img data-nowo-avatar-image>` inside their own wrapper.
 *
 * - One capture-phase `load` listener and one capture-phase `error` listener on the document (`load` / `error` do not
 *   bubble, capture still sees them): pictures in fragments fetched later work without re-binding, and there is no
 *   inline handler (CSP).
 * - `load`: the wrapper gets `is-loaded` (the CSS then hides the initials under the picture).
 * - `error`: the `<img>` is removed, the wrapper loses `has-image` / `is-loaded` and shows its initials again; the URL
 *   joins a page-lifetime set of failed URLs, and a picture with a failed URL that is inserted later is removed
 *   before it is fetched again (the set lives as long as the page).
 * - Start-up: pictures that finished before the script ran are handled (`complete`, then `naturalWidth`).
 * - Defence in depth, the same rules as the PHP policy (`isSafeAvatarUrl()`, a shared case table): any other `src` is
 *   treated as failed. It runs after the browser started an eager fetch, so it prevents lazy fetches and re-fetches of
 *   failed URLs; the server-side policy is the gate.
 */

export const AVATAR_IMAGE = 'img.nowo-ui-avatar__image, img[data-nowo-avatar-image]';

const failed = new Set<string>();

/**
 * The PHP policy (`AvatarUrlPolicy::PATTERN`) in JavaScript: 1 to 2048 UTF-8 bytes without control characters,
 * whitespace, backslash, quotes, `<`, `>` or backtick, then a same-origin `/path` (not `//`) or `https://` with a host
 * free of userinfo and IPv6 brackets and an optional port from 1 to 65535 without leading zeros. Both are tested
 * against tests/Fixtures/avatar/url-policy-cases.json.
 */
export const AVATAR_URL_PATTERN =
  /^(?=[^\u0000- \u007f\\"'<>`]+$)(?:\/(?!\/)|https:\/\/[^/?#@:[\]]+(?::(?:6553[0-5]|655[0-2][0-9]|65[0-4][0-9]{2}|6[0-4][0-9]{3}|[1-5][0-9]{4}|[1-9][0-9]{0,3}))?(?:[/?#]|$))/i;

const encoder = new TextEncoder();

/** The client-side URL check, the same rules as the server (`AvatarUrlPolicy`). */
export function isSafeAvatarUrl(url: string | null | undefined): url is string {
  return typeof url === 'string' && url !== '' && encoder.encode(url).length <= 2048 && AVATAR_URL_PATTERN.test(url);
}

/** Whether a URL failed earlier on this page. */
export function hasFailed(url: string): boolean {
  return failed.has(url);
}

/** Forgets the failed URLs (tests). */
export function resetFailedAvatars(): void {
  failed.clear();
}

function isAvatarImage(target: EventTarget | null): target is HTMLImageElement {
  return target instanceof HTMLImageElement && target.matches(AVATAR_IMAGE);
}

/** Shows the picture: the wrapper hides its initials. */
export function markLoaded(img: HTMLImageElement): void {
  img.parentElement?.classList.add('is-loaded');
}

/** Drops the picture: the wrapper shows its initials again, and the URL is not requested again on this page. */
export function fallBack(img: HTMLImageElement): void {
  const src = img.getAttribute('src');
  if (src) {
    failed.add(src);
  }
  img.parentElement?.classList.remove('has-image', 'is-loaded');
  img.remove();
}

/** Checks one picture: unsafe or already-failed URL → fallback; finished → loaded or fallback; else wait for events. */
export function settle(img: HTMLImageElement): void {
  const src = img.getAttribute('src');
  if (!isSafeAvatarUrl(src) || failed.has(src)) {
    fallBack(img);

    return;
  }
  if (img.complete) {
    if (img.naturalWidth > 0) {
      markLoaded(img);
    } else {
      fallBack(img);
    }
  }
}

/** Checks the pictures under `root` (start-up, inserted fragments). */
export function scanAvatars(root: ParentNode = document): void {
  const images: HTMLImageElement[] = root instanceof HTMLImageElement && root.matches(AVATAR_IMAGE) ? [root] : [];
  root.querySelectorAll<HTMLImageElement>(AVATAR_IMAGE).forEach((img) => images.push(img));
  images.forEach(settle);
}

function onLoad(event: Event): void {
  if (isAvatarImage(event.target)) {
    markLoaded(event.target);
  }
}

function onError(event: Event): void {
  if (isAvatarImage(event.target)) {
    fallBack(event.target);
  }
}

/** Removes inserted pictures whose URL already failed or is unsafe, before they are fetched again. */
function onMutations(records: MutationRecord[]): void {
  records.forEach((record) =>
    record.addedNodes.forEach((node) => {
      if (!(node instanceof Element)) {
        return;
      }
      const images: HTMLImageElement[] = isAvatarImage(node) ? [node] : Array.from(node.querySelectorAll<HTMLImageElement>(AVATAR_IMAGE));
      images.forEach((img) => {
        const src = img.getAttribute('src');
        if (!isSafeAvatarUrl(src) || failed.has(src)) {
          fallBack(img);
        }
      });
    }),
  );
}

let bound = false;

export function bindNowoUiAvatar(): void {
  scanAvatars();
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => scanAvatars(), { once: true });
  }
  if (bound) {
    return;
  }
  bound = true;
  document.addEventListener('load', onLoad, true);
  document.addEventListener('error', onError, true);
  if (typeof MutationObserver === 'function') {
    new MutationObserver(onMutations).observe(document.documentElement, { childList: true, subtree: true });
  }
}

bindNowoUiAvatar();
