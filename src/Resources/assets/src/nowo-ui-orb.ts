/**
 * Thinking Orbs auto-mount for [data-nowo-ui-orb] canvases.
 * Script: js/nowo-ui-orb.js
 */

import { mountThinkingOrb, type ThinkingOrbHandle, type ThinkingOrbOptions } from './orb/mount';
import type { OrbSize, OrbState, OrbTheme } from './orb/types';

declare global {
  interface Window {
    nowoUiMountOrb?: (canvas: HTMLCanvasElement, options?: ThinkingOrbOptions) => ThinkingOrbHandle;
    nowoUiSetOrbState?: (canvas: HTMLCanvasElement, state: OrbState) => void;
    nowoUiBindOrbs?: (root?: ParentNode) => void;
  }
}

const handles = new WeakMap<HTMLCanvasElement, ThinkingOrbHandle>();

function readOptions(canvas: HTMLCanvasElement): ThinkingOrbOptions {
  const sizeAttr = canvas.getAttribute('data-size');
  const speedAttr = canvas.getAttribute('data-speed');
  return {
    state: (canvas.getAttribute('data-state') as OrbState | null) ?? undefined,
    size: sizeAttr ? (Number(sizeAttr) as OrbSize) : undefined,
    theme: (canvas.getAttribute('data-theme') as OrbTheme | null) ?? undefined,
    speed: speedAttr !== null ? Number(speedAttr) : undefined,
    paused: canvas.hasAttribute('data-paused'),
    ariaLabel: canvas.getAttribute('aria-label') ?? undefined,
  };
}

export function mountOrb(canvas: HTMLCanvasElement, options?: ThinkingOrbOptions): ThinkingOrbHandle {
  const existing = handles.get(canvas);
  if (existing) {
    existing.destroy();
    handles.delete(canvas);
  }
  const handle = mountThinkingOrb(canvas, { ...readOptions(canvas), ...options });
  handles.set(canvas, handle);
  return handle;
}

export function setOrbState(canvas: HTMLCanvasElement, state: OrbState): void {
  const handle = handles.get(canvas) ?? mountOrb(canvas);
  canvas.setAttribute('data-state', state);
  handle.update({ state });
}

export function bindNowoUiOrbs(root: ParentNode = document): void {
  root.querySelectorAll<HTMLCanvasElement>('canvas[data-nowo-ui-orb]').forEach((canvas) => {
    if (!handles.has(canvas)) {
      mountOrb(canvas);
    }
  });
}

let bound = false;

export function bindNowoUiOrb(): void {
  if (!bound) {
    bound = true;
    window.nowoUiMountOrb = mountOrb;
    window.nowoUiSetOrbState = setOrbState;
    window.nowoUiBindOrbs = bindNowoUiOrbs;
  }
  bindNowoUiOrbs();
}

bindNowoUiOrb();
