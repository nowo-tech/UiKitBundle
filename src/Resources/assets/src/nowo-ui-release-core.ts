/**
 * Shared (side-effect free) logic for the release-version dialog.
 * Used by the IIFE `nowo-ui-release.ts` and the Stimulus peer `release_status_controller.ts`.
 *
 * Markup contract (see `_release_version.html.twig`):
 *   dialog[data-nowo-ui-release][data-nowo-ui-release-url][data-nowo-ui-release-messages]
 *   [data-nowo-ui-release-part="loading|content|summary|detail|meta|error|compare|release"]
 */

export type ReleaseStatusPayload = {
  status: string;
  current: string;
  latest: string | null;
  updateAvailable: boolean;
  versionsBehind: number | null;
  releaseUrl: string | null;
  compareUrl: string | null;
  checkedAt: string | null;
};

export type ReleaseMessages = Record<string, string>;

export type ReleaseParts = {
  loading: HTMLElement | null;
  content: HTMLElement | null;
  summary: HTMLElement | null;
  detail: HTMLElement | null;
  meta: HTMLElement | null;
  error: HTMLElement | null;
  compare: HTMLAnchorElement | null;
  release: HTMLAnchorElement | null;
};

const PART_NAMES: (keyof ReleaseParts)[] = [
  'loading',
  'content',
  'summary',
  'detail',
  'meta',
  'error',
  'compare',
  'release',
];

/** Replace `%name%` placeholders (Symfony translator style). */
export function interpolateReleaseMessage(template: string, vars: Record<string, string> = {}): string {
  let text = template;
  Object.keys(vars).forEach((name) => {
    text = text.split(`%${name}%`).join(vars[name]);
  });
  return text;
}

/** ATOM → `YYYY-MM-DD HH:MM UTC` (falls back to the raw string). */
export function formatReleaseCheckedAt(atom: string): string {
  const date = new Date(atom);
  if (Number.isNaN(date.getTime())) {
    return atom;
  }
  return `${date.toISOString().replace('T', ' ').slice(0, 16)} UTC`;
}

export function parseReleaseMessages(raw: string | null | undefined): ReleaseMessages {
  if (!raw) {
    return {};
  }
  try {
    const parsed: unknown = JSON.parse(raw);
    if (parsed === null || typeof parsed !== 'object' || Array.isArray(parsed)) {
      return {};
    }
    const out: ReleaseMessages = {};
    Object.entries(parsed as Record<string, unknown>).forEach(([key, value]) => {
      if (typeof value === 'string') {
        out[key] = value;
      }
    });
    return out;
  } catch {
    return {};
  }
}

export function translateRelease(messages: ReleaseMessages, key: string, vars: Record<string, string> = {}): string {
  return interpolateReleaseMessage(messages[key] ?? key, vars);
}

/** Collect `[data-nowo-ui-release-part]` nodes; explicit overrides (e.g. Stimulus targets) win. */
export function resolveReleaseParts(root: ParentNode, overrides: Partial<ReleaseParts> = {}): ReleaseParts {
  const parts = {} as ReleaseParts;
  PART_NAMES.forEach((name) => {
    const override = overrides[name];
    if (override) {
      (parts as Record<string, HTMLElement | null>)[name] = override;
      return;
    }
    const el = root.querySelector(`[data-nowo-ui-release-part="${name}"]`);
    (parts as Record<string, HTMLElement | null>)[name] = el instanceof HTMLElement ? el : null;
  });
  return parts;
}

function setHidden(el: HTMLElement | null, hidden: boolean): void {
  if (el) {
    el.hidden = hidden;
  }
}

export function showReleaseIdle(parts: ReleaseParts): void {
  setHidden(parts.loading, true);
  setHidden(parts.content, true);
  setHidden(parts.error, true);
}

export function showReleaseLoading(parts: ReleaseParts): void {
  setHidden(parts.loading, false);
  setHidden(parts.content, true);
  setHidden(parts.error, true);
}

export function showReleaseError(parts: ReleaseParts, messages: ReleaseMessages): void {
  setHidden(parts.loading, true);
  setHidden(parts.content, true);
  if (parts.error) {
    parts.error.hidden = false;
    parts.error.textContent = translateRelease(messages, 'check_unavailable');
  }
}

function setText(el: HTMLElement | null, text: string | null): void {
  if (!el) {
    return;
  }
  if (text === null) {
    el.hidden = true;
    return;
  }
  el.textContent = text;
  el.hidden = false;
}

/** Only https://github.com/… links are rendered (payload comes from the host, but stay defensive). */
function safeGithubUrl(url: string | null): string | null {
  return url !== null && url.indexOf('https://github.com/') === 0 ? url : null;
}

export function renderReleaseStatus(parts: ReleaseParts, payload: ReleaseStatusPayload, messages: ReleaseMessages): void {
  const t = (key: string, vars: Record<string, string> = {}): string => translateRelease(messages, key, vars);

  setHidden(parts.loading, true);
  setHidden(parts.error, true);
  setHidden(parts.content, false);

  if (payload.updateAvailable && payload.latest) {
    setText(parts.summary, t('update_available', { version: payload.latest }));
    setText(
      parts.detail,
      payload.versionsBehind !== null && payload.versionsBehind > 0
        ? t('versions_behind', { count: String(payload.versionsBehind) })
        : t('upgrade_hint'),
    );
  } else if (payload.status === 'ok') {
    setText(parts.summary, t('up_to_date'));
    setText(parts.detail, payload.latest ? t('latest_label', { version: payload.latest }) : null);
  } else {
    setText(parts.summary, t(payload.status === 'skipped' ? 'check_skipped' : 'check_unavailable'));
    setText(parts.detail, null);
  }

  setText(parts.meta, payload.checkedAt ? t('checked_at', { when: formatReleaseCheckedAt(payload.checkedAt) }) : null);

  const compareUrl = safeGithubUrl(payload.compareUrl);
  if (parts.compare) {
    parts.compare.hidden = compareUrl === null;
    if (compareUrl !== null) {
      parts.compare.href = compareUrl;
      parts.compare.setAttribute('data-nowo-ui-release-update', payload.updateAvailable ? 'true' : 'false');
    }
  }

  const releaseUrl = safeGithubUrl(payload.releaseUrl);
  if (parts.release) {
    const show = payload.updateAvailable && releaseUrl !== null;
    parts.release.hidden = !show;
    if (show && releaseUrl !== null) {
      parts.release.href = releaseUrl;
    }
  }
}

export async function fetchReleaseStatus(url: string): Promise<ReleaseStatusPayload> {
  const response = await fetch(url, {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
  });
  if (!response.ok) {
    throw new Error(`release status HTTP ${response.status}`);
  }
  return (await response.json()) as ReleaseStatusPayload;
}
