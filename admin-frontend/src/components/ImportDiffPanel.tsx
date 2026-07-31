'use client';

import type { AdminImportItem } from '@/lib/api';

function display(value: unknown): string {
  if (value === undefined || value === null || value === '') return '—';
  return typeof value === 'object' ? JSON.stringify(value) : String(value);
}

export default function ImportDiffPanel({ item }: { item: AdminImportItem }) {
  const before = item.base_snapshot ?? {};
  const after = item.candidate_payload ?? {};
  const rows = [...new Set([...Object.keys(before), ...Object.keys(after)])].sort()
    .map((field) => ({ field, before: display(before[field]), after: display(after[field]) }))
    .filter((row) => row.before !== row.after);

  return (
    <section aria-label={`Changes for ${item.external_id}`} className="rounded-3xl border-2 border-[#bdc8d2] bg-white p-5">
      <h3 className="text-lg font-black capitalize">{item.entity} · {item.external_id}</h3>
      {item.validation_errors && item.validation_errors.length > 0 && (
        <ul className="mt-3 space-y-1 rounded-2xl bg-[#ffdad6] p-3 font-bold text-[#93000a]">
          {item.validation_errors.map((error) => <li key={error}>{error}</li>)}
        </ul>
      )}
      {rows.length === 0 ? (
        <p className="mt-4 font-bold text-[#3e4850]">No field changes.</p>
      ) : (
        <div className="mt-4 overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead>
              <tr>{['Field', 'Current', 'Incoming'].map((label) => <th key={label} scope="col" className="px-3 py-2 text-xs uppercase tracking-wide text-[#3e4850]">{label}</th>)}</tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.field} className="border-t border-[#e3e8ec] align-top">
                  <th scope="row" className="px-3 py-2 text-left font-bold">{row.field}</th>
                  <td className="px-3 py-2 text-[#93000a]">{row.before}</td>
                  <td className="px-3 py-2 font-bold text-[#0d4b22]">{row.after}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}
