'use client';

import { useCallback, useEffect, useState } from 'react';
import AdminLayout from '@/components/AdminLayout';
import { adminUsers, auth, type AdminUser, type PageMeta } from '@/lib/api';

const roles: AdminUser['role'][] = ['learner', 'teacher', 'admin', 'super_admin'];

export default function UsersPage() {
  const [users, setUsers] = useState<AdminUser[]>([]);
  const [meta, setMeta] = useState<PageMeta>();
  const [search, setSearch] = useState('');
  const [role, setRole] = useState('');
  const [page, setPage] = useState(1);
  const [state, setState] = useState<'loading' | 'ready' | 'error'>('loading');
  const [message, setMessage] = useState('');
  const [editing, setEditing] = useState<AdminUser>();
  const [nextRole, setNextRole] = useState<AdminUser['role']>('learner');
  const [password, setPassword] = useState('');
  const [canChangeRoles, setCanChangeRoles] = useState(false);

  const load = useCallback(async () => {
    setState('loading');
    setMessage('');
    try {
      const [response, currentUser] = await Promise.all([
        adminUsers.list({ search: search || undefined, role: role || undefined, page, perPage: 20 }),
        auth.me(),
      ]);
      setUsers(response.data);
      setMeta(response.meta);
      setCanChangeRoles(currentUser.role === 'super_admin');
      setState('ready');
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : 'Could not load users.');
      setState('error');
    }
  }, [page, role, search]);

  // eslint-disable-next-line react-hooks/set-state-in-effect
  useEffect(() => { void load(); }, [load]);

  async function saveRole(event: React.FormEvent) {
    event.preventDefault();
    if (!editing) return;
    setMessage('');
    try {
      const updated = await adminUsers.assignRole(editing.id, nextRole, password);
      setUsers((items) => items.map((item) => item.id === updated.id ? updated : item));
      setEditing(undefined);
      setPassword('');
      setMessage('Role updated.');
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : 'Could not update role.');
    }
  }

  return <AdminLayout title="Users">
    <div className="space-y-6">
      <header className="flex flex-col justify-between gap-4 lg:flex-row lg:items-end"><div><p className="text-xs font-black uppercase tracking-[.18em]" style={{ color: '#006590' }}>Identity boundary</p><h2 className="mt-2 text-3xl font-black">User management</h2><p className="mt-1 font-medium" style={{ color: '#3e4850' }}>Admins can inspect users. Only super admins can change privileged roles.</p></div><div className="rounded-2xl px-6 py-4 text-center" style={{ background: '#e8f4ff', border: '2px solid #88ceff' }}><p className="text-3xl font-black" style={{ color: '#006590' }}>{meta?.total ?? 0}</p><p className="text-xs font-bold uppercase">accounts</p></div></header>
      {message && <p role="status" className="rounded-xl p-4 font-bold" style={{ background: '#ffdf92', color: '#594400' }}>{message}</p>}
      <section className="rounded-3xl p-5" style={{ background: '#fff', border: '2px solid #bdc8d2', borderBottomWidth: 4 }}>
        <div className="flex flex-wrap gap-3"><label className="min-w-64 flex-1"><span className="sr-only">Search users</span><input value={search} onChange={(event) => { setSearch(event.target.value); setPage(1); }} placeholder="Search name or email" className="w-full rounded-xl px-4 py-3 outline-none" style={{ border: '2px solid #bdc8d2' }} /></label><label><span className="sr-only">Filter role</span><select value={role} onChange={(event) => { setRole(event.target.value); setPage(1); }} className="rounded-xl px-4 py-3 font-bold" style={{ border: '2px solid #bdc8d2' }}><option value="">All roles</option>{roles.map((item) => <option key={item} value={item}>{item.replace('_', ' ')}</option>)}</select></label></div>
        {state === 'loading' ? <p className="p-10 text-center font-bold">Loading users…</p> : state === 'error' ? <div className="p-10 text-center"><p>{message}</p><button onClick={load} className="mt-4 rounded-xl px-5 py-3 font-bold text-white" style={{ background: '#006590' }}>Retry</button></div> : users.length === 0 ? <p className="p-10 text-center">No matching users.</p> : <div className="mt-5 overflow-x-auto"><table className="w-full text-left"><thead><tr>{['User', 'Role', 'Verified', 'Created', 'Action'].map((label) => <th key={label} className="px-4 py-3 text-xs uppercase tracking-wider">{label}</th>)}</tr></thead><tbody>{users.map((user) => <tr key={user.id} style={{ borderTop: '1px solid #efeded' }}><td className="px-4 py-4"><p className="font-bold">{user.name}</p><p className="text-sm" style={{ color: '#6e7881' }}>{user.email}</p></td><td className="px-4 py-4"><span className="rounded-full px-3 py-1 text-xs font-black uppercase" style={{ background: user.role === 'super_admin' ? '#ffdad6' : user.role === 'teacher' ? '#ffdf92' : '#e8f4ff', color: '#004c6e' }}>{user.role.replace('_', ' ')}</span></td><td className="px-4 py-4">{user.email_verified_at ? 'Yes' : 'No'}</td><td className="px-4 py-4">{user.created_at ? new Intl.DateTimeFormat('en', { dateStyle: 'medium' }).format(new Date(user.created_at)) : '—'}</td><td className="px-4 py-4">{canChangeRoles ? <button onClick={() => { setEditing(user); setNextRole(user.role); }} className="rounded-xl px-4 py-2 font-bold" style={{ color: '#006590', border: '2px solid #88ceff' }}>Change role</button> : <span className="text-sm" style={{ color: '#6e7881' }}>Read only</span>}</td></tr>)}</tbody></table></div>}
        {meta && meta.last_page > 1 && <div className="mt-5 flex items-center justify-end gap-3"><button disabled={page <= 1} onClick={() => setPage((value) => value - 1)} className="rounded-xl px-4 py-2 font-bold disabled:opacity-40" style={{ border: '2px solid #bdc8d2' }}>Previous</button><span className="font-bold">{page}/{meta.last_page}</span><button disabled={page >= meta.last_page} onClick={() => setPage((value) => value + 1)} className="rounded-xl px-4 py-2 font-bold disabled:opacity-40" style={{ border: '2px solid #bdc8d2' }}>Next</button></div>}
      </section>
    </div>
    {editing && <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4"><form onSubmit={saveRole} className="w-full max-w-md rounded-3xl bg-white p-7 shadow-xl"><h3 className="text-2xl font-black">Change {editing.name}&apos;s role</h3><p className="mt-2 text-sm" style={{ color: '#3e4850' }}>Privileged role changes require your recent password.</p><label className="mt-5 block font-bold">Role<select value={nextRole} onChange={(event) => setNextRole(event.target.value as AdminUser['role'])} className="mt-2 w-full rounded-xl px-4 py-3" style={{ border: '2px solid #bdc8d2' }}>{roles.map((item) => <option key={item} value={item}>{item.replace('_', ' ')}</option>)}</select></label><label className="mt-4 block font-bold">Your password<input type="password" required value={password} onChange={(event) => setPassword(event.target.value)} className="mt-2 w-full rounded-xl px-4 py-3" style={{ border: '2px solid #bdc8d2' }} /></label><div className="mt-6 flex justify-end gap-3"><button type="button" onClick={() => { setEditing(undefined); setPassword(''); }} className="rounded-xl px-5 py-3 font-bold">Cancel</button><button className="rounded-xl px-5 py-3 font-bold text-white" style={{ background: '#006590' }}>Save role</button></div></form></div>}
  </AdminLayout>;
}
