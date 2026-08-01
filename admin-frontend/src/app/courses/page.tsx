'use client';

import { Suspense, useCallback, useEffect, useState } from 'react';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import AccessibleDialog from '@/components/AccessibleDialog';
import { ActionButton } from '@/components/ActionButton';
import AdminLayout from '@/components/AdminLayout';
import {
  adminCatalog,
  adminCourses,
  adminLessons,
  adminUnits,
  type AdminCourse,
  type AdminLesson,
  type AdminLevel,
  type AdminTopic,
  type AdminUnit,
  type CourseWrite,
  type PageMeta,
  type UnitWrite,
} from '@/lib/api';

const empty: CourseWrite = { title: '', slug: '', description: '', status: 'draft', language: 'en', estimated_duration: 0, topic_ids: [] };
export default function Page() { return <AdminLayout title="Courses"><Suspense fallback={<p>Loading…</p>}><Content /></Suspense></AdminLayout>; }

function Content() {
  const query = useSearchParams(), router = useRouter(), pathname = usePathname();
  const [items, setItems] = useState<AdminCourse[]>([]), [meta, setMeta] = useState<PageMeta>(), [levels, setLevels] = useState<AdminLevel[]>([]), [topics, setTopics] = useState<AdminTopic[]>([]), [selected, setSelected] = useState<AdminCourse | null | undefined>(), [form, setForm] = useState<CourseWrite>(empty), [message, setMessage] = useState(''), [saving, setSaving] = useState(false);
  const search = query.get('search') || '', status = query.get('status') || '', levelId = Number(query.get('level_id') || 0), page = Number(query.get('page') || 1), mode = query.get('mode');
  const setQuery = useCallback((patch: Record<string, string>) => { const next = new URLSearchParams(query); Object.entries(patch).forEach(([key, value]) => value ? next.set(key, value) : next.delete(key)); router.replace(`${pathname}?${next}`); }, [pathname, query, router]);
  const load = useCallback(async () => { setMessage(''); try { const [courses, levelPage, topicPage] = await Promise.all([adminCourses.list({ search, status, levelId: levelId || undefined, page }), adminCatalog.levels({ perPage: 100 }), adminCatalog.topics({ perPage: 100 })]); setItems(courses.data); setMeta(courses.meta); setLevels(levelPage.data); setTopics(topicPage.data); const id = Number(query.get('course') || 0); if (mode === 'create') { setSelected(null); setForm(empty); } else if (id) { const course = await adminCourses.get(id); setSelected(course); setForm({ title: course.title, slug: course.slug, description: course.description, status: course.status, language: course.language ?? 'en', estimated_duration: course.estimated_duration ?? 0, level_id: course.level?.id ?? null, topic_ids: course.topics?.map(topic => topic.id) ?? [] }); } else setSelected(undefined); } catch (error) { setMessage(error instanceof Error ? error.message : 'Could not load courses.'); } }, [levelId, mode, page, query, search, status]);
  useEffect(() => { void Promise.resolve().then(load); }, [load]);
  async function save(event: React.FormEvent) { event.preventDefault(); setSaving(true); setMessage(''); try { if (selected?.id) await adminCourses.update(selected.id, form); else await adminCourses.create(form); setQuery({ course: '', mode: '' }); await load(); } catch (error) { setMessage(error instanceof Error ? error.message : 'Could not save course.'); } finally { setSaving(false); } }
  async function act(action: 'publish' | 'archive' | 'delete') { if (!selected) return; if (action === 'delete' && !confirm(`Delete course “${selected.title}”?`)) return; setSaving(true); try { await adminCourses[action](selected.id); setQuery({ course: '', mode: '' }); await load(); } catch (error) { setMessage(error instanceof Error ? error.message : `Could not ${action} course.`); } finally { setSaving(false); } }
  const close = () => setQuery({ course: '', mode: '' });

  return <div className="space-y-6">
    <header className="flex flex-wrap justify-between gap-4"><div><h1 className="text-3xl font-black">Course library</h1><p>Khóa học · Course administration</p></div><button onClick={() => setQuery({ mode: 'create', course: '' })} className="rounded-xl bg-[#006590] px-5 py-3 font-bold text-white">New course</button></header>
    {message && <p role="alert" className="rounded-xl bg-[#ffdf92] p-3">{message}</p>}
    <div className="grid gap-3 sm:grid-cols-3"><input aria-label="Search courses" defaultValue={search} onBlur={event => setQuery({ search: event.target.value, page: '' })} placeholder="Search courses…" className="rounded-xl border-2 p-3"/><select aria-label="Status" value={status} onChange={event => setQuery({ status: event.target.value, page: '' })} className="rounded-xl border-2 p-3"><option value="">All statuses</option><option value="draft">Draft</option><option value="published">Published</option><option value="archived">Archived</option></select><select aria-label="Level" value={levelId || ''} onChange={event => setQuery({ level_id: event.target.value, page: '' })} className="rounded-xl border-2 p-3"><option value="">All levels</option>{levels.map(level => <option key={level.id} value={level.id}>{level.name}</option>)}</select></div>
    <section className="overflow-x-auto rounded-3xl border-2 border-[#bdc8d2] bg-white"><table className="w-full min-w-[640px] text-left"><thead><tr><th className="p-4">Course</th><th>Status</th><th>Level</th><th>Actions</th></tr></thead><tbody>{items.map(course => <tr key={course.id} className="border-t"><td className="p-4 font-bold">{course.title}</td><td>{course.status}</td><td>{course.level?.name ?? '—'}</td><td className="p-4"><div className="flex flex-wrap gap-2"><ActionButton variant="neutral" onClick={() => setQuery({ course: String(course.id), mode: 'view' })}>View</ActionButton><ActionButton variant="primary" onClick={() => setQuery({ course: String(course.id), mode: 'edit' })}>Edit</ActionButton></div></td></tr>)}</tbody></table></section>
    <nav aria-label="Course pages" className="flex justify-center gap-3"><button disabled={page <= 1} onClick={() => setQuery({ page: String(page - 1) })}>Previous</button><span>{page}/{meta?.last_page ?? 1}</span><button disabled={page >= (meta?.last_page ?? 1)} onClick={() => setQuery({ page: String(page + 1) })}>Next</button></nav>
    {selected !== undefined && <AccessibleDialog className={mode === 'view' ? 'max-w-6xl' : 'max-w-2xl'} title={mode === 'view' ? selected?.title ?? 'Course' : selected ? 'Edit course' : 'New course'} onClose={close}>{mode === 'view' && selected ? <><div className="mt-3 flex flex-wrap items-start justify-between gap-4 rounded-2xl bg-[#f5f3f3] p-5"><div><p className="max-w-2xl text-[#3e4850]">{selected.description || 'No description'}</p><p className="mt-2 text-sm font-bold text-[#006590]">Topics: {selected.topics?.map(topic => topic.name).join(', ') || '—'}</p></div><div className="flex flex-wrap gap-2"><ActionButton variant="primary" onClick={() => setQuery({ mode: 'edit' })}>Edit</ActionButton>{selected.status === 'draft' && <ActionButton variant="success" disabled={saving} onClick={() => void act('publish')}>Publish</ActionButton>}{selected.status !== 'archived' && <ActionButton variant="neutral" disabled={saving} onClick={() => void act('archive')}>Archive</ActionButton>}{selected.status === 'draft' && <ActionButton variant="danger" disabled={saving} onClick={() => void act('delete')}>Delete</ActionButton>}</div></div><CourseOutline course={selected}/></> : <form onSubmit={save} className="mt-4 grid gap-4"><label className="font-bold">Title<input required value={form.title} onChange={event => setForm({ ...form, title: event.target.value })} className="mt-2 w-full rounded-xl border-2 p-3"/></label><label className="font-bold">Slug<input required value={form.slug} onChange={event => setForm({ ...form, slug: event.target.value })} className="mt-2 w-full rounded-xl border-2 p-3"/></label><div className="grid gap-4 sm:grid-cols-2"><label className="font-bold">Language<input required maxLength={2} value={form.language ?? 'en'} onChange={event => setForm({ ...form, language: event.target.value.toLowerCase() })} className="mt-2 w-full rounded-xl border-2 p-3"/></label><label className="font-bold">Duration (minutes)<input type="number" min="0" value={form.estimated_duration ?? 0} onChange={event => setForm({ ...form, estimated_duration: Number(event.target.value) })} className="mt-2 w-full rounded-xl border-2 p-3"/></label></div><label className="font-bold">Level<select value={form.level_id ?? ''} onChange={event => setForm({ ...form, level_id: event.target.value ? Number(event.target.value) : null })} className="mt-2 w-full rounded-xl border-2 p-3"><option value="">No level</option>{levels.map(level => <option key={level.id} value={level.id}>{level.name}</option>)}</select></label><label className="font-bold">Topics<select multiple value={(form.topic_ids ?? []).map(String)} onChange={event => setForm({ ...form, topic_ids: Array.from(event.target.selectedOptions, option => Number(option.value)) })} className="mt-2 min-h-32 w-full rounded-xl border-2 p-3">{topics.map(topic => <option key={topic.id} value={topic.id}>{topic.name}</option>)}</select></label><label className="font-bold">Description<textarea value={form.description ?? ''} onChange={event => setForm({ ...form, description: event.target.value })} className="mt-2 w-full rounded-xl border-2 p-3"/></label><div className="flex justify-end gap-3"><ActionButton type="button" variant="neutral" className="px-5 py-3 text-sm normal-case" onClick={close}>Cancel</ActionButton><button disabled={saving} className="rounded-xl bg-[#006590] px-5 py-3 font-bold text-white disabled:opacity-60">{saving ? 'Saving…' : 'Save course'}</button></div></form>}</AccessibleDialog>}
  </div>;
}

