'use client';

import { useCallback, useEffect, useState } from 'react';
import AdminLayout from '@/components/AdminLayout';
import { DataPanel, PageHeading, StateNotice, StatCard } from '@/components/AdminDataView';
import { adminLearning, type AdminFsrsAnalytics } from '@/lib/api';

export default function SpacedRepetitionPage() { return <AdminLayout title="Spaced Repetition"><PageContent /></AdminLayout>; }
function PageContent() {
  const [data, setData] = useState<AdminFsrsAnalytics>(); const [error, setError] = useState('');
  const load = useCallback(() => { setError(''); return adminLearning.fsrs().then(setData).catch((e) => setError(e instanceof Error ? e.message : 'Không thể tải FSRS analytics.')); }, []);
  useEffect(() => { void Promise.resolve().then(load); }, [load]);
  if (error) return <StateNotice state="error" message={error} retry={load} />;
  if (!data) return <StateNotice state="loading" />;
  return <div className="space-y-6"><PageHeading title="FSRS overview" description="Độ ổn định, độ khó và trạng thái thẻ tổng hợp; tham số thuật toán không được sửa từ giao diện này." /><section className="grid gap-4 sm:grid-cols-3"><StatCard label="Reviews" value={data.reviews} /><StatCard label="Average stability" value={data.average_stability ?? '—'} accent="#1b5e20" /><StatCard label="Average difficulty" value={data.average_difficulty ?? '—'} accent="#843ab4" /></section><DataPanel title="Card states">{data.state_bands.length === 0 ? <StateNotice state="empty" /> : <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">{data.state_bands.map((band) => <div key={band.state} className="rounded-2xl bg-[#f5f3f3] p-4"><p className="text-xs font-black uppercase text-[#6e7881]">{band.state}</p><p className="mt-1 text-2xl font-black">{band.count}</p></div>)}</div>}</DataPanel></div>;
}
