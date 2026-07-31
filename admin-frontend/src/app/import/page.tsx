'use client';

import { useCallback, useEffect, useState } from 'react';
import AdminLayout from '@/components/AdminLayout';
import AccessibleDialog from '@/components/AccessibleDialog';
import { DataPanel, StateNotice } from '@/components/AdminDataView';
import { useImportPolling } from '@/lib/useImportPolling';
import {
  adminImports, auth,
  type AdminImportCheckpoint, type AdminImportEntity, type AdminImportRun,
  type PageMeta, type StagedItem, type StagedItemClassification,
} from '@/lib/api';

export default function ImportPage() {
  return <AdminLayout title="Import Jobs"><PageContent /></AdminLayout>;
}

function PageContent() {
  const [checkpoints, setCheckpoints] = useState<AdminImportCheckpoint[]>([]);
  const [runs, setRuns] = useState<AdminImportRun[]>([]);
  const [entity, setEntity] = useState<AdminImportEntity>('courses');
  const [limit, setLimit] = useState(50);
  const [superAdmin, setSuperAdmin] = useState(false);
  const [loading, setLoading] = useState(true);
  const [working, setWorking] = useState(false);
  const [message, setMessage] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setMessage('');
    try {
      const [items, user] = await Promise.all([adminImports.checkpoints(), auth.adminMe()]);
      setCheckpoints(items);
      setSuperAdmin(user.role === 'super_admin');
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : 'Could not load import checkpoints.');
    } finally { setLoading(false); }
  }, []);

  // eslint-disable-next-line react-hooks/set-state-in-effect
  useEffect(() => { void load(); }, [load]);

  async function start(reset = false) {
    setWorking(true);
    setMessage('');
    try {
      const run = reset ? await adminImports.reset(entity, limit) : await adminImports.resume(entity, limit);
      setRuns(items => [run, ...items.filter(item => item.id !== run.id)]);
      setMessage(`Import ${run.status}. Request ${run.request_id.slice(0, 8)}…`);
      await load();
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : 'Could not start import.');
    } finally { setWorking(false); }
  }

  return <div className="space-y-6">
    <header className="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
      <div><p className="text-xs font-black uppercase tracking-[.18em] text-[#006590]">LexiLingo provider</p><h2 className="mt-2 text-3xl font-black">Server-to-server imports</h2><p className="mt-1 font-medium text-[#3e4850]">Resume from durable checkpoints without bypassing provider rate limits.</p></div>
      <button onClick={() => void load()} disabled={loading} className="rounded-xl border-2 border-[#88ceff] px-5 py-3 font-bold text-[#006590] disabled:opacity-50">Refresh</button>
    </header>

    {message && <p role="status" className="rounded-2xl bg-[#ffdf92] p-4 font-bold text-[#594400]">{message}</p>}

    <section className="rounded-3xl border-2 border-[#bdc8d2] bg-white p-6">
      <div className="grid gap-4 md:grid-cols-[1fr_160px_auto_auto] md:items-end">
        <label className="font-bold">Entity<select value={entity} onChange={event => setEntity(event.target.value as AdminImportEntity)} className="mt-2 w-full rounded-xl border-2 border-[#bdc8d2] px-4 py-3">{(['categories', 'courses', 'vocabulary', 'lessons'] as const).map(item => <option key={item}>{item}</option>)}</select></label>
        <label className="font-bold">Limit<input type="number" min="1" max="100" value={limit} onChange={event => setLimit(Math.min(100, Math.max(1, Number(event.target.value))))} className="mt-2 w-full rounded-xl border-2 border-[#bdc8d2] px-4 py-3" /></label>
        <button onClick={() => void start()} disabled={working} className="rounded-xl bg-[#006590] px-6 py-3 font-black text-white disabled:opacity-50">Start / resume</button>
        {superAdmin && <button onClick={() => confirm(`Reset ${entity} checkpoint and import again?`) && void start(true)} disabled={working} className="rounded-xl bg-[#ba1a1a] px-6 py-3 font-black text-white disabled:opacity-50">Reset & retry</button>}
      </div>
    </section>

    <section className="grid gap-4 md:grid-cols-3">
      {loading ? <p className="font-bold">Loading checkpoints…</p> : checkpoints.length === 0 ? <p>No checkpoints found.</p> : checkpoints.map(item => <article key={item.entity} className="rounded-2xl border-2 border-[#bdc8d2] bg-white p-5"><h3 className="text-lg font-black capitalize">{item.entity}</h3><dl className="mt-4 grid grid-cols-2 gap-3 text-sm"><dt>Cursor</dt><dd className="text-right font-black">{item.cursor}</dd><dt>Failures</dt><dd className="text-right font-black">{item.failures}</dd><dt>Last sync</dt><dd className="text-right font-bold">{item.last_synced_at ? new Date(item.last_synced_at).toLocaleString() : 'Never'}</dd></dl></article>)}
    </section>

    {runs.length > 0 && <section className="overflow-x-auto rounded-3xl border-2 border-[#bdc8d2] bg-white p-5"><h3 className="text-lg font-black">Runs started in this session</h3><table className="mt-4 w-full text-left"><thead><tr>{['Entity', 'Status', 'Processed', 'Skipped', 'Cursor', 'Error'].map(label => <th key={label} className="px-3 py-2 text-xs uppercase">{label}</th>)}</tr></thead><tbody>{runs.map(run => <LiveRunRow key={run.id} initial={run} />)}</tbody></table></section>}

    <RunHistoryPanel />
  </div>;
}

