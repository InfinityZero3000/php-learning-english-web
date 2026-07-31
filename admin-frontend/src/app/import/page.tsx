'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import AdminLayout from '@/components/AdminLayout';
import ImportDiffPanel from '@/components/ImportDiffPanel';
import ImportReviewTable, { cascadeExcluded } from '@/components/ImportReviewTable';
import ImportRunList from '@/components/ImportRunList';
import {
  ApiError, adminImports, auth,
  type AdminImportAction, type AdminImportCheckpoint, type AdminImportEntity,
  type AdminImportItem, type AdminImportRun,
} from '@/lib/api';

const ACTIVE_STATUSES = ['fetching', 'validating', 'applying'];
const POLL_FAST_MS = 5_000;
const POLL_SLOW_MS = 30_000;
const SLOW_AFTER_MS = 60_000;

export default function ImportPage() {
  return <AdminLayout title="Import Jobs"><ImportWorkspace /></AdminLayout>;
}

export function ImportWorkspace() {
  const [checkpoints, setCheckpoints] = useState<AdminImportCheckpoint[]>([]);
  const [runs, setRuns] = useState<AdminImportRun[]>([]);
  const [entity, setEntity] = useState<AdminImportEntity>('courses');
  const [limit, setLimit] = useState(50);
  const [superAdmin, setSuperAdmin] = useState(false);
  const [loading, setLoading] = useState(true);
  const [working, setWorking] = useState(false);
  const [message, setMessage] = useState('');
  const [run, setRun] = useState<AdminImportRun | null>(null);
  const [items, setItems] = useState<AdminImportItem[]>([]);
  const [draft, setDraft] = useState<Record<number, AdminImportAction>>({});
  const [focused, setFocused] = useState<number | null>(null);

  const runId = run?.id ?? null;
  const active = run !== null && ACTIVE_STATUSES.includes(run.status);
  const excluded = useMemo(() => cascadeExcluded(items, draft), [items, draft]);
  const blockedByInvalid = items.some((item) =>
    item.classification === 'invalid' && (draft[item.id] ?? item.selected_action) !== 'exclude');

  const openRun = useCallback(async (id: number) => {
    setMessage('');
    try {
      const [detail, staged] = await Promise.all([adminImports.run(id), adminImports.items(id)]);
      setRun(detail);
      setItems(staged);
      setDraft({});
      setFocused(null);
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : 'Could not open the import run.');
    }
  }, []);

  const load = useCallback(async () => {
    setLoading(true);
    setMessage('');
    try {
      const [points, history, user] = await Promise.all([adminImports.checkpoints(), adminImports.history(), auth.adminMe()]);
      setCheckpoints(points);
      setRuns(history);
      setSuperAdmin(user.role === 'super_admin');
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : 'Could not load import checkpoints.');
    } finally { setLoading(false); }
  }, []);

  /* eslint-disable react-hooks/set-state-in-effect -- initial load and deep-linked run */
  useEffect(() => {
    void load();
    const requested = Number(new URLSearchParams(window.location.search).get('run'));
    if (Number.isInteger(requested) && requested > 0) void openRun(requested);
  }, [load, openRun]);
  /* eslint-enable react-hooks/set-state-in-effect */

  useEffect(() => {
    if (runId === null || !active) return;
    const startedAt = Date.now();
    let cancelled = false;
    let timer: ReturnType<typeof setTimeout>;
    const schedule = () => {
      timer = setTimeout(async () => {
        const fresh = await adminImports.run(runId).catch(() => null);
        if (cancelled) return;
        if (fresh) {
          setRun(fresh);
          if (!ACTIVE_STATUSES.includes(fresh.status)) {
            setItems(await adminImports.items(runId).catch(() => []));
            return;
          }
        }
        schedule();
      }, Date.now() - startedAt >= SLOW_AFTER_MS ? POLL_SLOW_MS : POLL_FAST_MS);
    };
    schedule();

    return () => { cancelled = true; clearTimeout(timer); };
  }, [runId, active]);

  function stepUp(id: number) {
    window.location.assign(`/api/v1/auth/oauth/google/admin?return=${encodeURIComponent(`/import?run=${id}`)}`);
  }

  async function mutate(label: string, action: (id: number) => Promise<AdminImportRun>) {
    if (runId === null) return;
    setWorking(true);
    setMessage('');
    try {
      const next = await action(runId);
      setRun(next);
      setItems(await adminImports.items(next.id));
      setDraft({});
      setRuns((list) => list.map((item) => (item.id === next.id ? { ...item, ...next } : item)));
      setMessage(`${label}: ${next.status.replace('_', ' ')}.`);
    } catch (reason) {
      if (reason instanceof ApiError && reason.status === 428) { stepUp(runId); return; }
      if (reason instanceof ApiError && reason.status === 409) {
        await openRun(runId);
        setMessage(`${reason.message} The draft was refreshed with the current catalog state.`);
        return;
      }
      setMessage(reason instanceof Error ? reason.message : `Could not ${label.toLowerCase()}.`);
    } finally { setWorking(false); }
  }

  async function start(reset = false) {
    setWorking(true);
    setMessage('');
    try {
      const started = reset ? await adminImports.reset(entity, limit) : await adminImports.resume(entity, limit);
      setRuns((list) => [started, ...list.filter((item) => item.id !== started.id)]);
      await openRun(started.id);
      setMessage(`Import ${started.status.replace('_', ' ')}.`);
    } catch (reason) {
      if (reason instanceof ApiError && reason.status === 428 && runId !== null) { stepUp(runId); return; }
      setMessage(reason instanceof Error ? reason.message : 'Could not start import.');
    } finally { setWorking(false); }
  }

  const draftEntries = Object.entries(draft).map(([id, action]) => ({ id: Number(id), action }));

  return <div className="space-y-6">
    <header className="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
      <div><p className="text-xs font-black uppercase tracking-[.18em] text-[#006590]">LexiLingo provider</p><h2 className="mt-2 text-3xl font-black">Staged imports</h2><p className="mt-1 font-medium text-[#3e4850]">Fetching stages records for review. Nothing reaches the catalog until an authorised admin approves and applies it.</p></div>
      <button onClick={() => void load()} disabled={loading} className="rounded-xl border-2 border-[#88ceff] px-5 py-3 font-bold text-[#006590] disabled:opacity-50">Refresh</button>
    </header>

    {message && <p role="status" className="rounded-2xl bg-[#ffdf92] p-4 font-bold text-[#594400]">{message}</p>}

    <section className="rounded-3xl border-2 border-[#bdc8d2] bg-white p-6">
      <div className="grid gap-4 md:grid-cols-[1fr_160px_auto_auto] md:items-end">
        <label className="font-bold">Entity<select value={entity} onChange={event => setEntity(event.target.value as AdminImportEntity)} className="mt-2 w-full rounded-xl border-2 border-[#bdc8d2] px-4 py-3">{(['categories', 'courses', 'vocabulary'] as const).map(item => <option key={item}>{item}</option>)}</select></label>
        <label className="font-bold">Limit<input type="number" min="1" max="100" value={limit} onChange={event => setLimit(Math.min(100, Math.max(1, Number(event.target.value))))} className="mt-2 w-full rounded-xl border-2 border-[#bdc8d2] px-4 py-3" /></label>
        <button onClick={() => void start()} disabled={working} className="rounded-xl bg-[#006590] px-6 py-3 font-black text-white disabled:opacity-50">Stage / resume</button>
        {superAdmin && <button onClick={() => confirm(`Reset ${entity} checkpoint and stage again?`) && void start(true)} disabled={working} className="rounded-xl bg-[#ba1a1a] px-6 py-3 font-black text-white disabled:opacity-50">Reset & stage</button>}
      </div>
    </section>

    <section className="grid gap-4 md:grid-cols-3">
      {loading ? <p className="font-bold">Loading checkpoints…</p> : checkpoints.length === 0 ? <p>No checkpoints found.</p> : checkpoints.map(item => <article key={item.entity} className="rounded-2xl border-2 border-[#bdc8d2] bg-white p-5"><h3 className="text-lg font-black capitalize">{item.entity}</h3><dl className="mt-4 grid grid-cols-2 gap-3 text-sm"><dt>Cursor</dt><dd className="text-right font-black">{item.cursor}</dd><dt>Failures</dt><dd className="text-right font-black">{item.failures}</dd><dt>Last sync</dt><dd className="text-right font-bold">{item.last_synced_at ? new Date(item.last_synced_at).toLocaleString() : 'Never'}</dd></dl></article>)}
    </section>

    <div className="grid gap-6 lg:grid-cols-[320px_1fr] lg:items-start">
      <ImportRunList runs={runs} selectedId={runId} onSelect={(id) => void openRun(id)} />

      {run === null ? <p className="rounded-3xl border-2 border-[#bdc8d2] bg-white p-6 font-bold">Select a run to review its staged records.</p> : <div className="space-y-4">
        <section className="rounded-3xl border-2 border-[#bdc8d2] bg-white p-6">
          <h3 className="text-xl font-black capitalize">{run.entity} · {run.status.replace('_', ' ')}</h3>
          <ul aria-label="Classification counts" className="mt-4 flex flex-wrap gap-3">
            {Object.entries(run.counts ?? {}).map(([classification, total]) => (
              <li key={classification} className="rounded-full bg-[#e3e8ec] px-4 py-2 text-sm font-black">{classification.replace('_', ' ')}: {total}</li>
            ))}
          </ul>
          {run.error_message && <p role="alert" className="mt-4 rounded-2xl bg-[#ffdad6] p-3 font-bold text-[#93000a]">{run.error_message}</p>}
          {blockedByInvalid && <p role="alert" className="mt-4 rounded-2xl bg-[#ffdad6] p-3 font-bold text-[#93000a]">Invalid records must be excluded before approval.</p>}
          <div className="mt-5 flex flex-wrap gap-3">
            <button onClick={() => void mutate('Draft saved', (id) => adminImports.saveDraft(id, draftEntries))} disabled={working || draftEntries.length === 0} className="rounded-xl border-2 border-[#006590] px-5 py-3 font-black text-[#006590] disabled:opacity-50">Save draft</button>
            <button onClick={() => void mutate('Approved', adminImports.approve)} disabled={working || blockedByInvalid || run.status !== 'review_ready'} className="rounded-xl bg-[#006590] px-5 py-3 font-black text-white disabled:opacity-50">Approve</button>
            <button onClick={() => void mutate('Applied', adminImports.apply)} disabled={working || run.status !== 'approved'} className="rounded-xl bg-[#0d4b22] px-5 py-3 font-black text-white disabled:opacity-50">Apply to catalog</button>
            <button onClick={() => void mutate('Cancelled', adminImports.cancel)} disabled={working || !['fetching', 'validating', 'review_ready', 'approved'].includes(run.status)} className="rounded-xl border-2 border-[#bdc8d2] px-5 py-3 font-bold disabled:opacity-50">Cancel</button>
            <button onClick={() => void mutate('Retried', adminImports.retry)} disabled={working || !['validation_failed', 'apply_failed', 'cancelled'].includes(run.status)} className="rounded-xl border-2 border-[#ba1a1a] px-5 py-3 font-bold text-[#ba1a1a] disabled:opacity-50">Retry</button>
          </div>
        </section>

        <ImportReviewTable
          items={items}
          draft={draft}
          excluded={excluded}
          selectedId={focused}
          onSelect={setFocused}
          onAction={(id, action) => setDraft((current) => ({ ...current, [id]: action }))}
          disabled={working || !['review_ready', 'apply_failed'].includes(run.status)}
        />

        {focused !== null && items.some((item) => item.id === focused) &&
          <ImportDiffPanel item={items.find((item) => item.id === focused)!} />}
      </div>}
    </div>
  </div>;
}
