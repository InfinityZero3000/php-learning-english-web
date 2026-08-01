'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import AdminLayout from '@/components/AdminLayout';
import AccessibleDialog from '@/components/AccessibleDialog';
import { DataPanel, StateNotice } from '@/components/AdminDataView';
import {
  ApiError, adminFileImports, redirectToGoogleStepUp,
  type AdminImportRun, type FileCatalogStagedItem, type PageMeta,
} from '@/lib/api';

export default function ImportFilePage() {
  return <AdminLayout title="File Import"><PageContent /></AdminLayout>;
}

function PageContent() {
  const [runs, setRuns] = useState<AdminImportRun[]>([]);
  const [meta, setMeta] = useState<PageMeta>();
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [diffRun, setDiffRun] = useState<AdminImportRun | null>(null);
  const fileInput = useRef<HTMLInputElement>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const result = await adminFileImports.runs({ perPage: 20 });
      setRuns(result.data);
      setMeta(result.meta);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Could not load file import runs.');
    } finally { setLoading(false); }
  }, []);

  // eslint-disable-next-line react-hooks/set-state-in-effect
  useEffect(() => { void load(); }, [load]);

  async function upload(event: React.FormEvent) {
    event.preventDefault();
    const file = fileInput.current?.files?.[0];
    if (!file) { setMessage('Choose a CSV, XLSX, XLS, or PDF file first.'); return; }
    setUploading(true);
    setMessage('');
    try {
      const result = await adminFileImports.upload(file);
      setRuns(items => [result.run, ...items.filter(item => item.id !== result.run.id)]);
      const courses = result.staged_items.data.filter(item => item.classification === 'new').length;
      const problems = result.staged_items.data.find(item => item.classification === 'invalid')?.errors?.length ?? 0;
      setMessage(`Staged ${courses} course${courses === 1 ? '' : 's'} for review.${problems ? ` ${problems} row(s) could not be placed and need attention.` : ''}`);
      if (fileInput.current) fileInput.current.value = '';
    } catch (reason) {
      if (reason instanceof ApiError && reason.status === 422) {
        setMessage(`File rejected: ${reason.message}`);
      } else {
        setMessage(reason instanceof Error ? reason.message : 'Could not upload the file.');
      }
    } finally { setUploading(false); }
  }

  return <div className="space-y-6">
    <header className="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
      <div>
        <p className="text-xs font-black uppercase tracking-[.18em] text-[#006590]">Bulk content import</p>
        <h2 className="mt-2 text-3xl font-black">Course file import</h2>
        <p className="mt-1 font-medium text-[#3e4850]">Upload a CSV, XLSX, XLS, or text-layer PDF built from the template below to stage new courses for review.</p>
      </div>
      <a href={adminFileImports.templateUrl} className="rounded-xl border-2 border-[#88ceff] px-5 py-3 font-bold text-[#006590]">Download template (CSV)</a>
    </header>

    <section className="rounded-3xl border-2 border-[#bdc8d2] bg-white p-6">
      <form onSubmit={event => void upload(event)} className="grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
        <label className="font-bold">File
          <input ref={fileInput} type="file" accept=".csv,.xlsx,.xls,.pdf" className="mt-2 block w-full rounded-xl border-2 border-[#bdc8d2] px-4 py-3" />
        </label>
        <button type="submit" disabled={uploading} className="rounded-xl bg-[#006590] px-6 py-3 font-black text-white disabled:opacity-50">{uploading ? 'Uploading…' : 'Upload'}</button>
      </form>
      {message && <p role="status" className="mt-4 rounded-2xl bg-[#ffdf92] p-4 font-bold text-[#594400]">{message}</p>}
    </section>

    <DataPanel title={`Runs · ${meta?.total ?? runs.length}`}>
      {error && runs.length === 0 ? <StateNotice state="error" message={error} retry={load} />
        : loading && runs.length === 0 ? <StateNotice state="loading" />
        : runs.length === 0 ? <StateNotice state="empty" message="No file imports yet." />
        : <div className="overflow-x-auto">
          <table className="w-full text-left">
            <thead><tr>{['Status', 'Courses', 'Started', ''].map(label => <th key={label} className="px-3 py-2 text-xs uppercase">{label}</th>)}</tr></thead>
            <tbody>{runs.map(run => <tr key={run.id} className="border-t">
              <td className="px-3 py-3 font-bold">{run.status}</td>
              <td className="px-3 py-3">{run.staged_new_count ?? '—'}</td>
              <td className="px-3 py-3"><time dateTime={run.created_at}>{new Date(run.created_at).toLocaleString()}</time></td>
              <td className="px-3 py-3"><button onClick={() => setDiffRun(run)} className="rounded-xl border-2 border-[#88ceff] px-3 py-2 text-xs font-bold text-[#006590]">Review</button></td>
            </tr>)}</tbody>
          </table>
        </div>}
    </DataPanel>

    {diffRun && <FileRunReviewDialog run={diffRun} onClose={() => setDiffRun(null)} onApplied={load} />}
  </div>;
}

