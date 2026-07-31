'use client';

import { IMPORT_ACTIONS, type AdminImportAction, type AdminImportItem } from '@/lib/api';

/**
 * Mirrors AdminImportApproval::cascadeExclusions so reviewers see the same outcome
 * the server will persist. An excluded parent/dependency only drags children down
 * when it does not already exist locally (`target_id === null`).
 */
export function cascadeExcluded(items: AdminImportItem[], draft: Record<number, AdminImportAction>): Set<number> {
  const byId = new Map(items.map((item) => [item.id, item]));
  const action = (item: AdminImportItem) => draft[item.id] ?? item.selected_action;
  const excluded = new Set(items.filter((item) => action(item) === 'exclude').map((item) => item.id));

  for (let changed = true; changed;) {
    changed = false;
    for (const item of items) {
      if (excluded.has(item.id)) continue;
      const blockers = [
        item.parent_item_id === null ? undefined : byId.get(item.parent_item_id),
        ...(item.dependencies ?? []).map((dependency) =>
          items.find((candidate) => candidate.entity === dependency.entity && candidate.external_id === dependency.external_id)),
      ];
      if (blockers.some((blocker) => blocker && excluded.has(blocker.id) && blocker.target_id === null)) {
        excluded.add(item.id);
        changed = true;
      }
    }
  }

  return excluded;
}

export default function ImportReviewTable({ items, draft, excluded, selectedId, onSelect, onAction, disabled }: {
  items: AdminImportItem[];
  draft: Record<number, AdminImportAction>;
  excluded: Set<number>;
  selectedId: number | null;
  onSelect: (id: number) => void;
  onAction: (id: number, action: AdminImportAction) => void;
  disabled: boolean;
}) {
  if (items.length === 0) return <p className="rounded-2xl border-2 border-[#bdc8d2] bg-white p-5 font-bold">No staged records in this run.</p>;

  return (
    <div className="overflow-x-auto rounded-3xl border-2 border-[#bdc8d2] bg-white p-5">
      <table className="w-full text-left">
        <caption className="sr-only">Staged import records</caption>
        <thead>
          <tr>{['Record', 'Classification', 'Action', ''].map((label, index) => <th key={label || index} scope="col" className="px-3 py-2 text-xs uppercase tracking-wide text-[#3e4850]">{label}</th>)}</tr>
        </thead>
        <tbody>
          {items.map((item) => {
            const action = draft[item.id] ?? item.selected_action ?? 'exclude';
            const cascaded = excluded.has(item.id) && action !== 'exclude';
            return (
              <tr key={item.id} className={`border-t border-[#e3e8ec] ${item.id === selectedId ? 'bg-[#f2f9ff]' : ''}`}>
                <td className="px-3 py-3">
                  <span className="block font-black capitalize">{item.entity}</span>
                  <span className="block text-sm font-medium text-[#3e4850]">{item.external_id}</span>
                  {cascaded && <span role="note" className="mt-1 block text-sm font-bold text-[#93000a]">Excluded with its parent record.</span>}
                </td>
                <td className="px-3 py-3 font-bold">{item.classification.replace('_', ' ')}</td>
                <td className="px-3 py-3">
                  <select
                    aria-label={`Action for ${item.external_id}`}
                    value={action}
                    disabled={disabled}
                    onChange={(event) => onAction(item.id, event.target.value as AdminImportAction)}
                    className="rounded-xl border-2 border-[#bdc8d2] px-3 py-2 font-bold disabled:opacity-50"
                  >
                    {IMPORT_ACTIONS[item.classification].map((option) => <option key={option} value={option}>{option.replace('_', ' ')}</option>)}
                  </select>
                </td>
                <td className="px-3 py-3">
                  <button type="button" onClick={() => onSelect(item.id)} className="rounded-xl border-2 border-[#88ceff] px-4 py-2 font-bold text-[#006590]">
                    {`Diff ${item.external_id}`}
                  </button>
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