const newUnit = (courseId: number, order: number): UnitWrite => ({
  course_id: courseId,
  title: '',
  description: '',
  sort_order: order,
  icon_url: '',
  background_color: '#E8F4F8',
});

export function CourseOutline({ course }: { course: AdminCourse }) {
  const [units, setUnits] = useState<AdminUnit[]>([]);
  const [lessons, setLessons] = useState<AdminLesson[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [editing, setEditing] = useState<{ id?: number; data: UnitWrite }>();

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [nextUnits, lessonPage] = await Promise.all([
        adminUnits.list(course.id),
        adminLessons.list({ courseId: course.id, perPage: 100 }),
      ]);
      setUnits(nextUnits);
      setLessons(lessonPage.data);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Could not load course outline.');
    } finally {
      setLoading(false);
    }
  }, [course.id]);

  useEffect(() => { void Promise.resolve().then(load); }, [load]);

  async function run(action: () => Promise<unknown>) {
    setBusy(true);
    setError('');
    try {
      await action();
      await load();
      return true;
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Could not update course outline.');
      return false;
    } finally {
      setBusy(false);
    }
  }

  async function saveUnit(event: React.FormEvent) {
    event.preventDefault();
    if (!editing) return;
    const saved = await run(() => editing.id
      ? adminUnits.update(editing.id, editing.data)
      : adminUnits.create(editing.data));
    if (saved) setEditing(undefined);
  }

  function move(index: number, direction: -1 | 1) {
    const next = units.map(unit => unit.id);
    [next[index], next[index + direction]] = [next[index + direction], next[index]];
    void run(() => adminUnits.reorder(course.id, next));
  }

  function assign(lesson: AdminLesson, unitId: number | null) {
    void run(() => adminLessons.update(lesson.id, {
      course_id: course.id,
      unit_id: unitId,
      title: lesson.title,
      slug: lesson.slug,
      content: lesson.content ?? null,
      sort_order: lesson.sort_order,
      estimated_minutes: lesson.estimated_minutes ?? null,
      status: lesson.status,
      prerequisite_ids: lesson.prerequisite_ids ?? [],
    }));
  }

  if (loading) return <p className="mt-8 rounded-2xl border-2 border-dashed border-[#bdc8d2] p-8 text-center font-bold text-[#3e4850]">Loading course outline…</p>;

  return <section className="mt-8" aria-labelledby="course-outline-heading">
    <div className="flex flex-wrap items-end justify-between gap-4">
      <div><p className="text-xs font-black uppercase tracking-[.2em] text-[#006590]">Editorial learning</p><h3 id="course-outline-heading" className="mt-1 text-2xl font-black">Course outline</h3><p className="text-sm text-[#3e4850]">Shape the learning sequence without losing local ownership.</p></div>
      <button disabled={busy} onClick={() => setEditing({ data: newUnit(course.id, Math.max(-1, ...units.map(unit => unit.sort_order)) + 1) })} className="rounded-xl bg-[#006590] px-4 py-2.5 font-black text-white disabled:opacity-50">Add unit</button>
    </div>
    {error && <p role="alert" className="mt-4 rounded-xl bg-[#ffdad6] p-3 font-bold text-[#93000a]">{error}</p>}
    {editing && <form onSubmit={saveUnit} className="mt-5 grid gap-3 rounded-2xl border-2 border-[#006590] bg-[#e8f4ff] p-5 md:grid-cols-2">
      <label className="font-bold">Unit title<input required value={editing.data.title} onChange={event => setEditing({ ...editing, data: { ...editing.data, title: event.target.value } })} className="mt-2 w-full rounded-xl border-2 border-[#bdc8d2] bg-white p-3"/></label>
      <label className="font-bold">Accent color<input type="color" value={editing.data.background_color ?? '#E8F4F8'} onChange={event => setEditing({ ...editing, data: { ...editing.data, background_color: event.target.value } })} className="mt-2 h-[3.25rem] w-full rounded-xl border-2 border-[#bdc8d2] bg-white p-2"/></label>
      <label className="font-bold md:col-span-2">Description<textarea value={editing.data.description ?? ''} onChange={event => setEditing({ ...editing, data: { ...editing.data, description: event.target.value } })} className="mt-2 w-full rounded-xl border-2 border-[#bdc8d2] bg-white p-3"/></label>
      <div className="flex justify-end gap-3 md:col-span-2"><ActionButton type="button" variant="neutral" className="px-4 py-2 text-sm normal-case" onClick={() => setEditing(undefined)}>Cancel</ActionButton><button disabled={busy} className="rounded-xl bg-[#006590] px-4 py-2 font-bold text-white disabled:opacity-60">Save unit</button></div>
    </form>}
    {!units.length ? <div className="mt-5 rounded-3xl border-2 border-dashed border-[#bdc8d2] bg-white p-12 text-center"><h4 className="text-xl font-black">No units yet</h4><p className="mt-2 text-[#3e4850]">Add the first unit to organize this course.</p></div> : <div className="mt-5 grid gap-4">
      {units.map((unit, index) => <article key={unit.id} aria-label={unit.title} className="overflow-hidden rounded-3xl border-2 border-[#bdc8d2] border-b-4 bg-white">
        <div className="flex flex-wrap items-start justify-between gap-4 p-5" style={{ backgroundColor: unitColor(unit.background_color) }}>
          <div><div className="flex flex-wrap items-center gap-2"><span className="grid h-8 w-8 place-items-center rounded-full bg-white/80 text-sm font-black">{index + 1}</span><h4 className="text-xl font-black">{unit.title}</h4><Badge>{unit.status}</Badge><Badge>{unit.source_system === 'local' || unit.local_override_at ? 'Local edit' : sourceLabel(unit.source_system)}</Badge></div><p className="mt-2 max-w-2xl text-sm text-[#3e4850]">{unit.description || 'No description'}</p><p className="mt-2 text-xs font-bold text-[#6e7881]">Revision {unit.catalog_revision} · {unit.lessons.length} lessons</p></div>
          <div className="flex flex-wrap gap-2">
            <ActionButton aria-label="Move up" variant="neutral" disabled={busy || index === 0} onClick={() => move(index, -1)} className="px-2.5">↑</ActionButton>
            <ActionButton aria-label="Move down" variant="neutral" disabled={busy || index === units.length - 1} onClick={() => move(index, 1)} className="px-2.5">↓</ActionButton>
            <ActionButton variant="primary" disabled={busy} onClick={() => setEditing({ id: unit.id, data: { course_id: course.id, title: unit.title, description: unit.description, sort_order: unit.sort_order, icon_url: unit.icon_url, background_color: unit.background_color } })}>Edit</ActionButton>
            {unit.status === 'draft' && <ActionButton variant="success" disabled={busy} onClick={() => void run(() => adminUnits.publish(unit.id))}>Publish</ActionButton>}
            {unit.status !== 'archived' && <ActionButton variant="neutral" disabled={busy} onClick={() => void run(() => adminUnits.archive(unit.id))}>Archive</ActionButton>}
            {unit.status === 'draft' && !unit.lessons.length && <ActionButton variant="danger" disabled={busy} onClick={() => { if (confirm(`Delete unit “${unit.title}”?`)) void run(() => adminUnits.delete(unit.id)); }}>Delete</ActionButton>}
          </div>
        </div>
        <div className="divide-y divide-[#e3e2e2]">
          {unit.lessons.length ? unit.lessons.map(summary => {
            const lesson = lessons.find(item => item.id === summary.id);
            return lesson ? <LessonRow key={lesson.id} lesson={lesson} units={units} busy={busy} assign={assign}/> : null;
          }) : <p className="p-5 text-sm font-medium text-[#6e7881]">No lessons assigned.</p>}
        </div>
      </article>)}
    </div>}
    {lessons.some(lesson => !lesson.unit_id) && <div className="mt-5 rounded-2xl border-2 border-dashed border-[#bdc8d2] bg-white p-5"><h4 className="font-black">Unassigned lessons</h4>{lessons.filter(lesson => !lesson.unit_id).map(lesson => <LessonRow key={lesson.id} lesson={lesson} units={units} busy={busy} assign={assign}/>)}</div>}
  </section>;
}

