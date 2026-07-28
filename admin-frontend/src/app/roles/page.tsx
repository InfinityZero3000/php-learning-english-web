'use client';

import { useEffect, useState } from 'react';
import AdminLayout from '@/components/AdminLayout';
import { adminUsers, roleManagement, type AdminRole, type AdminUser, type TeacherScope } from '@/lib/api';

export default function RolesPage() {
  const [roles, setRoles] = useState<AdminRole[]>([]);
  const [scopes, setScopes] = useState<TeacherScope[]>([]);
  const [users, setUsers] = useState<AdminUser[]>([]);
  const [teacherId, setTeacherId] = useState('');
  const [learnerId, setLearnerId] = useState('');
  const [password, setPassword] = useState('');
  const [message, setMessage] = useState('');
  const [loading, setLoading] = useState(true);

  async function load() {
    setLoading(true);
    setMessage('');
    try {
      const [nextRoles, nextScopes, teacherPage, learnerPage] = await Promise.all([
        roleManagement.roles(), roleManagement.scopes(),
        adminUsers.list({ role: 'teacher', perPage: 100 }),
        adminUsers.list({ role: 'learner', perPage: 100 }),
      ]);
      setRoles(nextRoles);
      setScopes(nextScopes);
      setUsers([...teacherPage.data, ...learnerPage.data]);
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : 'Could not load role controls.');
    } finally {
      setLoading(false);
    }
  }

  // eslint-disable-next-line react-hooks/set-state-in-effect
  useEffect(() => { void load(); }, []);

  async function assign(event: React.FormEvent) {
    event.preventDefault();
    try {
      const created = await roleManagement.assign(Number(teacherId), Number(learnerId), password);
      setScopes((items) => items.some((item) => item.id === created.id) ? items : [created, ...items]);
      setPassword('');
      setMessage('Teacher scope assigned.');
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : 'Could not assign teacher.');
    }
  }

  async function remove(id: number) {
    if (!password) {
      setMessage('Enter your password before removing a scope.');
      return;
    }
    try {
      await roleManagement.remove(id, password);
      setScopes((items) => items.filter((item) => item.id !== id));
      setPassword('');
      setMessage('Teacher scope removed.');
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : 'Could not remove teacher scope.');
    }
  }

  const teachers = users.filter((user) => user.role === 'teacher');
  const learners = users.filter((user) => user.role === 'learner');

  return <AdminLayout title="Roles & Teacher Scope" requiredRole="super_admin">
    <div className="space-y-7">
      <header><p className="text-xs font-black uppercase tracking-[.18em]" style={{ color: '#006590' }}>High-trust controls</p><h2 className="mt-2 text-3xl font-black">Roles & teacher scope</h2><p className="mt-1 font-medium" style={{ color: '#3e4850' }}>Roles are fixed capabilities. Teacher access is explicit per learner.</p></header>
      {message && <p role="status" className="rounded-xl p-4 font-bold" style={{ background: '#ffdf92', color: '#594400' }}>{message}</p>}
      {loading ? <p className="rounded-3xl bg-white p-10 text-center font-bold">Loading controls…</p> : <>
        <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">{roles.map((role) => <article key={role.id} className="rounded-3xl p-6" style={{ background: '#fff', border: '2px solid #bdc8d2', borderBottomWidth: 4 }}><span className="rounded-full px-3 py-1 text-xs font-black uppercase" style={{ background: role.slug === 'super_admin' ? '#ffdad6' : role.slug === 'teacher' ? '#ffdf92' : '#e8f4ff', color: '#004c6e' }}>{role.slug.replace('_', ' ')}</span><p className="mt-5 text-4xl font-black" style={{ color: '#006590' }}>{role.users_count}</p><p className="text-sm font-bold">{role.name}</p></article>)}</section>
        <div className="grid gap-6 xl:grid-cols-[.8fr_1.2fr]">
          <form onSubmit={assign} className="rounded-3xl bg-white p-6" style={{ border: '2px solid #bdc8d2', borderBottomWidth: 4 }}><h3 className="text-xl font-black">Assign teacher</h3><label className="mt-5 block font-bold">Teacher<select required value={teacherId} onChange={(event) => setTeacherId(event.target.value)} className="mt-2 w-full rounded-xl px-4 py-3" style={{ border: '2px solid #bdc8d2' }}><option value="">Choose teacher</option>{teachers.map((user) => <option key={user.id} value={user.id}>{user.name} · {user.email}</option>)}</select></label><label className="mt-4 block font-bold">Learner<select required value={learnerId} onChange={(event) => setLearnerId(event.target.value)} className="mt-2 w-full rounded-xl px-4 py-3" style={{ border: '2px solid #bdc8d2' }}><option value="">Choose learner</option>{learners.map((user) => <option key={user.id} value={user.id}>{user.name} · {user.email}</option>)}</select></label><label className="mt-4 block font-bold">Your password<input type="password" required value={password} onChange={(event) => setPassword(event.target.value)} className="mt-2 w-full rounded-xl px-4 py-3" style={{ border: '2px solid #bdc8d2' }} /></label><button className="mt-6 w-full rounded-xl px-5 py-3 font-black text-white" style={{ background: '#006590', borderBottom: '4px solid #004c6e' }}>Assign scope</button></form>
          <section className="rounded-3xl bg-white p-6" style={{ border: '2px solid #bdc8d2', borderBottomWidth: 4 }}><h3 className="text-xl font-black">Active teacher scopes</h3><div className="mt-4 space-y-3">{scopes.map((scope) => <article key={scope.id} className="flex flex-wrap items-center justify-between gap-3 rounded-2xl p-4" style={{ background: '#f5f3f3' }}><div><p className="font-black">{scope.teacher.name} → {scope.learner.name}</p><p className="text-sm" style={{ color: '#6e7881' }}>{scope.teacher.email} · {scope.learner.email}</p></div><button onClick={() => remove(scope.id)} className="rounded-xl px-4 py-2 font-bold" style={{ color: '#93000a', border: '2px solid #ffb4ab' }}>Remove</button></article>)}{scopes.length === 0 && <p className="rounded-xl p-6 text-center" style={{ background: '#f5f3f3' }}>No teacher scopes.</p>}</div></section>
        </div>
      </>}
    </div>
  </AdminLayout>;
}