/** Polls a single active run until it reaches a terminal status, then stops. */
function LiveRunRow({ initial }: { initial: AdminImportRun }) {
  const active = initial.status === 'pending' || initial.status === 'running';
  const { run } = useImportPolling(active ? initial.id : null);
  const current = run ?? initial;
  return <tr className="border-t"><td className="px-3 py-3 font-bold">{current.entity}</td><td className="px-3 py-3">{current.status}</td><td className="px-3 py-3">{current.processed ?? '—'}</td><td className="px-3 py-3">{current.skipped ?? '—'}</td><td className="px-3 py-3">{current.result_cursor ?? current.starting_cursor}</td><td className="px-3 py-3 text-[#93000a]">{current.error_message ?? '—'}</td></tr>;
}

const HISTORY_PER_PAGE = 10;

function RunHistoryPanel() {
  const [items, setItems] = useState<AdminImportRun[]>();
  const [meta, setMeta] = useState<PageMeta>();
  const [error, setError] = useState('');
  const [entityFilter, setEntityFilter] = useState<AdminImportEntity | ''>('');
  const [statusFilter, setStatusFilter] = useState('');
  const [page, setPage] = useState(1);
  const [diffRun, setDiffRun] = useState<AdminImportRun | null>(null);

  const load = useCallback(async () => {
    setError('');
    try {
      const result = await adminImports.runs({
        entity: entityFilter || undefined,
        status: statusFilter || undefined,
        page,
        perPage: HISTORY_PER_PAGE,
      });
      setItems(result.data);
      setMeta(result.meta);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Could not load import history.');
    }
  }, [entityFilter, statusFilter, page]);

  useEffect(() => { void Promise.resolve().then(load); }, [load]);

  return <DataPanel title={`Run history · ${meta?.total ?? items?.length ?? 0}`}>
    <div className="mb-5 flex flex-wrap gap-3">
      <label className="font-bold">Entity
        <select value={entityFilter} onChange={event => { setPage(1); setEntityFilter(event.target.value as AdminImportEntity | ''); }} className="mt-1 block rounded-xl border-2 border-[#bdc8d2] px-3 py-2">
          <option value="">All</option>
          {(['categories', 'courses', 'vocabulary', 'lessons'] as const).map(item => <option key={item} value={item}>{item}</option>)}
        </select>
      </label>
      <label className="font-bold">Status
        <select value={statusFilter} onChange={event => { setPage(1); setStatusFilter(event.target.value); }} className="mt-1 block rounded-xl border-2 border-[#bdc8d2] px-3 py-2">
          <option value="">All</option>
          {['pending', 'running', 'review-ready', 'approved', 'failed'].map(item => <option key={item} value={item}>{item}</option>)}
        </select>
      </label>
    </div>

    {error && !items ? <StateNotice state="error" message={error} retry={load} />
      : !items ? <StateNotice state="loading" />
      : items.length === 0 ? <StateNotice state="empty" message="No import runs match this filter yet." />
      : <>
        {error && <p role="alert" className="mb-4 rounded-xl bg-[#ffdad6] p-4 font-bold text-[#93000a]">{error}</p>}
        <div className="overflow-x-auto">
          <table className="w-full text-left">
            <thead><tr>{['Entity', 'Status', 'Processed', 'Skipped', 'New', 'Updated', 'Invalid', 'Started', ''].map(label => <th key={label} className="px-3 py-2 text-xs uppercase">{label}</th>)}</tr></thead>
            <tbody>{items.map(run => {
              const stagedTotal = (run.staged_new_count ?? 0) + (run.staged_update_count ?? 0) + (run.staged_invalid_count ?? 0);
              return <tr key={run.id} className="border-t">
                <td className="px-3 py-3 font-bold">{run.entity}</td>
                <td className="px-3 py-3">{run.status}</td>
                <td className="px-3 py-3">{run.processed ?? '—'}</td>
                <td className="px-3 py-3">{run.skipped ?? '—'}</td>
                <td className="px-3 py-3">{run.staged_new_count ?? 0}</td>
                <td className="px-3 py-3">{run.staged_update_count ?? 0}</td>
                <td className="px-3 py-3">{run.staged_invalid_count ?? 0}</td>
                <td className="px-3 py-3"><time dateTime={run.created_at}>{new Date(run.created_at).toLocaleString()}</time></td>
                <td className="px-3 py-3">{stagedTotal > 0 && <button onClick={() => setDiffRun(run)} className="rounded-xl border-2 border-[#88ceff] px-3 py-2 text-xs font-bold text-[#006590]">View diff</button>}</td>
              </tr>;
            })}</tbody>
          </table>
        </div>
        <div className="mt-5 flex justify-end gap-3">
          <button disabled={page <= 1} onClick={() => setPage(value => value - 1)} className="rounded-xl border-2 border-[#bdc8d2] px-4 py-2 font-bold disabled:opacity-40">Prev</button>
          <span className="py-2 font-bold">{page}</span>
          <button disabled={items.length < HISTORY_PER_PAGE} onClick={() => setPage(value => value + 1)} className="rounded-xl border-2 border-[#bdc8d2] px-4 py-2 font-bold disabled:opacity-40">Next</button>
        </div>
      </>}

    {diffRun && <StagedItemDiffDialog run={diffRun} onClose={() => setDiffRun(null)} />}
  </DataPanel>;
}

