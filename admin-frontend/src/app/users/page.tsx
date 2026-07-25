'use client';

import { useEffect, useState, useCallback } from 'react';
import AdminLayout from '@/components/AdminLayout';
import { adminUsers } from '@/lib/api';
import Link from 'next/link';

interface User {
  id: number;
  email: string;
  name: string;
  avatarUrl: string;
  role: 'USER' | 'MODERATOR' | 'ADMIN';
  locked: boolean;
  createdAt: string;
  lastLoginAt: string;
}

interface UsersResponse {
  users: User[];
  totalElements: number;
  totalPages: number;
  page: number;
  size: number;
}

// Deterministic XP from user id
function fakeXP(id: number) { return (id * 3721 + 1234) % 99000 + 500; }
function fakeStreak(id: number) { return (id * 17 + 3) % 200; }
function fakeLevel(id: number) {
  const levels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
  return levels[id % levels.length];
}

const LEVEL_COLORS: Record<string, { text: string; bg: string; border: string }> = {
  A1: { text: '#843ab4', bg: '#f4d9ff', border: '#e4b5ff' },
  A2: { text: '#843ab4', bg: '#f4d9ff', border: '#e4b5ff' },
  B1: { text: '#006590', bg: '#c8e6ff', border: '#88ceff' },
  B2: { text: '#006590', bg: '#c8e6ff', border: '#88ceff' },
  C1: { text: '#004c6e', bg: '#e8f4ff', border: '#88ceff' },
  C2: { text: '#004c6e', bg: '#e8f4ff', border: '#88ceff' },
};

const ROLE_BADGE: Record<string, { bg: string; text: string; border: string; label: string }> = {
  ADMIN:     { bg: '#ffdad6', text: '#93000a', border: '#ffb4ab', label: 'Admin' },
  MODERATOR: { bg: '#ffdf92', text: '#594400', border: '#f4bf00', label: 'Moderator' },
  USER:      { bg: '#efeded', text: '#3e4850', border: '#bdc8d2', label: 'Free' },
};

function Avatar({ name, avatarUrl }: { name: string; avatarUrl: string }) {
  const [err, setErr] = useState(false);
  const initials = name ? name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase() : '?';
  if (avatarUrl && !err) {
    return (
      <img src={avatarUrl} alt={name} referrerPolicy="no-referrer" onError={() => setErr(true)}
        className="w-12 h-12 rounded-full object-cover"
        style={{ border: '2px solid #bdc8d2' }} />
    );
  }
  return (
    <div className="w-12 h-12 rounded-full flex items-center justify-center text-sm font-extrabold"
      style={{ backgroundColor: '#c8e6ff', color: '#004c6e', border: '2px solid #bdc8d2' }}>
      {initials}
    </div>
  );
}


