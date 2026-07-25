'use client';

import { useState } from 'react';
import AdminLayout from '@/components/AdminLayout';

type AuditAction = 'USER_LOCKED' | 'USER_UNLOCKED' | 'ROLE_CHANGED' | 'PASSWORD_RESET' | 'WORD_CREATED' | 'WORD_DELETED' | 'DECK_CREATED' | 'DECK_DELETED' | 'ADMIN_LOGIN' | 'SETTINGS_CHANGED';

interface AuditEntry {
  id: number;
  timestamp: string;
  action: AuditAction;
  actor: string;
  actorRole: string;
  resource: string;
  detail: string;
  ip: string;
  status: 'SUCCESS' | 'FAILED';
}

const ACTION_STYLE: Record<AuditAction, { bg: string; color: string; icon: string }> = {
  USER_LOCKED: { bg: '#ffdad6', color: '#93000a', icon: 'lock' },
  USER_UNLOCKED: { bg: '#d8f3dc', color: '#1b5e20', icon: 'lock_open' },
  ROLE_CHANGED: { bg: '#f4d9ff', color: '#5d068e', icon: 'admin_panel_settings' },
  PASSWORD_RESET: { bg: '#ffdf92', color: '#594400', icon: 'key' },
  WORD_CREATED: { bg: '#c8e6ff', color: '#004c6e', icon: 'add_circle' },
  WORD_DELETED: { bg: '#ffdad6', color: '#93000a', icon: 'delete' },
  DECK_CREATED: { bg: '#c8e6ff', color: '#004c6e', icon: 'collections_bookmark' },
  DECK_DELETED: { bg: '#ffdad6', color: '#93000a', icon: 'delete_forever' },
  ADMIN_LOGIN: { bg: '#d8f3dc', color: '#1b5e20', icon: 'login' },
  SETTINGS_CHANGED: { bg: '#efeded', color: '#3e4850', icon: 'settings' },
};

const MOCK_LOGS: AuditEntry[] = [
  { id: 1, timestamp: '2026-05-31T10:42:00', action: 'USER_LOCKED', actor: 'admin@example.com', actorRole: 'ADMIN', resource: 'User #42', detail: 'Locked user account for policy violation', ip: '192.168.1.10', status: 'SUCCESS' },
  { id: 2, timestamp: '2026-05-31T10:38:15', action: 'ROLE_CHANGED', actor: 'admin@example.com', actorRole: 'ADMIN', resource: 'User #38', detail: 'Changed role from USER to MODERATOR', ip: '192.168.1.10', status: 'SUCCESS' },
  { id: 3, timestamp: '2026-05-31T10:15:02', action: 'WORD_CREATED', actor: 'mod@example.com', actorRole: 'MODERATOR', resource: 'Word #204', detail: 'Created flashcard: "ephemeral"', ip: '10.0.0.55', status: 'SUCCESS' },
  { id: 4, timestamp: '2026-05-31T09:55:30', action: 'PASSWORD_RESET', actor: 'admin@example.com', actorRole: 'ADMIN', resource: 'User #17', detail: 'Temporary password generated and emailed', ip: '192.168.1.10', status: 'SUCCESS' },
  { id: 5, timestamp: '2026-05-31T09:30:00', action: 'ADMIN_LOGIN', actor: 'admin@example.com', actorRole: 'ADMIN', resource: 'Auth', detail: 'Admin session started', ip: '192.168.1.10', status: 'SUCCESS' },
  { id: 6, timestamp: '2026-05-30T22:14:08', action: 'DECK_DELETED', actor: 'admin@example.com', actorRole: 'ADMIN', resource: 'Deck #12', detail: 'Deleted deck "Test Set"', ip: '192.168.1.10', status: 'SUCCESS' },
  { id: 7, timestamp: '2026-05-30T21:05:44', action: 'USER_UNLOCKED', actor: 'admin@example.com', actorRole: 'ADMIN', resource: 'User #42', detail: 'Account unlocked after appeal', ip: '192.168.1.10', status: 'SUCCESS' },
  { id: 8, timestamp: '2026-05-30T18:30:00', action: 'SETTINGS_CHANGED', actor: 'admin@example.com', actorRole: 'ADMIN', resource: 'System Config', detail: 'Updated notification settings', ip: '192.168.1.10', status: 'SUCCESS' },
  { id: 9, timestamp: '2026-05-30T15:22:10', action: 'WORD_DELETED', actor: 'mod@example.com', actorRole: 'MODERATOR', resource: 'Word #199', detail: 'Deleted duplicate word "recieve"', ip: '10.0.0.55', status: 'SUCCESS' },
  { id: 10, timestamp: '2026-05-30T14:00:00', action: 'DECK_CREATED', actor: 'mod@example.com', actorRole: 'MODERATOR', resource: 'Deck #15', detail: 'Created deck "Advanced Business"', ip: '10.0.0.55', status: 'SUCCESS' },
  { id: 11, timestamp: '2026-05-29T11:45:30', action: 'ROLE_CHANGED', actor: 'admin@example.com', actorRole: 'ADMIN', resource: 'User #25', detail: 'Changed role from MODERATOR to USER', ip: '192.168.1.10', status: 'SUCCESS' },
  { id: 12, timestamp: '2026-05-29T09:10:05', action: 'PASSWORD_RESET', actor: 'admin@example.com', actorRole: 'ADMIN', resource: 'User #88', detail: 'Password reset requested by user', ip: '192.168.1.10', status: 'FAILED' },
];

