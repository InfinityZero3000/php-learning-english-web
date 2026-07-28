'use client';

import { useEffect, useState } from 'react';
import AdminLayout from '@/components/AdminLayout';
import { adminCourses, type AdminCourse, type CourseWrite } from '@/lib/api';

const emptyForm: CourseWrite = { title: '', slug: '', description: '', status: 'draft', language: 'en', estimated_duration: 0 };

export default function CoursesPage() {
  const [courses, setCourses] = useState<AdminCourse[]>([]);
  const [search, setSearch] = useState('');
  const [form, setForm] = useState<CourseWrite>(emptyForm);
  const [editingId, setEditingId] = useState<number>();
  const [open, setOpen] = useState(false);
  const [state, setState] = useState<'loading' | 'ready' | 'error'>('loading');
  const [message, setMessage] = useState('');

  async function load(query = search) {
    setState('loading');
    try {
      const response = await adminCourses.list(query);
      setCourses(response.data);
      setState('ready');
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : 'Could not load courses.');
      setState('error');
    }
  }

  // Initial API synchronization.
  // eslint-disable-next-line react-hooks/set-state-in-effect, react-hooks/exhaustive-deps
  useEffect(() => { void load(''); }, []);

  function edit(course?: AdminCourse) {
    setEditingId(course?.id);
    setForm(course ? {
      title: course.title, slug: course.slug, description: course.description ?? '', status: course.status,
      language: course.language ?? 'en', estimated_duration: course.estimated_duration ?? 0,
    } : emptyForm);
    setOpen(true);
  }

  async function save(event: React.FormEvent) {
    event.preventDefault();
    try {
      const saved = editingId ? await adminCourses.update(editingId, form) : await adminCourses.create(form);
      setCourses((items) => editingId ? items.map((item) => item.id === saved.id ? saved : item) : [saved, ...items]);
      setOpen(false);
      setMessage(editingId ? 'Course updated.' : 'Course created.');
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : 'Could not save course.');
    }
  }

  return <AdminLayout title="Courses">
    <div className="space-y-6">
      <header className="flex flex-col justify-between gap-4 lg:flex-row lg:items-end"><div><p className="text-xs font-black uppercase tracking-[.18em]" style={{ color: '#006590' }}>Local learning catalog</p><h2 className="mt-2 text-3xl font-black">Course library</h2><p className="mt-1 font-medium" style={{ color: '#3e4850' }}>Draft, publish, and maintain the course snapshots used by learners.</p></div><button onClick={() => edit()} className="rounded-xl px-6 py-3 font-black text-white" style={{ background: '#006590', borderBottom: '4px solid #004c6e' }}>+ New course</button></header>
      {message && <p role="status" className="rounded-xl p-4 font-bold" style={{ background: '#ffdf92', color: '#594400' }}>{message}</p>}
      <section className="rounded-3xl bg-white p-5" style={{ border: '2px solid #bdc8d2', borderBottomWidth: 4 }}><form onSubmit={(event) => { event.preventDefault(); void load(search); }} className="flex gap-3"><label className="flex-1"><span className="sr-only">Search courses</span><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search courses" className="w-full rounded-xl px-4 py-3" style={{ border: '2px solid #bdc8d2' }} /></label><button className="rounded-xl px-5 py-3 font-bold" style={{ border: '2px solid #88ceff', color: '#006590' }}>Search</button></form>
        {state === 'loading' ? <p className="p-10 text-center font-bold">Loading courses…</p> : state === 'error' ? <p className="p-10 text-center">{message}</p> : courses.length === 0 ? <p className="p-10 text-center">No courses found.</p> : <div className="mt-5 overflow-x-auto"><table className="w-full text-left"><thead><tr>{['Course', 'Status', 'Units', 'Lessons', 'Duration', 'Action'].map((item) => <th key={item} className="px-4 py-3 text-xs uppercase tracking-wider">{item}</th>)}</tr></thead><tbody>{courses.map((course) => <tr key={course.id} style={{ borderTop: '1px solid #efeded' }}><td className="px-4 py-4"><p className="font-black">{course.title}</p><p className="text-sm" style={{ color: '#6e7881' }}>{course.slug}</p></td><td className="px-4 py-4"><span className="rounded-full px-3 py-1 text-xs font-black uppercase" style={{ background: course.status === 'published' ? '#d8f3dc' : course.status === 'archived' ? '#ffdad6' : '#ffdf92', color: course.status === 'published' ? '#1b5e20' : '#594400' }}>{course.status}</span></td><td className="px-4 py-4 font-bold">{course.units_count ?? 0}</td><td className="px-4 py-4 font-bold">{course.lessons_count ?? 0}</td><td className="px-4 py-4">{course.estimated_duration ?? 0} min</td><td className="px-4 py-4"><button onClick={() => edit(course)} className="rounded-xl px-4 py-2 font-bold" style={{ color: '#006590', border: '2px solid #88ceff' }}>Edit</button></td></tr>)}</tbody></table></div>}
      </section>
    </div>
    {open && <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4"><form onSubmit={save} className="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-3xl bg-white p-7"><h3 className="text-2xl font-black">{editingId ? 'Edit course' : 'New course'}</h3><div className="mt-5 grid gap-4 sm:grid-cols-2"><Field label="Title"><input required value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value, ...(!editingId ? { slug: slugify(event.target.value) } : {}) })} /></Field><Field label="Slug"><input required value={form.slug} onChange={(event) => setForm({ ...form, slug: event.target.value })} /></Field><Field label="Status"><select value={form.status} onChange={(event) => setForm({ ...form, status: event.target.value as AdminCourse['status'] })}><option value="draft">Draft</option><option value="published">Published</option><option value="archived">Archived</option></select></Field><Field label="Duration (minutes)"><input type="number" min="0" value={form.estimated_duration ?? 0} onChange={(event) => setForm({ ...form, estimated_duration: Number(event.target.value) })} /></Field></div><Field label="Description" wide><textarea rows={4} value={form.description ?? ''} onChange={(event) => setForm({ ...form, description: event.target.value })} /></Field><div className="mt-6 flex justify-end gap-3"><button type="button" onClick={() => setOpen(false)} className="rounded-xl px-5 py-3 font-bold">Cancel</button><button className="rounded-xl px-5 py-3 font-black text-white" style={{ background: '#006590' }}>Save course</button></div></form></div>}
  </AdminLayout>;
}

function Field({ label, children, wide = false }: { label: string; children: React.ReactElement<{ className?: string }>; wide?: boolean }) {
  return <label className={`${wide ? 'mt-4' : ''} block font-bold`}>{label}<span className="[&>*]:mt-2 [&>*]:w-full [&>*]:rounded-xl [&>*]:px-4 [&>*]:py-3 [&>*]:outline-none [&>*]:[border:2px_solid_#bdc8d2]">{children}</span></label>;
}

function slugify(value: string) {
  return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
}
