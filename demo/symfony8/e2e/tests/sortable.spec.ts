import { test, expect, type Page, type Locator } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * UiKit sortable on /kit/sortable, in Chromium, Firefox and WebKit with a real mouse and keyboard: moves within
 * and between lists, placeholder, auto-scroll, Escape, refusal, revert, focus, live region, no CSP violation, axe.
 *
 * The last block sends synthetic PointerEvents with pointerType "touch" in all three engines. That checks the script's
 * logic only: no engine gesture recognizer runs. sortable-touch.spec.ts drives real touch input in Chromium.
 */
test.describe.configure({ timeout: 45_000 });

type Violation = { directive: string; sample: string };

/**
 * Records real CSP violations, from the browser's event and not from console text (Firefox words it differently). The
 * toolbar is blocked: it brings inline styles of its own, and in worker mode its request strips the response's CSP header
 * from later responses, which is why the page also has the policy in a meta tag. Hot reload could replace the page mid-drag.
 */
async function watchCsp(page: Page): Promise<() => Promise<Violation[]>> {
  await page.route('**/_wdt/**', (route) => route.abort());
  await page.route('https://cdn.jsdelivr.net/**', (route) => route.abort());
  await page.addInitScript(() => {
    const seen: { directive: string; sample: string }[] = [];
    (window as unknown as { cspSeen: typeof seen }).cspSeen = seen;
    document.addEventListener('securitypolicyviolation', (e) => seen.push({ directive: e.effectiveDirective, sample: e.sample || e.blockedURI }));
  });
  return () => page.evaluate(() => (window as unknown as { cspSeen: { directive: string; sample: string }[] }).cspSeen.filter((v) => v.directive.endsWith('-attr')));
}

const cardsIn = (page: Page, column: string): Promise<(string | null)[]> =>
  page.getByTestId(`list-${column}`).locator('> [data-nowo-ui-sortable-item]').evaluateAll((els) => els.map((el) => el.getAttribute('data-nowo-ui-sortable-item')));
const log = (page: Page): Locator => page.getByTestId('log');
/** There is one region per root parent: the one that spoke is the one with the text. */
const spoken = (page: Page, text: string): Promise<void> => expect(page.locator('[data-nowo-ui-sortable-live]').filter({ hasText: text }).first()).toBeAttached();

async function centre(locator: Locator): Promise<{ x: number; y: number }> {
  await locator.scrollIntoViewIfNeeded();
  const box = await locator.boundingBox();
  if (!box) throw new Error('no box');
  return { x: box.x + box.width / 2, y: box.y + box.height / 2 };
}

/** Near the start of an element: the middle of a card may be one of its buttons. */
async function grip(locator: Locator): Promise<{ x: number; y: number }> {
  await locator.scrollIntoViewIfNeeded();
  const box = await locator.boundingBox();
  if (!box) throw new Error('no box');
  return { x: box.x + Math.min(16, box.width / 2), y: box.y + box.height / 2 };
}

/** Presses on `from` and moves in small steps over `to`, at a fraction of its height. The button stays down. */
async function dragOver(page: Page, from: Locator, to: Locator, fraction = 0.5): Promise<void> {
  const start = await grip(from);
  await page.mouse.move(start.x, start.y);
  await page.mouse.down();
  await page.mouse.move(start.x + 3, start.y + 8, { steps: 3 });
  const box = await to.boundingBox();
  if (!box) throw new Error('no box');
  await page.mouse.move(box.x + box.width / 2, box.y + box.height * fraction, { steps: 8 });
}