export default function UsersPage() {
  const [data, setData] = useState<UsersResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [email, setEmail] = useState('');
  const [status, setStatus] = useState('');
  const [role, setRole] = useState('');
  const [page, setPage] = useState(0);
  const [actionLoading, setActionLoading] = useState<number | null>(null);
  const [resetResult, setResetResult] = useState<{ id: number; password: string } | null>(null);
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [openMenuId, setOpenMenuId] = useState<number | null>(null);
  const [editUser, setEditUser] = useState<User | null>(null);
  const [editRole, setEditRole] = useState<string>('USER');
  const [editLoading, setEditLoading] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const res = await adminUsers.list({ email: email || undefined, status: status || undefined, role: role || undefined, page, size: 20 }) as UsersResponse;
      setData(res);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load users');
      setData(null);
    } finally {
      setLoading(false);
    }
  }, [email, status, role, page]);

  useEffect(() => { load(); }, [load]);

  // Close menu on outside click
  useEffect(() => {
    const handler = () => setOpenMenuId(null);
    document.addEventListener('click', handler);
    return () => document.removeEventListener('click', handler);
  }, []);

  const users = data?.users ?? [];
  const allSelected = users.length > 0 && users.every(u => selected.has(u.id));

  const toggleAll = () => {
    if (allSelected) setSelected(new Set());
    else setSelected(new Set(users.map(u => u.id)));
  };

  const toggleOne = (id: number) => {
    setSelected(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const handleLockToggle = async (user: User) => {
    setActionLoading(user.id);
    try {
      if (user.locked) await adminUsers.unlock(user.id);
      else await adminUsers.lock(user.id);
      await load();
    } finally {
      setActionLoading(null);
      setOpenMenuId(null);
    }
  };

  const openEditModal = (user: User) => {
    setEditUser(user);
    setEditRole(user.role);
    setOpenMenuId(null);
  };

  const handleSaveEdit = async () => {
    if (!editUser) return;
    setEditLoading(true);
    try {
      await adminUsers.assignRole(editUser.id, editRole);
      await load();
      setEditUser(null);
    } finally {
      setEditLoading(false);
    }
  };

  const handleResetPassword = async (userId: number) => {
    setActionLoading(userId);
    try {
      const res = await adminUsers.resetPassword(userId) as { temporaryPassword: string };
      setResetResult({ id: userId, password: res.temporaryPassword });
    } finally {
      setActionLoading(null);
      setOpenMenuId(null);
    }
  };

  const activeCount = users.filter(u => !u.locked).length;

  return (
    <AdminLayout title="User Management">
      <div className="space-y-6">
        {/* Page header + stat cards */}
        <div className="flex flex-col lg:flex-row lg:items-end justify-between gap-6">
          <div>
            <h2 className="text-3xl font-black" style={{ color: '#1b1c1c', letterSpacing: '-0.02em' }}>User Management</h2>
            <p className="font-medium mt-1" style={{ color: '#3e4850' }}>Manage and monitor learners across the platform.</p>
          </div>
          <div className="flex gap-4">
            <div className="px-8 py-5 rounded-2xl flex items-center gap-5"
              style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
              <div className="w-14 h-14 rounded-full flex items-center justify-center"
                style={{ backgroundColor: '#c8e6ff', color: '#006590' }}>
                <span className="material-symbols-outlined text-3xl">group</span>
              </div>
              <div>
                <p className="text-[11px] font-bold uppercase tracking-widest" style={{ color: '#3e4850' }}>Total Learners</p>
                <p className="text-3xl font-black" style={{ color: '#006590', lineHeight: 1.1 }}>
                  {loading ? '…' : (data?.totalElements ?? 0).toLocaleString()}
                </p>
              </div>
            </div>
            <div className="px-8 py-5 rounded-2xl flex items-center gap-5"
              style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
              <div className="w-14 h-14 rounded-full flex items-center justify-center"
                style={{ backgroundColor: '#ffdf92', color: '#594400' }}>
                <span className="material-symbols-outlined text-3xl">workspace_premium</span>
              </div>
              <div>
                <p className="text-[11px] font-bold uppercase tracking-widest" style={{ color: '#3e4850' }}>Active Users</p>
                <p className="text-3xl font-black" style={{ color: '#755b00', lineHeight: 1.1 }}>
                  {loading ? '…' : activeCount.toLocaleString()}
                </p>
              </div>
            </div>
          </div>
        </div>

        {/* Filters + bulk actions bar */}
        <div className="rounded-2xl p-5 flex flex-wrap items-center justify-between gap-4"
          style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
          <div className="flex items-center gap-3 flex-wrap">
            {/* Search */}
            <div className="relative">
              <span className="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-base" style={{ color: '#6e7881' }}>search</span>
              <input value={email} onChange={e => { setEmail(e.target.value); setPage(0); }}
                placeholder="Search by name or email..."
                className="pl-10 pr-4 py-2.5 rounded-xl text-sm font-medium outline-none w-64"
                style={{ border: '2px solid #bdc8d2', backgroundColor: '#f5f3f3', color: '#1b1c1c' }}
                onFocus={e => (e.target.style.borderColor = '#006590')}
                onBlur={e => (e.target.style.borderColor = '#bdc8d2')} />
            </div>

            {/* Level filter (mapped to role for now) */}
            <div className="flex items-center gap-2 px-4 py-2.5 rounded-xl"
              style={{ border: '2px solid #bdc8d2', backgroundColor: '#f5f3f3' }}>
              <span className="text-xs font-bold uppercase tracking-wider" style={{ color: '#3e4850' }}>Role:</span>
              <select value={role} onChange={e => { setRole(e.target.value); setPage(0); }}
                className="bg-transparent border-none text-sm font-bold outline-none cursor-pointer pr-6 appearance-none"
                style={{ color: '#1b1c1c' }}>
                <option value="">All</option>
                <option value="USER">User</option>
                <option value="MODERATOR">Moderator</option>
                <option value="ADMIN">Admin</option>
              </select>
            </div>

            {/* Status filter */}
            <div className="flex items-center gap-2 px-4 py-2.5 rounded-xl"
              style={{ border: '2px solid #bdc8d2', backgroundColor: '#f5f3f3' }}>
              <span className="text-xs font-bold uppercase tracking-wider" style={{ color: '#3e4850' }}>Status:</span>
              <select value={status} onChange={e => { setStatus(e.target.value); setPage(0); }}
                className="bg-transparent border-none text-sm font-bold outline-none cursor-pointer pr-6 appearance-none"
                style={{ color: '#1b1c1c' }}>
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="locked">Locked</option>
              </select>
            </div>
          </div>

          {/* Bulk actions */}
          {selected.size > 0 && (
            <div className="flex items-center gap-3">
              <span className="text-sm font-bold px-2" style={{ color: '#3e4850' }}>{selected.size} selected</span>
              <span className="text-xs italic px-3 py-2 rounded-xl" style={{ backgroundColor: '#efeded', color: '#6e7881' }}>
                Bulk actions coming soon
              </span>
            </div>
          )}
        </div>

        {/* Error banner */}
        {error && (
          <div className="rounded-2xl p-4 flex items-center gap-3"
            style={{ backgroundColor: '#ffdad6', border: '2px solid #ba1a1a' }}>
            <span className="material-symbols-outlined" style={{ color: '#ba1a1a' }}>error</span>
            <p className="text-sm font-bold" style={{ color: '#ba1a1a' }}>{error}</p>
            <button onClick={load} className="ml-auto text-xs font-bold px-3 py-1 rounded-lg"
              style={{ backgroundColor: '#ba1a1a', color: '#ffffff' }}>
              Retry
            </button>
          </div>
        )}

        {/* Reset password toast */}
        {resetResult && (
          <div className="rounded-2xl p-4 flex items-center justify-between"
            style={{ backgroundColor: '#fff3cd', border: '2px solid #755b00' }}>
            <div className="flex items-center gap-3">
              <span className="material-symbols-outlined" style={{ color: '#755b00' }}>key</span>
              <div>
                <p className="text-sm font-bold" style={{ color: '#755b00' }}>Temporary password for user #{resetResult.id}</p>
                <p className="text-lg font-extrabold font-mono mt-0.5" style={{ color: '#1b1c1c', letterSpacing: '0.1em' }}>{resetResult.password}</p>
              </div>
            </div>
            <button onClick={() => setResetResult(null)} className="p-1 rounded-lg hover:bg-black/10">
              <span className="material-symbols-outlined text-base" style={{ color: '#755b00' }}>close</span>
            </button>
          </div>
        )}

        {/* Data Table */}
        <div className="rounded-2xl"
          style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
          {loading ? (
            <div className="p-16 text-center">
              <span className="material-symbols-outlined text-5xl block mb-3 animate-spin" style={{ color: '#bdc8d2' }}>progress_activity</span>
              <p className="text-sm font-bold" style={{ color: '#3e4850' }}>Loading users...</p>
            </div>
          ) : users.length === 0 ? (
            <div className="p-16 text-center">
              <span className="material-symbols-outlined text-5xl block mb-3" style={{ color: '#bdc8d2' }}>group_off</span>
              <p className="text-sm font-bold" style={{ color: '#3e4850' }}>No users found</p>
            </div>
          ) : (
            <div className="overflow-x-auto" style={{ borderRadius: '14px 14px 0 0', overflowY: 'hidden' }}>
              <table className="w-full text-left border-collapse min-w-[900px]">
                <thead>
                  <tr style={{ backgroundColor: '#f5f3f3', borderBottom: '2px solid #bdc8d2' }}>
                    <th className="p-5 w-14 text-center">
                      <input type="checkbox" checked={allSelected} onChange={toggleAll}
                        className="rounded h-5 w-5 cursor-pointer"
                        style={{ accentColor: '#006590' }} />
                    </th>
                    {['Learner', 'Status', 'Level', 'Experience (XP)', 'Streak', 'Actions'].map(h => (
                      <th key={h} className="p-5 text-[11px] font-bold uppercase tracking-[0.2em]"
                        style={{ color: '#3e4850' }}>{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody style={{ borderTop: 'none' }}>
                  {users.map(u => {
                    const xp = fakeXP(u.id);
                    const streak = fakeStreak(u.id);
                    const level = fakeLevel(u.id);
                    const lvlColors = LEVEL_COLORS[level];
                    const roleBadge = ROLE_BADGE[u.role] ?? ROLE_BADGE.USER;
                    const isChecked = selected.has(u.id);
                    return (
                      <tr key={u.id}
                        style={{ borderTop: '2px solid #f5f3f3' }}
                        onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#e8f4ff18')}
                        onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
                        <td className="p-5 text-center">
                          <input type="checkbox" checked={isChecked} onChange={() => toggleOne(u.id)}
                            className="rounded h-5 w-5 cursor-pointer"
                            style={{ accentColor: '#006590' }} />
                        </td>

                        {/* Learner */}
                        <td className="p-5">
                          <div className="flex items-center gap-4">
                            <div className="relative shrink-0">
                              <Avatar name={u.name} avatarUrl={u.avatarUrl} />
                              <span className="absolute bottom-0 right-0 w-3.5 h-3.5 rounded-full border-2 border-white"
                                style={{ backgroundColor: u.locked ? '#ba1a1a' : '#22c55e' }} />
                            </div>
                            <div>
                              <p className="font-bold text-sm" style={{ color: '#1b1c1c' }}>{u.name || '—'}</p>
                              <p className="text-sm font-medium" style={{ color: '#3e4850' }}>{u.email}</p>
                            </div>
                          </div>
                        </td>

                        {/* Status badge */}
                        <td className="p-5">
                          <span className="inline-flex items-center px-3 py-1.5 rounded-full text-[10px] font-black uppercase"
                            style={{ backgroundColor: roleBadge.bg, color: roleBadge.text, border: `2px solid ${roleBadge.border}` }}>
                            {roleBadge.label}
                          </span>
                        </td>

                        {/* Level */}
                        <td className="p-5">
                          <span className="font-black px-3 py-1.5 rounded-xl text-sm"
                            style={{ color: lvlColors.text, backgroundColor: lvlColors.bg, border: `2px solid ${lvlColors.border}` }}>
                            {level}
                          </span>
                        </td>

                        {/* XP with progress bar */}
                        <td className="p-5">
                          <div className="flex items-center gap-3">
                            <div className="w-28 h-2.5 rounded-full overflow-hidden"
                              style={{ backgroundColor: '#efeded', border: '2px solid #e3e2e2' }}>
                              <div className="h-full rounded-full" style={{ width: `${Math.min(100, Math.round(xp / 1000))}%`, backgroundColor: '#006590' }} />
                            </div>
                            <span className="font-black text-sm tabular-nums" style={{ color: '#1b1c1c' }}>
                              {xp.toLocaleString()}
                            </span>
                          </div>
                        </td>

                        {/* Streak */}
                        <td className="p-5 text-center">
                          <div className="flex items-center justify-center gap-1.5"
                            style={{ color: streak > 0 ? '#f97316' : '#bdc8d2' }}>
                            <span className="material-symbols-outlined text-xl"
                              style={{ fontVariationSettings: streak > 0 ? "'FILL' 1" : "'FILL' 0" }}>
                              local_fire_department
                            </span>
                            <span className="font-black text-lg" style={{ color: '#1b1c1c' }}>{streak}</span>
                          </div>
                        </td>

                        {/* Actions ⋮ menu */}
                        <td className="p-5 text-right relative">
                          <button onClick={e => { e.stopPropagation(); setOpenMenuId(openMenuId === u.id ? null : u.id); }}
                            className="p-2 rounded-xl transition-all"
                            style={{ color: '#3e4850' }}
                            onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#c8e6ff')}
                            onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
                            <span className="material-symbols-outlined">more_vert</span>
                          </button>

                          {openMenuId === u.id && (
                            <div className="absolute right-5 top-14 z-20 rounded-2xl shadow-xl min-w-[190px]"
                              style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}
                              onClick={e => e.stopPropagation()}>
                              <div className="rounded-[14px] overflow-hidden">
                              <button onClick={() => openEditModal(u)}
                                className="w-full flex items-center gap-3 px-4 py-3 text-sm font-bold transition-colors"
                                style={{ color: '#1b1c1c' }}
                                onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#f5f3f3')}
                                onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
                                <span className="material-symbols-outlined text-base" style={{ color: '#843ab4' }}>edit</span>
                                Edit User
                              </button>
                              <div style={{ borderTop: '1px solid #efeded' }} />
                              <Link href={`/users/${u.id}`}
                                className="flex items-center gap-3 px-4 py-3 text-sm font-bold transition-colors"
                                style={{ color: '#1b1c1c' }}
                                onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#f5f3f3')}
                                onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
                                <span className="material-symbols-outlined text-base" style={{ color: '#006590' }}>person</span>
                                View Profile
                              </Link>
                              <button onClick={() => handleLockToggle(u)}
                                disabled={actionLoading === u.id}
                                className="w-full flex items-center gap-3 px-4 py-3 text-sm font-bold transition-colors"
                                style={{ color: '#1b1c1c' }}
                                onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#f5f3f3')}
                                onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
                                <span className="material-symbols-outlined text-base"
                                  style={{ color: u.locked ? '#1a6b1a' : '#ba1a1a' }}>
                                  {actionLoading === u.id ? 'progress_activity' : (u.locked ? 'lock_open' : 'lock')}
                                </span>
                                {u.locked ? 'Unlock Account' : 'Lock Account'}
                              </button>
                              <div style={{ borderTop: '1px solid #efeded' }} />
                              <button onClick={() => handleResetPassword(u.id)}
                                disabled={actionLoading === u.id}
                                className="w-full flex items-center gap-3 px-4 py-3 text-sm font-bold transition-colors"
                                style={{ color: '#755b00' }}
                                onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#fff3cd')}
                                onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
                                <span className="material-symbols-outlined text-base">key</span>
                                Reset Password
                              </button>
                              </div>
                            </div>
                          )}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}

          {/* Pagination */}
          {data && data.totalPages > 1 && (
            <div className="p-6 flex items-center justify-between"
              style={{ borderTop: '2px solid #bdc8d2', backgroundColor: '#f5f3f3' }}>
              <span className="text-sm font-bold uppercase tracking-wider" style={{ color: '#3e4850' }}>
                Showing {page * 20 + 1}–{Math.min((page + 1) * 20, data.totalElements)} of {data.totalElements.toLocaleString()} learners
              </span>
              <div className="flex items-center gap-2">
                <button onClick={() => setPage(p => Math.max(0, p - 1))} disabled={data.page === 0}
                  className="w-10 h-10 flex items-center justify-center rounded-xl disabled:opacity-40"
                  style={{ border: '2px solid #bdc8d2', color: '#3e4850' }}
                  onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#efeded')}
                  onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
                  <span className="material-symbols-outlined">chevron_left</span>
                </button>
                {Array.from({ length: Math.min(5, data.totalPages) }, (_, i) => i).map(i => (
                  <button key={i} onClick={() => setPage(i)}
                    className="w-10 h-10 flex items-center justify-center rounded-xl font-black text-sm"
                    style={page === i
                      ? { backgroundColor: '#1cb0f6', color: '#004c6e', borderBottom: '4px solid #006590' }
                      : { border: '2px solid #bdc8d2', color: '#3e4850' }}
                    onMouseEnter={e => { if (page !== i) e.currentTarget.style.backgroundColor = '#efeded'; }}
                    onMouseLeave={e => { if (page !== i) e.currentTarget.style.backgroundColor = ''; }}>
                    {i + 1}
                  </button>
                ))}
                {data.totalPages > 5 && <span className="px-2 font-black" style={{ color: '#6e7881' }}>…</span>}
                <button onClick={() => setPage(p => Math.min(data.totalPages - 1, p + 1))} disabled={data.page >= data.totalPages - 1}
                  className="w-10 h-10 flex items-center justify-center rounded-xl disabled:opacity-40"
                  style={{ border: '2px solid #bdc8d2', color: '#3e4850' }}
                  onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#efeded')}
                  onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
                  <span className="material-symbols-outlined">chevron_right</span>
                </button>
              </div>
            </div>
          )}
        </div>

        {/* Growth analysis bento */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2 rounded-3xl p-8 relative overflow-hidden"
            style={{ backgroundColor: '#e8f4ff', border: '2px solid #88ceff', borderBottom: '4px solid #88ceff' }}>
            <div className="relative z-10 max-w-2xl">
              <h3 className="text-xl font-bold mb-3" style={{ color: '#006590' }}>Growth Analysis</h3>
              <p className="font-medium mb-8 leading-relaxed" style={{ color: '#3e4850' }}>
                User retention is up by <span className="font-black" style={{ color: '#006590' }}>14%</span> this month.
                Learners reaching B2 level show 3× more engagement than beginners.
              </p>
              <div className="flex flex-wrap gap-4">
                <Link href="/analytics"
                  className="btn-tactile px-8 py-3 rounded-xl font-bold text-sm uppercase tracking-wide"
                  style={{ backgroundColor: '#1cb0f6', color: '#004c6e', borderBottom: '4px solid #006590' }}>
                  View Full Report
                </Link>
                <Link href="/reports"
                  className="btn-tactile px-8 py-3 rounded-xl font-bold text-sm uppercase tracking-wide"
                  style={{ backgroundColor: '#ffffff', color: '#006590', border: '2px solid #88ceff', borderBottom: '4px solid #88ceff' }}>
                  Export Raw Data
                </Link>
              </div>
            </div>
            <div className="absolute right-0 bottom-0 w-80 h-80 rounded-full blur-3xl -mr-20 -mb-20"
              style={{ backgroundColor: '#006590', opacity: 0.05 }} />
            <div className="absolute right-12 top-1/2 -translate-y-1/2 opacity-10 hidden lg:block">
              <span className="material-symbols-outlined select-none"
                style={{ fontSize: '160px', color: '#006590', fontVariationSettings: "'wght' 200" }}>trending_up</span>
            </div>
          </div>

          <div className="rounded-3xl p-8 flex flex-col justify-between"
            style={{ backgroundColor: '#f4d9ff', border: '2px solid #e4b5ff', borderBottom: '4px solid #d087ff' }}>
            <div>
              <h3 className="text-xl font-bold mb-1" style={{ color: '#843ab4' }}>Support Queue</h3>
              <p className="text-sm font-bold uppercase tracking-wider mb-6" style={{ color: '#6a1c9a' }}>8 pending inquiries</p>
              <div className="flex items-center gap-3 mb-6">
                <div className="flex -space-x-3">
                  {['EL', 'MC', 'JV'].map(i => (
                    <div key={i} className="w-10 h-10 rounded-full border-2 border-white flex items-center justify-center text-xs font-extrabold"
                      style={{ backgroundColor: '#c8e6ff', color: '#004c6e' }}>{i}</div>
                  ))}
                  <div className="w-10 h-10 rounded-full border-2 border-white flex items-center justify-center text-[10px] font-black"
                    style={{ backgroundColor: '#f4d9ff', color: '#843ab4' }}>+5</div>
                </div>
                <span className="text-xs font-medium" style={{ color: '#6a1c9a' }}>Active discussions</span>
              </div>
            </div>
            <button className="btn-tactile w-full flex items-center justify-center gap-3 py-4 rounded-xl font-bold text-sm uppercase tracking-wide"
              style={{ backgroundColor: '#843ab4', color: '#ffffff', borderBottom: '4px solid #5c00a3' }}>
              <span className="material-symbols-outlined">forum</span>
              Open Tickets
            </button>
          </div>
        </div>
      </div>

      {/* Edit User Modal */}
      {editUser && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4"
          style={{ backgroundColor: 'rgba(0,0,0,0.4)' }}
          onClick={() => setEditUser(null)}>
          <div className="w-full max-w-md rounded-3xl p-8"
            style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '6px solid #bdc8d2' }}
            onClick={e => e.stopPropagation()}>

            {/* Header */}
            <div className="flex items-center justify-between mb-6">
              <h3 className="text-xl font-extrabold" style={{ color: '#1b1c1c' }}>Edit User</h3>
              <button onClick={() => setEditUser(null)}
                className="p-2 rounded-xl"
                style={{ color: '#6e7881' }}
                onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#f5f3f3')}
                onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
                <span className="material-symbols-outlined">close</span>
              </button>
            </div>

            {/* User Info (read-only) */}
            <div className="flex items-center gap-4 p-4 rounded-2xl mb-6"
              style={{ backgroundColor: '#f5f3f3', border: '2px solid #efeded' }}>
              <Avatar name={editUser.name} avatarUrl={editUser.avatarUrl} />
              <div>
                <p className="font-extrabold" style={{ color: '#1b1c1c' }}>{editUser.name || '—'}</p>
                <p className="text-sm font-medium" style={{ color: '#3e4850' }}>{editUser.email}</p>
              </div>
            </div>

            {/* Role selector */}
            <div className="mb-6">
              <label className="block text-xs font-bold uppercase tracking-widest mb-2" style={{ color: '#3e4850' }}>
                Role
              </label>
              <div className="flex gap-3">
                {(['USER', 'MODERATOR', 'ADMIN'] as const).map(r => {
                  const badge = ROLE_BADGE[r];
                  const isSelected = editRole === r;
                  return (
                    <button key={r} onClick={() => setEditRole(r)}
                      className="flex-1 py-3 rounded-2xl text-sm font-extrabold transition-all"
                      style={{
                        backgroundColor: isSelected ? badge.bg : '#f5f3f3',
                        color: isSelected ? badge.text : '#6e7881',
                        border: isSelected ? `2px solid ${badge.border}` : '2px solid #efeded',
                        borderBottom: isSelected ? `4px solid ${badge.border}` : '2px solid #efeded',
                      }}>
                      {badge.label}
                    </button>
                  );
                })}
              </div>
            </div>

            {/* Actions */}
            <div className="flex gap-3">
              <button onClick={() => setEditUser(null)}
                className="flex-1 py-3 rounded-2xl font-bold text-sm"
                style={{ border: '2px solid #bdc8d2', color: '#3e4850', borderBottom: '4px solid #bdc8d2' }}
                onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#f5f3f3')}
                onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
                Cancel
              </button>
              <button onClick={handleSaveEdit} disabled={editLoading}
                className="flex-1 py-3 rounded-2xl font-bold text-sm"
                style={{
                  backgroundColor: editLoading ? '#bdc8d2' : '#006590',
                  color: '#ffffff',
                  borderBottom: editLoading ? '4px solid #a0adb5' : '4px solid #004c6e',
                  cursor: editLoading ? 'not-allowed' : 'pointer',
                }}>
                {editLoading ? 'Saving…' : 'Save Changes'}
              </button>
            </div>
          </div>
        </div>
      )}
    </AdminLayout>
  );
}
