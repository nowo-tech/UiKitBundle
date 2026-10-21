import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ErrorRetryCountdownController from './error_retry_countdown_controller';

describe('error-retry-countdown controller', () => {
  let application: Application;

  beforeEach(() => {
    vi.useFakeTimers();
    application = Application.start();
    application.register('error-retry-countdown', ErrorRetryCountdownController);
  });

  afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  const connect = async (html: string): Promise<ErrorRetryCountdownController> => {
    document.body.innerHTML = html;
    await Promise.resolve();
    await Promise.resolve();
    const root = document.querySelector('[data-controller="error-retry-countdown"]') as HTMLElement;
    return application.getControllerForElementAndIdentifier(root, 'error-retry-countdown') as ErrorRetryCountdownController;
  };

  it('counts down into the remaining target and reloads the URL value at zero', async () => {
    const assign = vi.fn();
    vi.spyOn(window, 'location', 'get').mockReturnValue({ ...window.location, assign, href: 'http://localhost/now' } as Location);
    await connect(`<p data-controller="error-retry-countdown" data-error-retry-countdown-url-value="/orders" data-error-retry-countdown-seconds-value="2">
      <span data-error-retry-countdown-target="remaining">10:00</span></p>`);
    const span = document.querySelector('span')!;
    expect(span.textContent).toBe('0:02');
    vi.advanceTimersByTime(1000);
    expect(span.textContent).toBe('0:01');
    vi.advanceTimersByTime(1000);
    expect(span.textContent).toBe('0:00');
    expect(assign).toHaveBeenCalledWith('/orders');
  });

  it('defaults to 10 minutes, works without a target and stops on disconnect', async () => {
    const assign = vi.fn();
    vi.spyOn(window, 'location', 'get').mockReturnValue({ ...window.location, assign, href: 'http://localhost/here' } as Location);
    const controller = await connect('<p data-controller="error-retry-countdown"></p>');
    expect(controller).toBeInstanceOf(ErrorRetryCountdownController);
    document.body.innerHTML = '';
    await Promise.resolve();
    vi.advanceTimersByTime(700_000);
    expect(assign).not.toHaveBeenCalled();
  });

  it('navigates to the current location without a URL value', async () => {
    const assign = vi.fn();
    vi.spyOn(window, 'location', 'get').mockReturnValue({ ...window.location, assign, href: 'http://localhost/here' } as Location);
    await connect('<p data-controller="error-retry-countdown" data-error-retry-countdown-seconds-value="1"><span data-error-retry-countdown-target="remaining"></span></p>');
    vi.advanceTimersByTime(1000);
    expect(assign).toHaveBeenCalledWith('http://localhost/here');
  });
});