function LessonRow({ lesson, units, busy, assign }: { lesson: AdminLesson; units: AdminUnit[]; busy: boolean; assign: (lesson: AdminLesson, unitId: number | null) => void }) {
  return <div className="flex flex-wrap items-center justify-between gap-3 p-4"><div><p className="font-bold">{lesson.title}</p><p className="text-xs font-bold text-[#6e7881]">{lesson.status} · {lesson.source_system === 'local' || lesson.local_override_at ? 'Local edit' : sourceLabel(lesson.source_system)}</p></div><label className="text-xs font-black uppercase tracking-wider text-[#3e4850]">Unit<select aria-label={`Assign ${lesson.title}`} disabled={busy} value={lesson.unit_id ?? ''} onChange={event => assign(lesson, event.target.value ? Number(event.target.value) : null)} className="ml-2 rounded-lg border-2 border-[#bdc8d2] bg-white p-2 normal-case tracking-normal"><option value="">Unassigned</option>{units.map(unit => <option key={unit.id} value={unit.id}>{unit.title}</option>)}</select></label></div>;
}

function Badge({ children }: { children: React.ReactNode }) {
  return <span className="rounded-full border border-black/10 bg-white/75 px-2.5 py-1 text-[11px] font-black uppercase tracking-wider">{children}</span>;
}

function sourceLabel(source?: string) {
  return source === 'lexilingo' ? 'LexiLingo' : source || 'Local edit';
}

function unitColor(color?: string | null) {
  return color && /^#(?:[A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/.test(color) ? color : '#f5f3f3';
}
