'use client';

import type { AdminImportRun } from '@/lib/api';

const STATUS_TONE: Record<string, string> = {
  review_ready: 'bg-[#ffdf92] text-[#594400]',
  approved: 'bg-[#d6e9ff] text-[#00405c]',
  completed: 'bg-[#c6f0d2] text-[#0d4b22]',
  validation_failed: 'bg-[#ffdad6] text-[#93000a]',
  apply_failed: 'bg-[#ffdad6] text-[#93000a]',
  cancelled: 'bg-[#e3e8ec] text-[#3e4850]',
};

export default function ImportRunList({ runs, selectedId, onSelect }: {
  runs: AdminImportRun[];
  selectedId: number | null;
  onSelect: (id: number) => void;
}) {
  if (runs.length === 0) return <p className="rounded-2xl border-2 border-[#bdc8d2] bg-white p-5 font-bold">No import runs yet.</p>;

  return (
    <ul aria-label="Import run history" className="space-y-3">
      {runs.map((run) => (
        <li key={run.id}>
          <button
            type="button"
            onClick={() => onSelect(run.id)}
            aria-current={run.id === selectedId}
            className={`w-full rounded-2xl border-2 p-4 text-left transition ${run.id === selectedId ? 'border-[#006590] bg-[#f2f9ff]' : 'border-[#bdc8d2] bg-white hover:border-[#88ceff]'}`}
          >
            <span className="flex items-center justify-between gap-3">
              <span className="font-black capitalize">{run.entity}</span>
              <span className={`rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide ${STATUS_TONE[run.status] ?? 'bg-[#e3e8ec] text-[#3e4850]'}`}>{run.status.replace('_', ' ')}</span>
            </span>
            <span className="mt-2 block text-sm font-medium text-[#3e4850]">
              {run.items_count ?? 0} staged · cursor {run.result_cursor ?? run.starting_cursor} · {new Date(run.created_at).toLocaleString()}
            </span>
            {run.error_message && <span className="mt-2 block text-sm font-bold text-[#93000a]">{run.error_message}</span>}
          </button>
        </li>
      ))}
    </ul>
  );
}
