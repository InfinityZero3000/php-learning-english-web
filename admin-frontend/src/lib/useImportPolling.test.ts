import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useImportPolling } from './useImportPolling';
import { adminImports } from '@/lib/api';

vi.mock('@/lib/api', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/api')>();
  return { ...actual, adminImports: { ...actual.adminImports, run: vi.fn() } };
});

describe('useImportPolling', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('polls until a terminal status and then stops', async () => {
    vi.mocked(adminImports.run)
      .mockResolvedValueOnce({ id: 'run-1', status: 'running' } as never)
      .mockResolvedValueOnce({ id: 'run-1', status: 'review-ready' } as never);

    const { result } = renderHook(() => useImportPolling('run-1'));

    await act(async () => { await Promise.resolve(); await Promise.resolve(); });
    expect(adminImports.run).toHaveBeenCalledTimes(1);
    expect(result.current.run?.status).toBe('running');

    await act(async () => { await vi.advanceTimersByTimeAsync(2000); });
    expect(adminImports.run).toHaveBeenCalledTimes(2);
    expect(result.current.run?.status).toBe('review-ready');

    // Terminal status reached: no further polls even after a long wait.
    await act(async () => { await vi.advanceTimersByTimeAsync(20000); });
    expect(adminImports.run).toHaveBeenCalledTimes(2);
  });

  it('stops polling after unmount', async () => {
    vi.mocked(adminImports.run).mockResolvedValue({ id: 'run-1', status: 'running' } as never);

    const { unmount } = renderHook(() => useImportPolling('run-1'));
    await act(async () => { await Promise.resolve(); await Promise.resolve(); });
    expect(adminImports.run).toHaveBeenCalledTimes(1);

    unmount();
    await act(async () => { await vi.advanceTimersByTimeAsync(20000); });
    expect(adminImports.run).toHaveBeenCalledTimes(1);
  });
});
