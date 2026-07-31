'use client';

import { useEffect, useRef, useState } from 'react';
import { adminImports, type AdminImportRun } from '@/lib/api';

const TERMINAL: AdminImportRun['status'][] = ['succeeded', 'review-ready', 'failed'];
const BACKOFF_MS = [2000, 3000, 5000, 8000, 8000];

export function useImportPolling(runId: string | null) {
  const [run, setRun] = useState<AdminImportRun | null>(null);
  const [error, setError] = useState('');
  const attempt = useRef(0);

  useEffect(() => {
    if (!runId) {
      void Promise.resolve().then(() => setRun(null));
      return;
    }
    let cancelled = false;
    let timer: ReturnType<typeof setTimeout> | undefined;
    attempt.current = 0;

    async function poll() {
      try {
        const result = await adminImports.run(runId as string);
        if (cancelled) return;
        setRun(result);
        setError('');
        if (TERMINAL.includes(result.status)) return;
      } catch (reason) {
        if (cancelled) return;
        setError(reason instanceof Error ? reason.message : 'Could not refresh import status.');
      }
      const delay = BACKOFF_MS[Math.min(attempt.current, BACKOFF_MS.length - 1)];
      attempt.current += 1;
      timer = setTimeout(() => { if (!cancelled) void poll(); }, delay);
    }

    void poll();
    return () => {
      cancelled = true;
      if (timer) clearTimeout(timer);
    };
  }, [runId]);

  return { run, error };
}