const ALL_ACTIONS: AuditAction[] = ['USER_LOCKED', 'USER_UNLOCKED', 'ROLE_CHANGED', 'PASSWORD_RESET', 'WORD_CREATED', 'WORD_DELETED', 'DECK_CREATED', 'DECK_DELETED', 'ADMIN_LOGIN', 'SETTINGS_CHANGED'];

function fmt(dt: string) {
  try { return new Intl.DateTimeFormat('en', { dateStyle: 'medium', timeStyle: 'medium' }).format(new Date(dt)); }
  catch { return dt; }
}

export default function AuditLogsPage() {
  const [actionFilter, setActionFilter] = useState('');
  const [search, setSearch] = useState('');

  const filtered = MOCK_LOGS.filter(log => {
    const matchAction = !actionFilter || log.action === actionFilter;
    const matchSearch = !search || log.actor.includes(search) || log.resource.toLowerCase().includes(search.toLowerCase()) || log.detail.toLowerCase().includes(search.toLowerCase());
    return matchAction && matchSearch;
  });

  return (
    <AdminLayout title="Audit Logs">
      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
          <div>
            <h2 className="text-2xl font-extrabold" style={{ color: '#1b1c1c', letterSpacing: '-0.01em' }}>Audit Logs</h2>
            <p className="text-sm font-medium mt-0.5" style={{ color: '#3e4850' }}>
              Immutable trail of all admin and moderator actions
            </p>
          </div>
          <div className="flex items-center gap-2 px-4 py-2 rounded-xl" style={{ backgroundColor: '#ffdf92', border: '2px solid #f4bf00' }}>
            <span className="material-symbols-outlined" style={{ fontSize: '16px', color: '#594400' }}>info</span>
            <span className="text-xs font-bold" style={{ color: '#594400' }}>Demo data — wire to /api/admin/audit-logs</span>
          </div>
        </div>

        {/* Filters */}
        <div className="flex flex-wrap gap-3">
          <div className="relative">
            <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-lg" style={{ color: '#3e4850' }}>search</span>
            <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search actor, resource, detail..." className="pl-10 pr-4 py-2.5 rounded-xl text-sm font-medium outline-none" style={{ border: '2px solid #bdc8d2', backgroundColor: '#ffffff', color: '#1b1c1c', width: '280px' }} onFocus={e => (e.target.style.borderColor = '#006590')} onBlur={e => (e.target.style.borderColor = '#bdc8d2')} />
          </div>
          <select value={actionFilter} onChange={e => setActionFilter(e.target.value)} className="py-2.5 px-4 rounded-xl text-sm font-bold outline-none" style={{ border: '2px solid #bdc8d2', backgroundColor: '#ffffff', color: '#1b1c1c' }}>
            <option value="">All Actions</option>
            {ALL_ACTIONS.map(a => <option key={a} value={a}>{a.replace(/_/g, ' ')}</option>)}
          </select>
          {(actionFilter || search) && (
            <button onClick={() => { setActionFilter(''); setSearch(''); }} className="px-4 py-2.5 rounded-xl text-sm font-bold" style={{ backgroundColor: '#efeded', color: '#3e4850', border: '2px solid #bdc8d2' }}>
              Clear
            </button>
          )}
        </div>

        {/* Table */}
        <div className="rounded-3xl" style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
          <div className="p-5 flex justify-between items-center rounded-t-[22px]" style={{ borderBottom: '2px solid #bdc8d2', backgroundColor: '#f5f3f3' }}>
            <h3 className="text-base font-extrabold" style={{ color: '#1b1c1c' }}>Event Log</h3>
            <span className="text-xs font-bold" style={{ color: '#6e7881' }}>{filtered.length} events</span>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-left">
              <thead>
                <tr style={{ borderBottom: '2px solid #efeded' }}>
                  {['Action', 'Actor', 'Resource', 'Detail', 'IP Address', 'Status', 'Timestamp'].map(h => (
                    <th key={h} className="px-5 py-4 text-xs font-bold uppercase tracking-widest" style={{ color: '#3e4850' }}>{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {filtered.length === 0 ? (
                  <tr><td colSpan={7} className="p-10 text-center">
                    <span className="material-symbols-outlined text-4xl block mb-2" style={{ color: '#bdc8d2' }}>manage_history</span>
                    <p className="text-sm font-medium" style={{ color: '#3e4850' }}>No audit events match your filters.</p>
                  </td></tr>
                ) : filtered.map((log) => {
                  const as = ACTION_STYLE[log.action];
                  return (
                    <tr key={log.id} style={{ borderTop: '1px solid #efeded' }} onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#f5f3f3')} onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
                      <td className="px-5 py-4">
                        <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold" style={{ backgroundColor: as.bg, color: as.color }}>
                          <span className="material-symbols-outlined" style={{ fontSize: '13px', fontVariationSettings: "'FILL' 1" }}>{as.icon}</span>
                          {log.action.replace(/_/g, ' ')}
                        </span>
                      </td>
                      <td className="px-5 py-4">
                        <p className="text-sm font-bold" style={{ color: '#1b1c1c' }}>{log.actor}</p>
                        <span className="text-[10px] font-bold px-1.5 py-0.5 rounded" style={{ backgroundColor: log.actorRole === 'ADMIN' ? '#d8f3dc' : '#c8e6ff', color: log.actorRole === 'ADMIN' ? '#1b5e20' : '#004c6e' }}>{log.actorRole}</span>
                      </td>
                      <td className="px-5 py-4 font-bold text-sm" style={{ color: '#006590' }}>{log.resource}</td>
                      <td className="px-5 py-4 text-sm max-w-xs" style={{ color: '#3e4850' }}>
                        <span className="line-clamp-2">{log.detail}</span>
                      </td>
                      <td className="px-5 py-4 font-mono text-xs" style={{ color: '#6e7881' }}>{log.ip}</td>
                      <td className="px-5 py-4">
                        <span className="px-2.5 py-1 rounded-full text-[11px] font-bold" style={{ backgroundColor: log.status === 'SUCCESS' ? '#d8f3dc' : '#ffdad6', color: log.status === 'SUCCESS' ? '#1b5e20' : '#93000a' }}>{log.status}</span>
                      </td>
                      <td className="px-5 py-4 text-xs font-medium" style={{ color: '#3e4850' }}>{fmt(log.timestamp)}</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          <div className="px-5 py-4 flex items-center justify-between" style={{ borderTop: '2px solid #bdc8d2', backgroundColor: '#f5f3f3' }}>
            <span className="text-xs font-bold" style={{ color: '#6e7881' }}>Showing {filtered.length} of {MOCK_LOGS.length} events</span>
            <span className="text-xs font-medium" style={{ color: '#6e7881' }}>Logs are retained for 90 days</span>
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}
