import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { DEFAULT_SECONDS, RetryCountdown, formatClock, parseSeconds } from './nowo-ui-error-retry-core';

describe('nowo-ui-error-retry-core', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  it('formats m:ss', () => {
    expect(formatClock(600)).toBe('10:00');
    expect(formatClock(61.9)).toBe('1:01');
    expect(formatClock(5)).toBe('0:05');
    expect(formatClock(-3)).toBe('0:00');
  });

  it('parses the seconds with a default', () => {
    expect(parseSeconds('90')).toBe(90);
    expect(parseSeconds(12.7)).toBe(12);
    expect(parseSeconds('0')).toBe(DEFAULT_SECONDS);
    expect(parseSeconds('soon')).toBe(DEFAULT_SECONDS);
    expect(parseSeconds(null, 30)).toBe(30);
    expect(parseSeconds(undefined)).toBe(DEFAULT_SECONDS);
  });

  it('renders every second and navigates to the URL at zero, then stops', () => {
    const texts: string[] = [];
    const navigate = vi.fn();
    const countdown = new RetryCountdown({ url: '/orders?page=2', seconds: 3, render: (t) => texts.push(t), navigate });
    countdown.start();
    expect(countdown.running()).toBe(true);
    expect(countdown.remaining()).toBe(3);
    vi.advanceTimersByTime(2000);
    expect(texts).toEqual(['0:03', '0:02', '0:01']);
    expect(navigate).not.toHaveBeenCalled();
    vi.advanceTimersByTime(1000);
    expect(texts[texts.length - 1]).toBe('0:00');
    expect(navigate).toHaveBeenCalledWith('/orders?page=2');
    expect(countdown.running()).toBe(false);
    vi.advanceTimersByTime(5000);
    expect(navigate).toHaveBeenCalledTimes(1);
  });

  it('restarts cleanly, stops on demand and floors the seconds at one', () => {
    const navigate = vi.fn();
    const countdown = new RetryCountdown({ url: '', seconds: 0, render: () => undefined, navigate });
    countdown.start();
    countdown.start();
    expect(countdown.remaining()).toBe(1);
    countdown.stop();
    countdown.stop();
    vi.advanceTimersByTime(3000);
    expect(navigate).not.toHaveBeenCalled();
    countdown.tick();
    expect(navigate).toHaveBeenCalledWith(window.location.href);
  });

  it('defaults to window.location.assign', () => {
    const assign = vi.fn();
    vi.spyOn(window, 'location', 'get').mockReturnValue({ ...window.location, assign, href: 'http://localhost/x' } as Location);
    const countdown = new RetryCountdown({ url: '/retry', seconds: 1, render: () => undefined });
    countdown.start();
    vi.advanceTimersByTime(1000);
    expect(assign).toHaveBeenCalledWith('/retry');
  });
});
