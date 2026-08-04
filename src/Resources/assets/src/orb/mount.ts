/**
 * Vanilla canvas mount for Thinking Orbs.
 *
 * Animation loop adapted from thinking-orbs ThinkingOrb.tsx
 * (MIT © 2026 Jakub Antalik) — https://github.com/Jakubantalik/thinking-orbs
 */

import { MODE_DRAWS } from './engine/registry';
import { resolvePreset } from './presets';
import { prefersReducedMotion, resolveDark, watchDark, watchReducedMotion, type ThemeUnsub } from './theme';
import { DEFAULT_ORB_LABELS, type OrbSize, type OrbState, type OrbTheme } from './types';

export interface ThinkingOrbOptions {
  state?: OrbState;
  size?: OrbSize;
  theme?: OrbTheme;
  speed?: number;
  paused?: boolean;
  ariaLabel?: string;
}

export interface ThinkingOrbHandle {
  update: (patch: ThinkingOrbOptions) => void;
  destroy: () => void;
  getOptions: () => Required<Pick<ThinkingOrbOptions, 'state' | 'size' | 'theme' | 'speed' | 'paused'>>;
}

function normalizeSize(raw: unknown): OrbSize {
  return raw === 20 || raw === '20' ? 20 : 64;
}

function normalizeState(raw: unknown): OrbState {
  const s = String(raw ?? 'working');
  const allowed: OrbState[] = [
    'working',
    'searching',
    'solving',
    'listening',
    'connecting',
    'weaving',
    'composing',
    'breathing',
    'shaping',
  ];
  return (allowed.includes(s as OrbState) ? s : 'working') as OrbState;
}

function normalizeTheme(raw: unknown): OrbTheme {
  if (raw === 'dark' || raw === 'light' || raw === 'auto') {
    return raw;
  }
  return 'auto';
}

export function mountThinkingOrb(canvas: HTMLCanvasElement, options: ThinkingOrbOptions = {}): ThinkingOrbHandle {
  let state = normalizeState(options.state);
  let size = normalizeSize(options.size);
  let theme = normalizeTheme(options.theme);
  let speed = typeof options.speed === 'number' && Number.isFinite(options.speed) ? options.speed : 1;
  let paused = Boolean(options.paused);
  let ariaLabel = options.ariaLabel;

  let dark = resolveDark(theme, canvas);
  let reduced = prefersReducedMotion();
  let raf = 0;
  let running = false;
  let visible = true;
  let destroyed = false;
  let unwatchDark: ThemeUnsub = () => undefined;

  const dpr = Math.min(2, (typeof devicePixelRatio !== 'undefined' && devicePixelRatio) || 1);

  const applyCanvasChrome = (): void => {
    canvas.width = Math.round(size * dpr);
    canvas.height = Math.round(size * dpr);
    canvas.style.width = `${size}px`;
    canvas.style.height = `${size}px`;
    canvas.style.display = 'block';
    canvas.setAttribute('role', 'img');
    canvas.setAttribute('aria-label', ariaLabel ?? DEFAULT_ORB_LABELS[state]);
  };

  const paint = (tSec: number): void => {
    const ctx = canvas.getContext('2d');
    if (!ctx) {
      return;
    }
    const { mode, opts } = resolvePreset(state, size);
    const draw = MODE_DRAWS[mode];
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, size, size);
    draw(ctx, size, tSec, dark, opts);
  };

  const paintNow = (): void => {
    const { speed: baseSpeed } = resolvePreset(state, size);
    paint((performance.now() / 1000) * baseSpeed * speed);
  };

  const stop = (): void => {
    running = false;
    cancelAnimationFrame(raf);
  };

  const loop = (): void => {
    const { speed: baseSpeed } = resolvePreset(state, size);
    paint((performance.now() / 1000) * baseSpeed * speed);
    if (running) {
      raf = requestAnimationFrame(loop);
    }
  };

  const start = (): void => {
    if (destroyed || running || paused || reduced) {
      return;
    }
    running = true;
    raf = requestAnimationFrame(loop);
  };

  const restart = (): void => {
    stop();
    applyCanvasChrome();
    if (reduced) {
      paint(0.6);
      return;
    }
    paintNow();
    if (visible && document.visibilityState !== 'hidden' && !paused) {
      start();
    }
  };

  const bindTheme = (): void => {
    unwatchDark();
    unwatchDark = watchDark(theme, canvas, (next) => {
      dark = next;
      restart();
    });
  };

  applyCanvasChrome();
  bindTheme();

  const unwatchMotion = watchReducedMotion((next) => {
    reduced = next;
    restart();
  });

  const io =
    typeof IntersectionObserver !== 'undefined'
      ? new IntersectionObserver(([entry]) => {
          visible = entry.isIntersecting;
          if (visible && document.visibilityState !== 'hidden') {
            start();
          } else {
            stop();
          }
        })
      : null;
  io?.observe(canvas);

  const onVis = (): void => {
    if (document.visibilityState === 'hidden') {
      stop();
    } else if (visible) {
      start();
    }
  };
  document.addEventListener('visibilitychange', onVis);

  if (reduced) {
    paint(0.6);
  } else {
    paintNow();
    start();
  }

  return {
    update(patch: ThinkingOrbOptions): void {
      if (destroyed) {
        return;
      }
      let themeChanged = false;
      if (patch.state !== undefined) {
        state = normalizeState(patch.state);
      }
      if (patch.size !== undefined) {
        size = normalizeSize(patch.size);
      }
      if (patch.theme !== undefined) {
        theme = normalizeTheme(patch.theme);
        themeChanged = true;
      }
      if (patch.speed !== undefined && Number.isFinite(patch.speed)) {
        speed = patch.speed;
      }
      if (patch.paused !== undefined) {
        paused = Boolean(patch.paused);
      }
      if (patch.ariaLabel !== undefined) {
        ariaLabel = patch.ariaLabel;
      }
      if (themeChanged) {
        bindTheme();
        return;
      }
      restart();
    },
    destroy(): void {
      if (destroyed) {
        return;
      }
      destroyed = true;
      stop();
      io?.disconnect();
      document.removeEventListener('visibilitychange', onVis);
      unwatchDark();
      unwatchMotion();
    },
    getOptions() {
      return { state, size, theme, speed, paused };
    },
  };
}
