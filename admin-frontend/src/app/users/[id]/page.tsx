'use client';

import { useEffect, useState, useCallback } from 'react';
import { useParams, useRouter } from 'next/navigation';
import AdminLayout from '@/components/AdminLayout';
import { adminUsers } from '@/lib/api';
import Link from 'next/link';

interface UserInfo {
  id: number;
  email: string;
  name: string;
  avatarUrl: string;
  role: 'USER' | 'MODERATOR' | 'ADMIN';
  locked: boolean;
  createdAt: string;
  lastLoginAt: string;
}

interface Stats {
  totalWords: number;
  mastered: number;
  learning: number;
  accuracy: number;
  correctAnswers: number;
  incorrectAnswers: number;
}

interface StreakInfo {
  currentStreak: number;
  longestStreak: number;
  totalDaysStudied: number;
  lastStudyDate: string;
}

interface Deck {
  id: number;
  name: string;
  description: string;
  isPublic: boolean;
  createdAt: string;
}

interface ReviewHistoryItem {
  id: number;
  word: string;
  rating: number;
  correct: boolean;
  reviewedAt: string;
}

interface UserDetail {
  user: UserInfo;
  stats: Stats;
  streak: StreakInfo;
  decks: Deck[];
}

const RATING_LABELS: Record<number, { label: string; color: string }> = {
  1: { label: 'Again', color: '#ba1a1a' },
  2: { label: 'Hard', color: '#755b00' },
  3: { label: 'Good', color: '#006590' },
  4: { label: 'Easy', color: '#1a6b1a' },
};

const ROLE_COLORS: Record<string, { bg: string; text: string }> = {
  ADMIN: { bg: '#ffe8e8', text: '#ba1a1a' },
  MODERATOR: { bg: '#fff3cd', text: '#755b00' },
  USER: { bg: '#e8f4ff', text: '#004c6e' },
};

function StatBox({ icon, label, value, color = '#006590' }: { icon: string; label: string; value: string | number; color?: string }) {
  return (
    <div className="p-5 rounded-2xl flex items-center gap-4"
      style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
      <div className="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
        style={{ backgroundColor: color + '20' }}>
        <span className="material-symbols-outlined text-2xl" style={{ color }}>{icon}</span>
      </div>
      <div>
        <p className="text-xs font-bold uppercase tracking-widest" style={{ color: '#3e4850' }}>{label}</p>
        <p className="text-2xl font-extrabold mt-0.5" style={{ color: '#1b1c1c' }}>{value}</p>
      </div>
    </div>
  );
}

function formatDate(iso: string) {
  if (!iso) return '—';
  try {
    return new Date(iso).toLocaleString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  } catch { return iso.slice(0, 16).replace('T', ' '); }
}

