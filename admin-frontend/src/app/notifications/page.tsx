'use client';

import { useCallback, useEffect, useState } from 'react';
import AdminLayout from '@/components/AdminLayout';
import { DataPanel, PageHeading, StateNotice, StatCard } from '@/components/AdminDataView';
import { adminNotifications, type AdminNotification } from '@/lib/api';

export default function NotificationsPage() { return <AdminLayout title="Notifications"><PageContent /></AdminLayout>; }
function PageContent() {
  const [items, setItems] = useState<AdminNotification[]>(); const [error, setError] = useState(''); const [busy, setBusy] = useState<number>();
  const load = useCallback(() => { setError(''); return adminNotifications.list().then(setItems).catch((e) => setError(e instanceof Error ? e.message : 'Không thể tải notifications.')); }, []);
  useEffect(() => { void Promise.resolve().then(load); }, [load]);
  async function markRead(id: number) { setBusy(id); try { await adminNotifications.markRead(id); setItems((current) => current?.map((item) => item.id === id ? { ...item, read: true } : item)); } catch (e) { setError(e instanceof Error ? e.message : 'Không thể đánh dấu đã đọc.'); } finally { setBusy(undefined); } }
  if (error && !items) return <StateNotice state="error" message={error} retry={load} />;
  if (!items) return <StateNotice state="loading" />;
  return <div className="space-y-6"><PageHeading title="Operational notifications" description="Cảnh báo giám sát đã được loại bỏ thông tin định danh và evidence." /><section className="grid gap-4 sm:grid-cols-2"><StatCard label="Open" value={items.filter((item) => item.state === 'open').length} accent="#ba1a1a" /><StatCard label="Unread" value={items.filter((item) => !item.read).length} /></section>{error && <p role="alert" className="rounded-xl bg-[#ffdad6] p-4 font-bold text-[#93000a]">{error}</p>}<DataPanel title="Alerts">{items.length === 0 ? <StateNotice state="empty" /> : <div>{items.map((item) => <article key={item.id} className="flex flex-col justify-between gap-4 border-b py-4 last:border-0 sm:flex-row sm:items-center"><div><div className="flex gap-2"><span className="rounded-full bg-[#e8f4ff] px-3 py-1 text-xs font-black uppercase text-[#006590]">{item.severity}</span><span className="text-xs font-bold text-[#6e7881]">{item.type}</span></div><p className="mt-2 font-bold">{item.summary}</p><time className="text-xs text-[#6e7881]">{item.created_at ? new Intl.DateTimeFormat('vi-VN', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(item.created_at)) : '—'}</time></div>{!item.read && <button disabled={busy === item.id} onClick={() => void markRead(item.id)} className="rounded-xl border-2 border-[#88ceff] px-4 py-2 font-bold text-[#006590] disabled:opacity-50">Đánh dấu đã đọc</button>}</article>)}</div>}</DataPanel></div>;
}