/**
 * Per-course preview: title, its units/lessons/word counts, and any row
 * errors that couldn't be placed. Apply follows the same 428 step-up
 * handoff as the LexiLingo import page (see redirectToGoogleStepUp).
 */
function FileRunReviewDialog({ run, onClose, onApplied }: { run: AdminImportRun; onClose: () => void; onApplied: () => void }) {
  const [items, setItems] = useState<FileCatalogStagedItem[]>();
  const [error, setError] = useState('');
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [applying, setApplying] = useState(false);
  const [applyMessage, setApplyMessage] = useState('');

  const load = useCallback(async () => {
    setError('');
    try {
      const result = await adminFileImports.items(run.id, { perPage: 50 });
      setItems(result.data);
      setSelected(new Set());
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Could not load staged courses.');
    }
  }, [run.id]);

  useEffect(() => { void Promise.resolve().then(load); }, [load]);

  const applyableItems = items?.filter(item => item.status === 'staged' && item.classification === 'new') ?? [];

  function toggle(id: number) {
    setSelected(current => {
      const next = new Set(current);
      if (next.has(id)) next.delete(id); else next.add(id);
      return next;
    });
  }

  async function apply() {
    if (selected.size === 0) return;
    setApplying(true);
    setApplyMessage('');
    try {
      const result = await adminFileImports.apply(run.id, Array.from(selected));
      setApplyMessage(`Applied ${result.applied.length}, failed ${result.failed.length}.`);
      onApplied();
      await load();
    } catch (reason) {
      if (redirectToGoogleStepUp(reason, '/import-file')) return;
      setApplyMessage(reason instanceof Error ? reason.message : 'Could not apply the selected courses.');
    } finally { setApplying(false); }
  }

  return <AccessibleDialog title={`File import · run ${run.id}`} description="Review staged courses before applying them to the catalog" onClose={onClose} className="max-w-3xl">
    {applyMessage && <p role="status" className="mt-3 rounded-xl bg-[#ffdf92] p-3 font-bold text-[#594400]">{applyMessage}</p>}

    <div className="mt-4">
      {error && !items ? <StateNotice state="error" message={error} retry={load} />
        : !items ? <StateNotice state="loading" />
        : items.length === 0 ? <StateNotice state="empty" message="No staged courses for this run." />
        : <ul className="space-y-4">{items.map(item => {
            const applyable = item.status === 'staged' && item.classification === 'new';
            const tree = item.incoming_snapshot;
            const unitCount = tree?.units.length ?? 0;
            const lessonCount = tree?.units.reduce((sum, unit) => sum + unit.lessons.length, 0) ?? 0;
            const wordCount = tree?.units.reduce((sum, unit) => sum + unit.lessons.reduce((s, lesson) => s + lesson.vocabulary.length, 0), 0) ?? 0;
            return <li key={item.id} className="rounded-2xl border-2 border-[#bdc8d2] p-4">
              <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                  {applyable && <input type="checkbox" checked={selected.has(item.id)} onChange={() => toggle(item.id)} aria-label={`Select ${tree?.title ?? 'item'}`} />}
                  <span className="font-bold">{tree?.title ?? '(unplaced rows)'}</span>
                </div>
                <div className="flex items-center gap-2">
                  <span className="rounded-full bg-[#e8f4ff] px-3 py-1 text-xs font-black uppercase text-[#006590]">{item.classification}</span>
                  {item.status !== 'staged' && <span className="rounded-full bg-[#ffdf92] px-3 py-1 text-xs font-black uppercase text-[#594400]">{item.status}</span>}
                </div>
              </div>
              {tree && <p className="mt-2 text-sm text-[#3e4850]">{unitCount} unit{unitCount === 1 ? '' : 's'} · {lessonCount} lesson{lessonCount === 1 ? '' : 's'} · {wordCount} word{wordCount === 1 ? '' : 's'}</p>}
              {item.errors && item.errors.length > 0 && <ul className="mt-2 list-disc pl-5 text-sm text-[#93000a]">{item.errors.map((message, index) => <li key={index}>{message}</li>)}</ul>}
            </li>;
          })}</ul>}
    </div>

    {applyableItems.length > 0 && <div className="mt-5 flex justify-end"><button onClick={() => void apply()} disabled={applying || selected.size === 0} className="rounded-xl bg-[#006590] px-6 py-3 font-black text-white disabled:opacity-50">Apply selected ({selected.size})</button></div>}
  </AccessibleDialog>;
}
