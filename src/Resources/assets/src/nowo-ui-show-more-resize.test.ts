import { describe, expect, it, vi } from 'vitest';

/** HR-I7: the width changing brings the button or takes it away. In its own file: the module binds once, with the observer it finds. */
describe('nowo-ui-show-more with a ResizeObserver', () => {
  it('follows the observer, and sets up on DOMContentLoaded while the page loads', async () => {
    const instances: { callback: ResizeObserverCallback; observed: Element[] }[] = [];
    vi.stubGlobal(
      'ResizeObserver',
      class {
        observed: Element[] = [];

        constructor(public callback: ResizeObserverCallback) {
          instances.push(this);
        }

        observe(target: Element): void {
          this.observed.push(target);
        }

        unobserve(): void {}

        disconnect(): void {}
      },
    );
    const loading = vi.spyOn(Document.prototype, 'readyState', 'get').mockReturnValue('loading');
    document.body.innerHTML = `<div id="root" data-nowo-ui-show-more><div id="content" data-nowo-ui-show-more-content>t</div>
      <button id="toggle" data-nowo-ui-show-more-toggle aria-expanded="false" hidden>More</button></div><p id="other"></p>`;
    const el = (id: string): HTMLElement => document.getElementById(id) as HTMLElement;
    const sized = (scroll: number): void => {
      Object.defineProperty(el('content'), 'scrollHeight', { configurable: true, value: scroll });
      Object.defineProperty(el('content'), 'clientHeight', { configurable: true, value: 60 });
    };
    sized(60);
    await import('./nowo-ui-show-more');
    expect(el('root').hasAttribute('data-nowo-ui-show-more-ready')).toBe(false);
    loading.mockRestore();
    document.dispatchEvent(new Event('DOMContentLoaded'));
    expect(el('root').hasAttribute('data-nowo-ui-show-more-ready')).toBe(true);
    expect(el('toggle').hidden).toBe(true);

    const observer = instances[0]!;
    expect(observer.observed).toContain(el('content'));
    const fire = (target: Element): void => observer.callback([{ target } as unknown as ResizeObserverEntry], observer as unknown as ResizeObserver);
    sized(200);
    fire(el('content'));
    expect(el('toggle').hidden).toBe(false);
    sized(60);
    fire(el('content'));
    expect(el('toggle').hidden).toBe(true);
    fire(el('other'));
    expect(el('toggle').hidden).toBe(true);
    vi.unstubAllGlobals();
  });
});
