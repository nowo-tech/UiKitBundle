import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mountThinkingOrb } from './orb/mount';
import * as mod from './nowo-ui-orb';

function mockCanvasContext(): CanvasRenderingContext2D {
  return {
    setTransform: vi.fn(),
    clearRect: vi.fn(),
    beginPath: vi.fn(),
    arc: vi.fn(),
    fill: vi.fn(),
    stroke: vi.fn(),
    moveTo: vi.fn(),
    lineTo: vi.fn(),
    fillStyle: '',
    strokeStyle: '',
    lineWidth: 1,
    globalAlpha: 1,
  } as unknown as CanvasRenderingContext2D;
}

describe('nowo-ui-orb', () => {
  beforeEach(() => {
    vi.stubGlobal(
      'matchMedia',
      vi.fn().mockImplementation((query: string) => ({
        matches: query.includes('prefers-color-scheme: dark'),
        media: query,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
      })),
    );
    document.documentElement.setAttribute('data-theme', 'light');
    document.body.innerHTML = `
      <canvas data-nowo-ui-orb data-state="working" data-size="64" width="64" height="64"></canvas>
    `;
    const canvas = document.querySelector('canvas')!;
    vi.spyOn(canvas, 'getContext').mockReturnValue(mockCanvasContext());
  });

  afterEach(() => {
    document.body.innerHTML = '';
    document.documentElement.removeAttribute('data-theme');
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('auto-mounts data-nowo-ui-orb canvases', () => {
    mod.bindNowoUiOrb();
    expect(window.nowoUiMountOrb).toBeTypeOf('function');
    expect(window.nowoUiSetOrbState).toBeTypeOf('function');
    const canvas = document.querySelector('canvas') as HTMLCanvasElement;
    expect(canvas.getAttribute('role')).toBe('img');
    expect(canvas.getAttribute('aria-label')).toBeTruthy();
  });

  it('updates state via setOrbState', () => {
    mod.bindNowoUiOrb();
    const canvas = document.querySelector('canvas') as HTMLCanvasElement;
    mod.setOrbState(canvas, 'searching');
    expect(canvas.getAttribute('data-state')).toBe('searching');
  });

  it('mountThinkingOrb paints a static frame when reduced motion', () => {
    vi.stubGlobal(
      'matchMedia',
      vi.fn().mockImplementation((query: string) => ({
        matches: query.includes('prefers-reduced-motion'),
        media: query,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
      })),
    );
    const canvas = document.createElement('canvas');
    vi.spyOn(canvas, 'getContext').mockReturnValue(mockCanvasContext());
    document.body.appendChild(canvas);
    const handle = mountThinkingOrb(canvas, { state: 'solving', size: 64, theme: 'light' });
    expect(handle.getOptions().state).toBe('solving');
    const ctx = canvas.getContext('2d') as unknown as { clearRect: ReturnType<typeof vi.fn> };
    expect(ctx.clearRect).toHaveBeenCalled();
    handle.update({ state: 'working' });
    expect(handle.getOptions().state).toBe('working');
    handle.destroy();
  });

  it('bindNowoUiOrb is idempotent', () => {
    mod.bindNowoUiOrb();
    mod.bindNowoUiOrb();
    expect(window.nowoUiBindOrbs).toBeTypeOf('function');
  });

  it('remounts when mountOrb is called twice on the same canvas', () => {
    mod.bindNowoUiOrb();
    const canvas = document.querySelector('canvas') as HTMLCanvasElement;
    const first = mod.mountOrb(canvas, { state: 'idle' });
    const second = mod.mountOrb(canvas, { state: 'working', speed: 1.2 });
    expect(second).not.toBe(first);
    expect(second.getOptions().state).toBe('working');
  });

  it('reads speed, theme, paused and aria-label from data attributes', () => {
    document.body.innerHTML = `
      <canvas data-nowo-ui-orb data-speed="0.5" data-theme="dark" data-paused
        aria-label="Busy" width="64" height="64"></canvas>
    `;
    const canvas = document.querySelector('canvas')!;
    vi.spyOn(canvas, 'getContext').mockReturnValue(mockCanvasContext());
    const handle = mod.mountOrb(canvas);
    expect(handle.getOptions().speed).toBe(0.5);
    expect(handle.getOptions().theme).toBe('dark');
    expect(handle.getOptions().paused).toBe(true);
    expect(canvas.getAttribute('aria-label')).toBe('Busy');
    handle.destroy();
  });
});