test.describe('sortable', () => {
  // The first dev request warms the cache and the debug toolbar, which shifts the layout and logs its own CSP reports.
  test.beforeAll(async ({ browser }) => {
    const page = await browser.newPage();
    await page.goto('/kit/sortable');
    await page.waitForTimeout(1500);
    await page.close();
  });

  test('mouse: reorders in a list with a placeholder and a copy, and fires the events in order', async ({ page }) => {
    const csp = await watchCsp(page);
    const response = await page.goto('/kit/sortable');
    // The served demo markup, not the page: the dev toolbar has its own inline styles.
    const html = await response!.text();
    expect(html.slice(html.indexOf('<main'), html.indexOf('</main>'))).not.toContain('style="');

    await dragOver(page, page.getByTestId('card-t1'), page.getByTestId('card-t3'), 0.75);
    const placeholder = page.locator('.nowo-ui-sortable__placeholder');
    await expect(placeholder).toHaveCount(1);
    await expect(placeholder).toBeVisible();
    await expect(page.locator('.nowo-ui-sortable__ghost')).toHaveCount(1);
    await expect(page.locator('.nowo-ui-sortable__ghost')).toHaveAttribute('aria-hidden', 'true');
    await expect(page.getByTestId('list-todo')).toHaveClass(/is-drop-target/);
    // The copy keeps a colour the host set through the CSSOM (cloning its style attribute raises no CSP report).
    await expect(page.locator('.nowo-ui-sortable__ghost')).toHaveCSS('border-inline-start-color', 'rgb(37, 99, 235)');
    // Nothing moved yet.
    expect((await cardsIn(page, 'todo')).slice(0, 3)).toEqual(['t1', 't2', 't3']);
    await page.mouse.up();

    await expect(placeholder).toHaveCount(0);
    await expect(page.locator('.nowo-ui-sortable__ghost')).toHaveCount(0);
    expect((await cardsIn(page, 'todo')).slice(0, 3)).toEqual(['t2', 't3', 't1']);
    await expect(log(page)).toContainText('start cards t1 todo 0 pointer');
    await expect(log(page)).toContainText('move cards t1 todo todo 2 0 pointer');
    await expect(log(page)).toContainText('moved cards t1 todo todo 2 0 pointer');
    expect(await csp()).toEqual([]);
  });

  test('mouse: moves between lists, also to an empty list by its column', async ({ page }) => {
    const csp = await watchCsp(page);
    await page.goto('/kit/sortable');
    await dragOver(page, page.getByTestId('card-t1'), page.getByTestId('card-d2'), 0.1);
    await page.mouse.up();
    expect(await cardsIn(page, 'doing')).toEqual(['d1', 't1', 'd2']);
    await expect(log(page)).toContainText('moved cards t1 doing todo 1 0 pointer');

    // "Done" is empty: its header is part of the drop zone.
    await dragOver(page, page.getByTestId('card-t2'), page.getByTestId('column-done').locator('h2'));
    await page.mouse.up();
    expect(await cardsIn(page, 'done')).toEqual(['t2']);
    expect(await csp()).toEqual([]);
  });

  test('mouse: columns move by their handle, and cards go with them', async ({ page }) => {
    await page.goto('/kit/sortable');
    await dragOver(page, page.getByTestId('handle-done'), page.getByTestId('column-todo'), 0.5);
    const box = await page.getByTestId('column-todo').boundingBox();
    await page.mouse.move(box!.x + 5, box!.y + 20, { steps: 4 });
    await page.mouse.up();
    const order = await page.getByTestId('board').locator('> [data-nowo-ui-sortable-item]').evaluateAll((els) => els.map((el) => el.getAttribute('data-nowo-ui-sortable-item')));
    expect(order).toEqual(['done', 'todo', 'doing']);
    await expect(log(page)).toContainText('moved columns done board board 0 2 pointer');
    expect((await cardsIn(page, 'todo')).length).toBe(12);
  });

  test('mouse: a press on a card link with no movement still follows it; a drag from it does not', async ({ page }) => {
    await page.goto('/kit/sortable');
    await dragOver(page, page.getByTestId('link-t1'), page.getByTestId('card-t2'), 0.9);
    await page.mouse.up();
    await expect(page).not.toHaveURL(/#t1$/);
    expect((await cardsIn(page, 'todo')).slice(0, 2)).toEqual(['t2', 't1']);
    await page.getByTestId('link-t3').click();
    await expect(page).toHaveURL(/#t3$/);
  });

  test('mouse: dragging near the edge of a scrolling list scrolls it', async ({ page }) => {
    await page.goto('/kit/sortable');
    const list = page.getByTestId('list-todo');
    const overflow = await list.evaluate((el) => el.scrollHeight > el.clientHeight);
    expect(overflow).toBe(true);
    const box = (await list.boundingBox())!;
    const start = await grip(page.getByTestId('card-t1'));
    await page.mouse.move(start.x, start.y);
    await page.mouse.down();
    await page.mouse.move(start.x, start.y + 20, { steps: 3 });
    await page.mouse.move(box.x + box.width / 2, box.y + box.height - 4, { steps: 6 });
    await expect.poll(() => list.evaluate((el) => el.scrollTop), { timeout: 5_000 }).toBeGreaterThan(40);
    await page.mouse.up();
    const order = await cardsIn(page, 'todo');
    // The drop lands where the scrolled list was under the pointer, not at its old place.
    expect(order.indexOf('t1')).toBeGreaterThan(3);
  });

  test('Escape cancels a drag and restores the DOM', async ({ page }) => {
    await page.goto('/kit/sortable');
    const before = await cardsIn(page, 'todo');
    await dragOver(page, page.getByTestId('card-t1'), page.getByTestId('card-d2'));
    await expect(page.locator('.nowo-ui-sortable__placeholder')).toHaveCount(1);
    await page.keyboard.press('Escape');
    await expect(page.locator('.nowo-ui-sortable__placeholder, .nowo-ui-sortable__ghost')).toHaveCount(0);
    await page.mouse.up();
    expect(await cardsIn(page, 'todo')).toEqual(before);
    expect(await cardsIn(page, 'doing')).toEqual(['d1', 'd2']);
    await expect(log(page)).toContainText('cancel cards t1 todo 0 pointer escape');
    await spoken(page, 'cancelled');
    await expect(page.getByTestId('card-t1')).toBeVisible();
  });

  test('a host refusal restores the DOM, by mouse and by keyboard', async ({ page }) => {
    await page.goto('/kit/sortable');
    await dragOver(page, page.getByTestId('card-d1'), page.getByTestId('column-done').locator('h2'));
    await page.mouse.up();
    expect(await cardsIn(page, 'doing')).toEqual(['d1', 'd2']);
    expect(await cardsIn(page, 'done')).toEqual([]);
    await expect(log(page)).toContainText('cancel cards d1 doing 0 pointer refused');

    const card = page.getByTestId('card-d1');
    await card.focus();
    await page.keyboard.press('Alt+ArrowDown');
    expect(await cardsIn(page, 'doing')).toEqual(['d1', 'd2']);
    await expect(card).toBeFocused();
    await spoken(page, 'cannot be moved there');
  });

  test('a failed write reverts through detail.revert()', async ({ page }) => {
    await page.goto('/kit/sortable');
    await dragOver(page, page.getByTestId('card-d2'), page.getByTestId('column-done').locator('h2'));
    await page.mouse.up();
    await expect.poll(() => cardsIn(page, 'doing')).toEqual(['d1', 'd2']);
    expect(await cardsIn(page, 'done')).toEqual([]);
    await expect(log(page)).toContainText('reverted cards d2 done doing');
    await spoken(page, 'undone');
  });

  test('keyboard: Alt+arrows move, keep focus, and speak', async ({ page }) => {
    const csp = await watchCsp(page);
    await page.goto('/kit/sortable');
    const card = page.getByTestId('card-t1');
    await card.focus();
    await page.keyboard.press('Alt+ArrowDown');
    expect((await cardsIn(page, 'todo')).slice(0, 2)).toEqual(['t2', 't1']);
    await expect(card).toBeFocused();
    await expect(page.locator('[data-nowo-ui-sortable-live]').first()).toHaveAttribute('role', 'status');
    await spoken(page, 'Task 1 moved to position 2 of 12');

    await page.keyboard.press('Alt+ArrowRight');
    expect(await cardsIn(page, 'doing')).toEqual(['d1', 't1', 'd2']);
    await expect(card).toBeFocused();
    await expect(page).toHaveURL(/\/kit\/sortable$/); // Alt+Right is not "forward" here
    await expect(log(page)).toContainText('moved cards t1 doing todo 1 1 keyboard');

    await page.keyboard.press('Alt+ArrowLeft');
    await expect(card).toBeFocused();
    expect((await cardsIn(page, 'todo')).indexOf('t1')).toBe(1);
    await expect(page).toHaveURL(/\/kit\/sortable$/); // nor "back"

    const handle = page.getByTestId('step-handle-draft');
    await handle.focus();
    await page.keyboard.press('Alt+ArrowRight');
    await expect(handle).toBeFocused();
    const steps = await page.getByTestId('steps').locator('> li').evaluateAll((els) => els.map((el) => el.getAttribute('data-nowo-ui-sortable-item')));
    expect(steps).toEqual(['review', 'draft', 'publish']);
    expect(await csp()).toEqual([]);
  });

  test('axe: no violations at rest and during a drag', async ({ page }) => {
    await page.goto('/kit/sortable');
    await page.getByTestId('card-t1').focus();
    const rest = await new AxeBuilder({ page }).include('[data-testid="sortable-demo"]').analyze();
    expect(rest.violations).toEqual([]);
    await dragOver(page, page.getByTestId('card-t1'), page.getByTestId('card-t3'));
    const during = await new AxeBuilder({ page }).include('[data-testid="sortable-demo"]').analyze();
    expect(during.violations).toEqual([]);
    await page.keyboard.press('Escape');
    await page.mouse.up();
  });

  test('the CSP check can fail: a style attribute set here is reported, and a drag adds none', async ({ page }) => {
    const csp = await watchCsp(page);
    await page.goto('/kit/sortable');
    expect(await csp()).toEqual([]);
    await dragOver(page, page.getByTestId('card-t1'), page.getByTestId('card-t3'));
    await expect(page.locator('.nowo-ui-sortable__ghost')).toHaveCount(1);
    await page.mouse.up();
    expect(await csp()).toEqual([]);
    await page.evaluate(() => document.querySelector('h1')!.setAttribute('style', 'color: red'));
    await expect.poll(async () => (await csp()).map((v) => v.directive)).toEqual(['style-src-attr']);
    await expect(page.locator('h1')).not.toHaveCSS('color', 'rgb(255, 0, 0)');
  });

  test('HH-M1: a component stopping pointerup or pointermove cannot break a drag', async ({ page }) => {
    await page.goto('/kit/sortable');
    await page.evaluate(() => {
      for (const type of ['pointerup', 'pointermove']) document.body.addEventListener(type, (e) => e.stopPropagation());
    });
    await dragOver(page, page.getByTestId('card-t1'), page.getByTestId('card-d2'), 0.1);
    await expect(page.locator('.nowo-ui-sortable__placeholder')).toHaveCount(1);
    await page.mouse.up();
    await expect(page.locator('.nowo-ui-sortable__ghost')).toHaveCount(0);
    expect(await cardsIn(page, 'doing')).toEqual(['d1', 't1', 'd2']);
  });

  for (const name of ['elementFromPoint', 'removeEventListener', 'addEventListener', 'createElement', 'querySelectorAll', 'implementation', 'importNode']) {
    test(`HH-M1, HH-L4: <img name="${name}"> in the page changes nothing`, async ({ page }) => {
      await page.goto('/kit/sortable');
      await page.evaluate(() => {
        const w = window as unknown as { seen: string[] };
        w.seen = [];
        document.addEventListener('nowo-ui:sortable:moved', (e) => w.seen.push((e as CustomEvent).detail.id));
      });
      // Measured first: with these names hidden, Playwright's own locators and the demo's log stop working too.
      const start = await grip(page.getByTestId('card-t1'));
      const target = (await page.getByTestId('card-d2').boundingBox())!;
      const other = page.getByTestId('card-t2');
      await page.evaluate((n) => {
        const img = document.createElement('img');
        img.name = n;
        img.id = 'kit-e2e-named';
        document.body.append(img);
      }, name);
      await page.mouse.move(start.x, start.y);
      await page.mouse.down();
      await page.mouse.move(start.x + 3, start.y + 8, { steps: 3 });
      await page.mouse.move(target.x + target.width / 2, target.y + target.height * 0.1, { steps: 8 });
      await page.mouse.up();
      await page.evaluate(() => Element.prototype.remove.call(Document.prototype.getElementById.call(document, 'kit-e2e-named')!));
      await expect(page.locator('.nowo-ui-sortable__ghost')).toHaveCount(0);
      expect(await cardsIn(page, 'doing')).toEqual(['d1', 't1', 'd2']);
      expect(await page.evaluate(() => (window as unknown as { seen: string[] }).seen)).toEqual(['t1']);
      // The keyboard route across lists, with the name back in place.
      await other.focus();
      await page.evaluate((n) => {
        const img = document.createElement('img');
        img.name = n;
        img.id = 'kit-e2e-named';
        document.body.append(img);
      }, name);
      await page.keyboard.press('Alt+ArrowRight');
      await page.evaluate(() => Element.prototype.remove.call(Document.prototype.getElementById.call(document, 'kit-e2e-named')!));
      expect(await cardsIn(page, 'doing')).toContain('t2');
    });
  }

  test('HH-L4: an element with the id of the global does not turn the script off', async ({ page }) => {
    await page.route('**/js/nowo-ui-sortable.js', async (route) => {
      await new Promise((resolve) => setTimeout(resolve, 300));
      await route.continue();
    });
    await page.goto('/kit/sortable', { waitUntil: 'commit' });
    await page.waitForSelector('h1');
    await page.evaluate(() => {
      const a = document.createElement('a');
      a.id = 'nowoUiAnnounceSortable';
      document.body.append(a);
    });
    await page.waitForLoadState('load');
    await page.getByTestId('card-t1').focus();
    await page.keyboard.press('Alt+ArrowDown');
    expect((await cardsIn(page, 'todo')).slice(0, 2)).toEqual(['t2', 't1']);
  });

  for (const name of ['hidden', 'display: none', 'visibility: hidden', 'inert']) {
    test(`HH-M2: Alt+arrow skips a list that is ${name}, and focus stays`, async ({ page }) => {
      await page.goto('/kit/sortable');
      await page.getByTestId('column-doing').evaluate((column, kind) => {
        // A wrapper: the demo's flex rule would override [hidden] on the column itself.
        const wrapper = document.createElement('div');
        column.before(wrapper);
        wrapper.append(column);
        if (kind === 'hidden') wrapper.hidden = true;
        if (kind === 'display: none') wrapper.style.setProperty('display', 'none');
        if (kind === 'visibility: hidden') wrapper.style.setProperty('visibility', 'hidden');
        if (kind === 'inert') wrapper.inert = true;
      }, name);
      const card = page.getByTestId('card-t1');
      await card.focus();
      await page.keyboard.press('Alt+ArrowRight');
      expect(await cardsIn(page, 'doing')).toEqual(['d1', 'd2']);
      expect(await cardsIn(page, 'done')).toEqual(['t1']);
      await expect(card).toBeFocused();
    });
  }

  for (const kind of ['a closed dialog', 'a closed details']) {
    test(`HH-M2: Alt+arrow skips a list in ${kind}`, async ({ page }) => {
      await page.goto('/kit/sortable');
      await page.evaluate((k) => {
        const box = document.createElement(k.includes('dialog') ? 'dialog' : 'details');
        const ul = document.createElement('ul');
        ul.setAttribute('data-nowo-ui-sortable', 'cards');
        ul.setAttribute('data-nowo-ui-sortable-list', 'closed');
        box.append(ul);
        document.querySelector('[data-testid="column-todo"]')!.after(box);
      }, kind);
      const card = page.getByTestId('card-t1');
      await card.focus();
      await page.keyboard.press('Alt+ArrowRight');
      expect(await cardsIn(page, 'doing')).toContain('t1');
      await expect(card).toBeFocused();
    });
  }

  test('HH-M2: nothing leaves a modal dialog, and nothing goes from the page into one', async ({ page }) => {
    await page.goto('/kit/sortable');
    await page.evaluate(() => {
      const dialog = document.createElement('dialog');
      dialog.id = 'kit-e2e-modal';
      for (const id of ['m1', 'm2']) {
        const ul = document.createElement('ul');
        ul.setAttribute('data-testid', `list-${id}`);
        ul.setAttribute('data-nowo-ui-sortable', 'cards');
        ul.setAttribute('data-nowo-ui-sortable-list', id);
        dialog.append(ul);
      }
      const li = document.createElement('li');
      li.tabIndex = 0;
      li.textContent = 'In the modal';
      li.setAttribute('data-testid', 'card-in-modal');
      li.setAttribute('data-nowo-ui-sortable-item', 'in-modal');
      dialog.firstElementChild!.append(li);
      document.querySelector('main')!.append(dialog);
      dialog.showModal();
    });
    const card = page.getByTestId('card-in-modal');
    await card.focus();
    await page.keyboard.press('Alt+ArrowLeft');
    expect(await cardsIn(page, 'm1')).toEqual(['in-modal']);
    await expect(card).toBeFocused();
    await page.keyboard.press('Alt+ArrowRight');
    expect(await cardsIn(page, 'm2')).toEqual(['in-modal']);
    await expect(card).toBeFocused();
    await page.evaluate(() => (document.getElementById('kit-e2e-modal') as HTMLDialogElement).close());
    await page.getByTestId('card-t1').focus();
    await page.keyboard.press('Alt+ArrowLeft');
    expect(await cardsIn(page, 'm2')).toEqual(['in-modal']);
  });

  test('HH-L1: a custom element runs no callback and media fetches nothing, per drag', async ({ page }) => {
    const media: string[] = [];
    await page.route('**/kit-e2e-media.*', (route) => {
      media.push(route.request().url());
      return route.abort();
    });
    await page.goto('/kit/sortable');
    await page.evaluate(() => {
      customElements.define(
        'kit-e2e-probe',
        class extends HTMLElement {
          connectedCallback(): void {
            const w = window as unknown as { probes: number };
            w.probes = (w.probes ?? 0) + 1;
          }
        },
      );
      const card = document.querySelector('[data-testid="card-t1"]')!;
      card.append(document.createElement('kit-e2e-probe'));
      const video = document.createElement('video');
      video.src = '/kit-e2e-media.mp4';
      const audio = document.createElement('audio');
      audio.src = '/kit-e2e-media.mp3';
      audio.autoplay = true;
      audio.loop = true;
      card.append(video, audio);
    });
    await page.waitForTimeout(200);
    const before = await page.evaluate(() => (window as unknown as { probes: number }).probes);
    const fetched = media.length;
    // By its link: the middle of the card is now the media, which does not start a drag.
    await dragOver(page, page.getByTestId('link-t1'), page.getByTestId('card-t3'));
    await expect(page.locator('.nowo-ui-sortable__ghost')).toHaveCount(1);
    await expect(page.locator('.nowo-ui-sortable__ghost kit-e2e-probe, .nowo-ui-sortable__ghost video, .nowo-ui-sortable__ghost audio')).toHaveCount(0);
    await page.waitForTimeout(200);
    // Checked before the drop: the drop moves the real element, which rightly runs its callbacks.
    expect(await page.evaluate(() => (window as unknown as { probes: number }).probes)).toBe(before);
    expect(media.length).toBe(fetched);
    await page.mouse.up();
  });

  test('HH-M3: a served style attribute the CSP blocked stays blocked, on the copy and on the original, for good', async ({ page }) => {
    const leaked: string[] = [];
    await page.route('**/kit-e2e-exfil.png', (route) => {
      leaked.push(route.request().url());
      return route.abort();
    });
    const response = await page.goto('/kit/sortable?blocked=1');
    expect(await response!.text()).toContain('style="position:fixed'); // the attribute is really served
    // Inside the real item: the copy keeps the test id.
    const original = page.locator('[data-nowo-ui-sortable-item="blocked"] [data-testid="link-blocked"]');
    const read = (): Promise<{ position: string; length: number; hit: string | null }> =>
      original.evaluate((link: HTMLElement) => ({
        position: getComputedStyle(link).position,
        length: link.style.length,
        hit: document.elementFromPoint(innerWidth / 2, innerHeight / 2)?.getAttribute('data-testid') ?? null,
      }));
    const check = async (): Promise<void> => {
      const now = await read();
      expect(now.position).toBe('static');
      expect(now.length).toBe(0);
      expect(now.hit).not.toBe('link-blocked');
    };
    // No read of the style before the drag: a browser fills it in once, lazily, and a read here would hide the effect.
    const card = page.locator('[data-nowo-ui-sortable-item="blocked"]');
    await dragOver(page, card, page.getByTestId('card-rich'), 0.1);
    const ghost = page.locator('.nowo-ui-sortable__ghost');
    await expect(ghost).toHaveCount(1);
    await check();
    const copy = ghost.locator('a');
    await expect(copy).not.toHaveCSS('position', 'fixed');
    await expect(copy).not.toHaveCSS('color', 'rgb(255, 0, 0)');
    expect(await copy.getAttribute('style')).toBeNull();
    await page.waitForTimeout(150);
    await page.mouse.up();
    expect(await cardsIn(page, 'device')).toEqual(['blocked', 'rich', 'plain']);
    await page.waitForTimeout(150);
    await check();
    expect(leaked).toEqual([]);
  });

  test('the copy of a big card keeps its look and says what it costs', async ({ page }, info) => {
    await page.goto('/kit/sortable');
    const costs = await page.getByTestId('card-t1').evaluate((card: HTMLElement) => {
      for (let i = 0; i < 1000; i++) {
        const row = document.createElement('div');
        const cell = document.createElement('span');
        cell.textContent = `cell ${i}`;
        cell.dataset.n = String(i);
        cell.style.setProperty('--n', String(i));
        row.append(cell);
        card.append(row);
      }
      const start = performance.now();
      card.cloneNode(true);
      return { nodes: card.querySelectorAll('*').length, cloneMs: performance.now() - start };
    });
    await page.evaluate(() => {
      const w = window as unknown as { startAt: number; shown: number };
      document.addEventListener('nowo-ui:sortable:start', () => {
        w.startAt = performance.now();
        queueMicrotask(() => undefined);
      });
      new MutationObserver((records) => {
        if (records.some((r) => [...r.addedNodes].some((n) => (n as Element).classList?.contains('nowo-ui-sortable__ghost'))) && !w.shown) w.shown = performance.now();
      }).observe(document.body, { childList: true, subtree: true });
    });
    // Near its top: the middle of this card is far below the screen.
    await page.getByTestId('card-t1').evaluate((card) => card.scrollIntoView({ block: 'start', behavior: 'instant' }));
    const box = (await page.getByTestId('card-t1').boundingBox())!;
    await page.mouse.move(box.x + 16, box.y + 12);
    await page.mouse.down();
    await page.mouse.move(box.x + 18, box.y + 30, { steps: 3 });
    const ghost = page.locator('.nowo-ui-sortable__ghost');
    await expect(ghost).toHaveCount(1);
    const pairs = await ghost.evaluate((el) => [...el.querySelectorAll<HTMLElement>('[data-n]')].filter((c) => c.style.getPropertyValue('--n') === c.dataset.n).length);
    expect(pairs).toBe(1000);
    const buildMs = await page.evaluate(() => {
      const w = window as unknown as { startAt: number; shown: number };
      return w.shown - w.startAt;
    });
    console.log(`COST ${info.project.name}: ${costs.nodes} nodes, drag start ${buildMs.toFixed(1)} ms, cloneNode alone ${costs.cloneMs.toFixed(1)} ms`);
    await page.keyboard.press('Escape');
    await page.mouse.up();
  });

  test('HH-L3: an item planted in a card has no place, and nothing lands inside a card', async ({ page }) => {
    await page.goto('/kit/sortable');
    await page.getByTestId('card-t2').evaluate((card) => {
      const span = document.createElement('span');
      span.setAttribute('data-nowo-ui-sortable-item', 'planted');
      span.tabIndex = 0;
      card.append(span);
    });
    const third = page.getByTestId('card-t3');
    await third.focus();
    await page.keyboard.press('Alt+ArrowUp');
    await expect(log(page)).toContainText('move cards t3 todo todo 1 2 keyboard');
    expect((await cardsIn(page, 'todo')).slice(0, 3)).toEqual(['t1', 't3', 't2']);
  });

  test('HH-L5: Enter on a button after Escape is not swallowed', async ({ page }) => {
    await page.goto('/kit/sortable');
    await dragOver(page, page.getByTestId('card-t1'), page.getByTestId('card-t3'));
    await page.keyboard.press('Escape');
    const button = page.getByTestId('move-t2-down');
    await button.focus();
    await page.keyboard.press('Enter');
    expect((await cardsIn(page, 'todo')).slice(0, 3)).toEqual(['t1', 't3', 't2']);
    await page.mouse.up();
  });

  test('WCAG 2.5.7: the arrow buttons move with no drag, keep focus, speak and can be refused', async ({ page }) => {
    const csp = await watchCsp(page);
    await page.goto('/kit/sortable');
    const down = page.getByTestId('move-t1-down');
    await down.click();
    expect((await cardsIn(page, 'todo')).slice(0, 2)).toEqual(['t2', 't1']);
    await expect(down).toBeFocused();
    await expect(log(page)).toContainText('moved cards t1 todo todo 1 0 button');
    await spoken(page, 'Task 1 moved to position 2 of 12');

    const next = page.getByTestId('move-t1-next');
    await next.click();
    expect(await cardsIn(page, 'doing')).toContain('t1');
    await expect(next).toBeFocused();

    await page.getByTestId('move-d1-down').click();
    expect((await cardsIn(page, 'doing'))[0]).toBe('d1');
    await spoken(page, 'cannot be moved there');

    await page.getByTestId('move-draft-down').click();
    const steps = await page.getByTestId('steps').locator('> li').evaluateAll((els) => els.map((el) => el.getAttribute('data-nowo-ui-sortable-item')));
    expect(steps).toEqual(['review', 'draft', 'publish']);
    await page.getByTestId('move-todo-down').click();
    const columns = await page.getByTestId('board').locator('> [data-nowo-ui-sortable-item]').evaluateAll((els) => els.map((el) => el.getAttribute('data-nowo-ui-sortable-item')));
    expect(columns).toEqual(['doing', 'todo', 'done']);
    expect(await csp()).toEqual([]);
  });

  test('HH-DL1: the move attribute on anything but a button does nothing', async ({ page }) => {
    await page.goto('/kit/sortable');
    await page.getByTestId('card-t1').evaluate((card) => {
      const span = document.createElement('span');
      span.textContent = 'planted';
      span.setAttribute('data-testid', 'planted-move');
      span.setAttribute('data-nowo-ui-sortable-move', 'down');
      card.append(span);
    });
    await page.getByTestId('planted-move').click();
    expect((await cardsIn(page, 'todo'))[0]).toBe('t1');
    await expect(log(page)).not.toContainText('move cards');
  });

  test('HH-DL2: Alt+arrow during a press leaves no stale gesture', async ({ page }) => {
    await page.goto('/kit/sortable');
    const start = await grip(page.getByTestId('card-t1'));
    await page.mouse.move(start.x, start.y);
    await page.mouse.down();
    await page.keyboard.press('Alt+ArrowRight');
    expect((await cardsIn(page, 'todo'))[0]).toBe('t1');
    await page.mouse.up();
  });

  test('HH-EL1: pressing and moving on audio controls scrubs, it starts no drag', async ({ page }) => {
    await page.goto('/kit/sortable');
    const start = await centre(page.getByTestId('rich-audio'));
    await page.mouse.move(start.x, start.y);
    await page.mouse.down();
    await page.mouse.move(start.x + 40, start.y, { steps: 6 });
    await expect(page.locator('.nowo-ui-sortable__ghost')).toHaveCount(0);
    await page.mouse.up();
    await expect(log(page)).not.toContainText('start device');
  });

  test('HH-DL3: a customized built-in in a card does not run again in the copy', async ({ page, browserName }) => {
    test.skip(browserName === 'webkit', 'WebKit has no customized built-in elements');
    const fetched: string[] = [];
    await page.route('**/kit-e2e-builtin', (route) => {
      fetched.push(route.request().url());
      return route.fulfill({ status: 204 });
    });
    await page.goto('/kit/sortable');
    await page.evaluate(() => {
      const w = window as unknown as { built: number; connected: number };
      w.built = 0;
      w.connected = 0;
      customElements.define(
        'kit-e2e-builtin',
        class extends HTMLDivElement {
          constructor() {
            super();
            w.built += 1;
          }

          connectedCallback(): void {
            w.connected += 1;
            void fetch('/kit-e2e-builtin');
          }
        },
        { extends: 'div' },
      );
      const part = document.createElement('div', { is: 'kit-e2e-builtin' });
      part.setAttribute('is', 'kit-e2e-builtin');
      part.textContent = 'built in';
      document.querySelector('[data-testid="card-t1"]')!.append(part);
    });
    const counts = (): Promise<number[]> => page.evaluate(() => {
      const w = window as unknown as { built: number; connected: number };
      return [w.built, w.connected];
    });
    await expect.poll(() => fetched.length).toBe(1);
    expect(await counts()).toEqual([1, 1]);
    await dragOver(page, page.getByTestId('card-t1'), page.getByTestId('card-t3'));
    const ghost = page.locator('.nowo-ui-sortable__ghost');
    await expect(ghost).toHaveCount(1);
    await expect(ghost.locator('[is]')).toHaveCount(0);
    await page.waitForTimeout(150);
    // Before the drop: the drop moves the real element, which rightly runs its callback.
    expect(await counts()).toEqual([1, 1]);
    expect(fetched.length).toBe(1);
    await page.keyboard.press('Escape');
    await page.mouse.up();
  });

  test('checklist cards: the field in a whole-item card takes focus and typing, the image does not start a native drag', async ({ page }) => {
    await page.goto('/kit/sortable');
    const field = page.getByTestId('rich-field');
    await field.click();
    await page.keyboard.type('typed');
    await expect(field).toHaveValue('typed');
    await expect(page.locator('.nowo-ui-sortable__ghost')).toHaveCount(0);
    await dragOver(page, page.getByTestId('rich-image'), page.getByTestId('card-plain'), 0.9);
    const ghost = page.locator('.nowo-ui-sortable__ghost');
    await expect(ghost).toHaveCount(1);
    await expect(ghost.locator('input')).toHaveValue('typed');
    await expect(ghost.locator('audio')).toHaveCount(0);
    await page.mouse.up();
    expect(await cardsIn(page, 'device')).toEqual(['plain', 'rich']);
    await expect(field).toHaveValue('typed');
  });

  test('synthetic touch events (logic only, proves nothing about the engine gesture): long press, then move and drop', async ({ page }) => {
    await page.goto('/kit/sortable');
    const a = await grip(page.getByTestId('card-t1'));
    const b = await centre(page.getByTestId('card-d2'));
    await page.evaluate(
      async ({ a, b }) => {
        const fire = (type: string, x: number, y: number): void => {
          const target = document.querySelector('[data-testid="card-t1"]')!;
          target.dispatchEvent(new PointerEvent(type, { bubbles: true, cancelable: true, pointerId: 7, pointerType: 'touch', isPrimary: true, button: type === 'pointermove' ? -1 : 0, clientX: x, clientY: y }));
        };
        fire('pointerdown', a.x, a.y);
        await new Promise((resolve) => setTimeout(resolve, 320));
        fire('pointermove', b.x, b.y);
        fire('pointerup', b.x, b.y);
      },
      { a, b },
    );
    expect((await cardsIn(page, 'doing'))).toContain('t1');
  });
});
