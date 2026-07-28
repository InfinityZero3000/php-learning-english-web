'use client';

import { useCallback, useEffect, useState } from 'react';
import AdminLayout from '@/components/AdminLayout';
import { adminImports, auth, type AdminImportCheckpoint, type AdminImportEntity, type AdminImportRun } from '@/lib/api';

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
        <label className="font-bold">Entity<select value={entity} onChange={event => setEntity(event.target.value as AdminImportEntity)} className="mt-2 w-full rounded-xl border-2 border-[#bdc8d2] px-4 py-3">{(['categories', 'courses', 'vocabulary'] as const).map(item => <option key={item}>{item}</option>)}</select></label>
        <label className="font-bold">Limit<input type="number" min="1" max="100" value={limit} onChange={event => setLimit(Math.min(100, Math.max(1, Number(event.target.value))))} className="mt-2 w-full rounded-xl border-2 border-[#bdc8d2] px-4 py-3" /></label>
        <button onClick={() => void start()} disabled={working} className="rounded-xl bg-[#006590] px-6 py-3 font-black text-white disabled:opacity-50">Start / resume</button>
        {superAdmin && <button onClick={() => confirm(`Reset ${entity} checkpoint and import again?`) && void start(true)} disabled={working} className="rounded-xl bg-[#ba1a1a] px-6 py-3 font-black text-white disabled:opacity-50">Reset & retry</button>}
      </div>
    </section>

    <section className="grid gap-4 md:grid-cols-3">
      {loading ? <p className="font-bold">Loading checkpoints…</p> : checkpoints.length === 0 ? <p>No checkpoints found.</p> : checkpoints.map(item => <article key={item.entity} className="rounded-2xl border-2 border-[#bdc8d2] bg-white p-5"><h3 className="text-lg font-black capitalize">{item.entity}</h3><dl className="mt-4 grid grid-cols-2 gap-3 text-sm"><dt>Cursor</dt><dd className="text-right font-black">{item.cursor}</dd><dt>Failures</dt><dd className="text-right font-black">{item.failures}</dd><dt>Last sync</dt><dd className="text-right font-bold">{item.last_synced_at ? new Date(item.last_synced_at).toLocaleString() : 'Never'}</dd></dl></article>)}
    </section>

    {runs.length > 0 && <section className="overflow-x-auto rounded-3xl border-2 border-[#bdc8d2] bg-white p-5"><h3 className="text-lg font-black">Runs started in this session</h3><table className="mt-4 w-full text-left"><thead><tr>{['Entity', 'Status', 'Processed', 'Skipped', 'Cursor', 'Error'].map(label => <th key={label} className="px-3 py-2 text-xs uppercase">{label}</th>)}</tr></thead><tbody>{runs.map(run => <tr key={run.id} className="border-t"><td className="px-3 py-3 font-bold">{run.entity}</td><td className="px-3 py-3">{run.status}</td><td className="px-3 py-3">{run.processed ?? '—'}</td><td className="px-3 py-3">{run.skipped ?? '—'}</td><td className="px-3 py-3">{run.result_cursor ?? run.starting_cursor}</td><td className="px-3 py-3 text-[#93000a]">{run.error_message ?? '—'}</td></tr>)}</tbody></table></section>}
  </div>;
}