export default function UserDetailPage() {
  const params = useParams();
  const router = useRouter();
  const id = Number(params.id);

  const [detail, setDetail] = useState<UserDetail | null>(null);
  const [history, setHistory] = useState<ReviewHistoryItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [selectedRole, setSelectedRole] = useState('');
  const [resetPassword, setResetPassword] = useState<string | null>(null);
  const [toast, setToast] = useState<{ type: 'success' | 'error'; msg: string } | null>(null);
  const [avatarError, setAvatarError] = useState(false);

  const showToast = (type: 'success' | 'error', msg: string) => {
    setToast({ type, msg });
    setTimeout(() => setToast(null), 4000);
  };

  const load = async () => {
    setLoading(true);
    try {
      const [d, h] = await Promise.all([
        adminUsers.get(id) as Promise<UserDetail>,
        adminUsers.history(id, 20) as Promise<ReviewHistoryItem[]>,
      ]);
      setDetail(d);
      setHistory(h);
      setSelectedRole(d.user.role);
    } catch {
      showToast('error', 'Failed to load user data');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { if (id) load(); }, [id]);

  const handleLockToggle = async () => {
    if (!detail) return;
    setActionLoading(true);
    try {
      if (detail.user.locked) {
        await adminUsers.unlock(id);
        showToast('success', 'Account unlocked successfully');
      } else {
        await adminUsers.lock(id);
        showToast('success', 'Account locked successfully');
      }
      await load();
    } catch {
      showToast('error', 'Action failed');
    } finally {
      setActionLoading(false);
    }
  };

  const handleResetPassword = async () => {
    setActionLoading(true);
    try {
      const res = await adminUsers.resetPassword(id) as { temporaryPassword: string };
      setResetPassword(res.temporaryPassword);
    } catch {
      showToast('error', 'Password reset failed');
    } finally {
      setActionLoading(false);
    }
  };

  const handleAssignRole = async () => {
    if (!selectedRole || selectedRole === detail?.user.role) return;
    setActionLoading(true);
    try {
      await adminUsers.assignRole(id, selectedRole);
      showToast('success', `Role updated to ${selectedRole}`);
      await load();
    } catch {
      showToast('error', 'Role update failed');
    } finally {
      setActionLoading(false);
    }
  };

  if (loading) {
    return (
      <AdminLayout title="User Detail">
        <div className="flex items-center justify-center h-64">
          <span className="material-symbols-outlined text-6xl animate-spin" style={{ color: '#bdc8d2' }}>progress_activity</span>
        </div>
      </AdminLayout>
    );
  }

  if (!detail) {
    return (
      <AdminLayout title="User Detail">
        <div className="text-center py-20">
          <span className="material-symbols-outlined text-6xl" style={{ color: '#bdc8d2' }}>person_off</span>
          <p className="mt-3 font-bold" style={{ color: '#3e4850' }}>User not found</p>
          <Link href="/users" className="mt-4 inline-block text-sm font-bold" style={{ color: '#006590' }}>← Back to Users</Link>
        </div>
      </AdminLayout>
    );
  }

  const { user, stats, streak, decks } = detail;
  const roleColor = ROLE_COLORS[user.role] ?? { bg: '#efeded', text: '#3e4850' };

  return (
    <AdminLayout title={`User: ${user.name || user.email}`}>
      <div className="space-y-6 pb-12">
        {/* Back */}
        <Link href="/users" className="inline-flex items-center gap-2 text-sm font-bold transition-colors"
          style={{ color: '#006590' }}>
          <span className="material-symbols-outlined text-base">arrow_back</span>
          Back to Users
        </Link>

        {/* Toast */}
        {toast && (
          <div className="rounded-2xl p-4 flex items-center gap-3"
            style={toast.type === 'success'
              ? { backgroundColor: '#d4f5d4', border: '2px solid #1a6b1a' }
              : { backgroundColor: '#ffe8e8', border: '2px solid #ba1a1a' }}>
            <span className="material-symbols-outlined text-base"
              style={{ color: toast.type === 'success' ? '#1a6b1a' : '#ba1a1a' }}>
              {toast.type === 'success' ? 'check_circle' : 'error'}
            </span>
            <p className="text-sm font-bold"
              style={{ color: toast.type === 'success' ? '#1a6b1a' : '#ba1a1a' }}>
              {toast.msg}
            </p>
          </div>
        )}

        {/* Reset password result */}
        {resetPassword && (
          <div className="rounded-2xl p-5 flex items-start justify-between"
            style={{ backgroundColor: '#fff3cd', border: '2px solid #755b00' }}>
            <div>
              <p className="text-sm font-bold" style={{ color: '#755b00' }}>Temporary Password Generated</p>
              <p className="text-2xl font-extrabold font-mono mt-1" style={{ color: '#1b1c1c', letterSpacing: '0.1em' }}>{resetPassword}</p>
              <p className="text-xs mt-1" style={{ color: '#755b00' }}>Share this with the user securely. It will not be shown again.</p>
            </div>
            <button onClick={() => setResetPassword(null)} className="p-1 rounded-lg hover:bg-black/10">
              <span className="material-symbols-outlined text-base" style={{ color: '#755b00' }}>close</span>
            </button>
          </div>
        )}

        {/* Profile header */}
        <div className="rounded-2xl p-6 flex flex-col sm:flex-row items-start sm:items-center gap-6"
          style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
          {/* Avatar */}
          {user.avatarUrl && !avatarError ? (
            <img
              src={user.avatarUrl}
              alt={user.name}
              referrerPolicy="no-referrer"
              onError={() => setAvatarError(true)}
              className="w-20 h-20 rounded-2xl object-cover flex-shrink-0"
              style={{ border: '3px solid #bdc8d2' }}
            />
          ) : (
            <div className="w-20 h-20 rounded-2xl flex items-center justify-center text-2xl font-extrabold flex-shrink-0"
              style={{ backgroundColor: '#c8e6ff', color: '#004c6e', border: '3px solid #bdc8d2' }}>
              {user.name ? user.name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase() : '?'}
            </div>
          )}

          <div className="flex-1 space-y-2">
            <div className="flex items-center gap-3 flex-wrap">
              <h2 className="text-2xl font-extrabold" style={{ color: '#1b1c1c' }}>
                {user.name || '(no name)'}
              </h2>
              <span className="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide"
                style={{ backgroundColor: roleColor.bg, color: roleColor.text }}>
                {user.role}
              </span>
              <span className="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold"
                style={user.locked
                  ? { backgroundColor: '#ffe8e8', color: '#ba1a1a' }
                  : { backgroundColor: '#d4f5d4', color: '#1a6b1a' }}>
                <span className="w-1.5 h-1.5 rounded-full inline-block"
                  style={{ backgroundColor: user.locked ? '#ba1a1a' : '#1a6b1a' }} />
                {user.locked ? 'Locked' : 'Active'}
              </span>
            </div>
            <p className="text-sm font-medium" style={{ color: '#3e4850' }}>{user.email}</p>
            <div className="flex gap-6 flex-wrap text-xs font-bold" style={{ color: '#3e4850' }}>
              <span>Registered: {formatDate(user.createdAt)}</span>
              <span>Last login: {formatDate(user.lastLoginAt)}</span>
            </div>
          </div>

          {/* Quick actions */}
          <div className="flex gap-2 flex-wrap">
            <button
              onClick={handleLockToggle}
              disabled={actionLoading}
              className="btn-tactile px-4 py-2.5 rounded-xl font-bold text-sm uppercase tracking-wide flex items-center gap-2 disabled:opacity-50"
              style={user.locked
                ? { backgroundColor: '#d4f5d4', color: '#1a6b1a', border: '2px solid #1a6b1a', borderBottom: '4px solid #1a6b1a' }
                : { backgroundColor: '#ffe8e8', color: '#ba1a1a', border: '2px solid #ba1a1a', borderBottom: '4px solid #ba1a1a' }}>
              <span className="material-symbols-outlined text-base">{user.locked ? 'lock_open' : 'lock'}</span>
              {user.locked ? 'Unlock' : 'Lock'}
            </button>
            <button
              onClick={handleResetPassword}
              disabled={actionLoading}
              className="btn-tactile px-4 py-2.5 rounded-xl font-bold text-sm uppercase tracking-wide flex items-center gap-2 disabled:opacity-50"
              style={{ backgroundColor: '#fff3cd', color: '#755b00', border: '2px solid #755b00', borderBottom: '4px solid #755b00' }}>
              <span className="material-symbols-outlined text-base">key</span>
              Reset Password
            </button>
          </div>
        </div>

        {/* Stats */}
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
          <StatBox icon="menu_book" label="Total Words" value={stats.totalWords} color="#006590" />
          <StatBox icon="star" label="Mastered" value={stats.mastered} color="#1a6b1a" />
          <StatBox icon="school" label="Learning" value={stats.learning} color="#755b00" />
          <StatBox icon="target" label="Accuracy" value={`${stats.accuracy}%`} color="#843ab4" />
          <StatBox icon="local_fire_department" label="Streak" value={`${streak.currentStreak}d`} color="#ba1a1a" />
          <StatBox icon="emoji_events" label="Best Streak" value={`${streak.longestStreak}d`} color="#755b00" />
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Left: Role management + Streak detail */}
          <div className="space-y-6">
            {/* Assign Role */}
            <div className="rounded-2xl p-5 space-y-4"
              style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
              <h3 className="text-sm font-extrabold uppercase tracking-wider" style={{ color: '#1b1c1c' }}>
                <span className="flex items-center gap-2">
                  <span className="material-symbols-outlined text-base" style={{ color: '#006590' }}>manage_accounts</span>
                  Assign Role
                </span>
              </h3>
              <div className="space-y-2">
                {(['USER', 'MODERATOR', 'ADMIN'] as const).map(r => {
                  const rc = ROLE_COLORS[r];
                  const isSelected = selectedRole === r;
                  return (
                    <button key={r}
                      onClick={() => setSelectedRole(r)}
                      className="w-full flex items-center justify-between px-4 py-3 rounded-xl font-bold text-sm transition-all"
                      style={isSelected
                        ? { backgroundColor: rc.bg, border: `2px solid ${rc.text}`, color: rc.text }
                        : { backgroundColor: '#f5f3f3', border: '2px solid transparent', color: '#3e4850' }}>
                      <span>{r}</span>
                      {isSelected && <span className="material-symbols-outlined text-base">check_circle</span>}
                    </button>
                  );
                })}
              </div>
              <button
                onClick={handleAssignRole}
                disabled={actionLoading || selectedRole === user.role}
                className="btn-tactile w-full py-2.5 rounded-xl font-bold text-sm uppercase tracking-wide disabled:opacity-40"
                style={{ backgroundColor: '#006590', color: '#ffffff', borderBottom: '4px solid #004c6e' }}>
                Save Role
              </button>
            </div>

            {/* Streak detail */}
            <div className="rounded-2xl p-5 space-y-3"
              style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
              <h3 className="text-sm font-extrabold uppercase tracking-wider flex items-center gap-2" style={{ color: '#1b1c1c' }}>
                <span className="material-symbols-outlined text-base" style={{ color: '#ba1a1a' }}>local_fire_department</span>
                Streak Details
              </h3>
              {[
                { label: 'Current streak', value: `${streak.currentStreak} days` },
                { label: 'Longest streak', value: `${streak.longestStreak} days` },
                { label: 'Total study days', value: `${streak.totalDaysStudied} days` },
                { label: 'Last study date', value: streak.lastStudyDate || '—' },
              ].map(item => (
                <div key={item.label} className="flex items-center justify-between py-2"
                  style={{ borderBottom: '1px solid #efeded' }}>
                  <span className="text-xs font-bold uppercase tracking-wide" style={{ color: '#3e4850' }}>{item.label}</span>
                  <span className="text-sm font-extrabold" style={{ color: '#1b1c1c' }}>{item.value}</span>
                </div>
              ))}
            </div>
          </div>

          {/* Middle: Decks */}
          <div className="rounded-2xl"
            style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
            <div className="px-5 py-4 flex items-center gap-2 rounded-t-[14px]"
              style={{ borderBottom: '2px solid #bdc8d2', backgroundColor: '#f5f3f3' }}>
              <span className="material-symbols-outlined text-base" style={{ color: '#006590' }}>collections_bookmark</span>
              <h3 className="text-sm font-extrabold uppercase tracking-wider" style={{ color: '#1b1c1c' }}>
                Decks ({decks.length})
              </h3>
            </div>
            {decks.length === 0 ? (
              <div className="p-10 text-center">
                <span className="material-symbols-outlined text-4xl" style={{ color: '#bdc8d2' }}>collections_bookmark</span>
                <p className="text-sm font-bold mt-2" style={{ color: '#3e4850' }}>No decks yet</p>
              </div>
            ) : (
              <div className="divide-y" style={{ borderColor: '#efeded' }}>
                {decks.map(deck => (
                  <div key={deck.id} className="px-5 py-3 flex items-start justify-between"
                    onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#f9f8f8')}
                    onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
                    <div>
                      <p className="text-sm font-bold" style={{ color: '#1b1c1c' }}>{deck.name}</p>
                      {deck.description && (
                        <p className="text-xs mt-0.5 line-clamp-1" style={{ color: '#3e4850' }}>{deck.description}</p>
                      )}
                      <p className="text-xs mt-1" style={{ color: '#bdc8d2' }}>{deck.createdAt.slice(0, 10)}</p>
                    </div>
                    {deck.isPublic && (
                      <span className="px-2 py-0.5 rounded-full text-xs font-bold flex-shrink-0"
                        style={{ backgroundColor: '#e8f4ff', color: '#004c6e' }}>Public</span>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Right: Learning history */}
          <div className="rounded-2xl"
            style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
            <div className="px-5 py-4 flex items-center gap-2 rounded-t-[14px]"
              style={{ borderBottom: '2px solid #bdc8d2', backgroundColor: '#f5f3f3' }}>
              <span className="material-symbols-outlined text-base" style={{ color: '#006590' }}>history</span>
              <h3 className="text-sm font-extrabold uppercase tracking-wider" style={{ color: '#1b1c1c' }}>
                Recent Reviews
              </h3>
            </div>
            {history.length === 0 ? (
              <div className="p-10 text-center">
                <span className="material-symbols-outlined text-4xl" style={{ color: '#bdc8d2' }}>history</span>
                <p className="text-sm font-bold mt-2" style={{ color: '#3e4850' }}>No review history</p>
              </div>
            ) : (
              <div className="divide-y max-h-[480px] overflow-y-auto custom-scrollbar" style={{ borderColor: '#efeded' }}>
                {history.map(item => {
                  const r = RATING_LABELS[item.rating] ?? { label: String(item.rating), color: '#3e4850' };
                  return (
                    <div key={item.id} className="px-5 py-3 flex items-center justify-between"
                      onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#f9f8f8')}
                      onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
                      <div className="flex items-center gap-3">
                        <span className={`w-2 h-2 rounded-full flex-shrink-0`}
                          style={{ backgroundColor: item.correct ? '#1a6b1a' : '#ba1a1a' }} />
                        <div>
                          <p className="text-sm font-bold" style={{ color: '#1b1c1c' }}>{item.word}</p>
                          <p className="text-xs" style={{ color: '#bdc8d2' }}>{item.reviewedAt.slice(0, 16).replace('T', ' ')}</p>
                        </div>
                      </div>
                      <span className="px-2 py-0.5 rounded-full text-xs font-bold"
                        style={{ backgroundColor: r.color + '20', color: r.color }}>
                        {r.label}
                      </span>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}