const CLASSIFICATION_TABS: Array<{ value: StagedItemClassification | ''; label: string }> = [
  { value: '', label: 'All' },
  { value: 'new', label: 'New' },
  { value: 'update', label: 'Updated' },
  { value: 'invalid', label: 'Invalid' },
];

/** Read-only staged-item diff viewer. No apply/approve action — see issue #45. */
function StagedItemDiffDialog({ run, onClose }: { run: AdminImportRun; onClose: () => void }) {
  const [items, setItems] = useState<StagedItem[]>();
  const [error, setError] = useState('');
  const [classification, setClassification] = useState<StagedItemClassification | ''>('');

  const load = useCallback(async () => {
    setError('');
    try {
      const result = await adminImports.items(run.id, { classification: classification || undefined, perPage: 50 });
      setItems(result.data);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Could not load staged items.');
    }
  }, [run.id, classification]);

  useEffect(() => { void Promise.resolve().then(load); }, [load]);

  return <AccessibleDialog title={`Staged items · ${run.entity}`} description={`Run ${run.request_id.slice(0, 8)}… (read-only review)`} onClose={onClose} className="max-w-3xl">
    <div className="mt-4 flex flex-wrap gap-2">
      {CLASSIFICATION_TABS.map(tab => <button key={tab.value} onClick={() => setClassification(tab.value)} className={`rounded-full px-3 py-1 text-xs font-black uppercase ${classification === tab.value ? 'bg-[#006590] text-white' : 'bg-[#e8f4ff] text-[#006590]'}`}>{tab.label}</button>)}
    </div>

    <div className="mt-5">
      {error && !items ? <StateNotice state="error" message={error} retry={load} />
        : !items ? <StateNotice state="loading" />
        : items.length === 0 ? <StateNotice state="empty" message="No staged items for this filter." />
        : <ul className="space-y-4">{items.map(item => <li key={item.id} className="rounded-2xl border-2 border-[#bdc8d2] p-4">
            <div className="flex items-center justify-between gap-3">
              <span className="font-bold">{item.external_id ?? '—'}</span>
              <span className="rounded-full bg-[#e8f4ff] px-3 py-1 text-xs font-black uppercase text-[#006590]">{item.classification}</span>
            </div>
            {item.errors && item.errors.length > 0 && <ul className="mt-2 list-disc pl-5 text-sm text-[#93000a]">{item.errors.map((message, index) => <li key={index}>{message}</li>)}</ul>}
            <div className="mt-3 grid gap-4 md:grid-cols-2">
              <SnapshotColumn title="Existing" snapshot={item.existing_snapshot} other={item.incoming_snapshot} />
              <SnapshotColumn title="Incoming" snapshot={item.incoming_snapshot} other={item.existing_snapshot} />
            </div>
          </li>)}</ul>}
    </div>
  </AccessibleDialog>;
}

function SnapshotColumn({ title, snapshot, other }: { title: string; snapshot: Record<string, unknown> | null; other: Record<string, unknown> | null }) {
  if (!snapshot) return <div><p className="text-xs font-black uppercase text-[#6e7881]">{title}</p><p className="mt-1 text-sm text-[#6e7881]">—</p></div>;
  const keys = Object.keys(snapshot);
  return <div>
    <p className="text-xs font-black uppercase text-[#6e7881]">{title}</p>
    <dl className="mt-1 space-y-1 text-sm">
      {keys.map(key => {
        const changed = JSON.stringify(snapshot[key]) !== JSON.stringify(other?.[key]);
        return <div key={key} className={changed ? 'rounded bg-[#ffdf92] px-2 py-1' : undefined}><dt className="inline font-bold">{key}: </dt><dd className="inline">{String(snapshot[key] ?? '—')}</dd></div>;
      })}
    </dl>
  </div>;
}
