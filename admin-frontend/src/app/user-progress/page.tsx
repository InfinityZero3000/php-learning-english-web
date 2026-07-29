'use client';

import { useCallback, useEffect, useState } from 'react';
import AdminLayout from '@/components/AdminLayout';
import { DataPanel, PageHeading, StateNotice, StatCard } from '@/components/AdminDataView';
import { adminLearning, type AdminProgressAnalytics } from '@/lib/api';

export default function UserProgressPage() { return <AdminLayout title="User Progress"><PageContent /></AdminLayout>; }
function PageContent() {
  const [data, setData] = useState<AdminProgressAnalytics>(); const [error, setError] = useState('');
  const load = useCallback(() => { setError(''); return adminLearning.progress().then(setData).catch((e) => setError(e instanceof Error ? e.message : 'Không thể tải progress analytics.')); }, []);
  useEffect(() => { void Promise.resolve().then(load); }, [load]);
  if (error) return <StateNotice state="error" message={error} retry={load} />;
  if (!data) return <StateNotice state="loading" />;
  return <div className="space-y-6"><PageHeading title="Learner progress" description="Tiến độ tổng hợp theo khóa học; không hiển thị hồ sơ hoặc bằng chứng cá nhân." /><StatCard label="Completed lessons" value={data.completed_lessons} accent="#1b5e20" /><DataPanel title="Course completion">{data.by_course.length === 0 ? <StateNotice state="empty" /> : data.by_course.map((row) => <div key={row.course_id} className="flex justify-between gap-4 border-b py-3 last:border-0"><span className="font-bold">{row.course_title}</span><span className="tabular-nums">{row.completed_lessons ?? 0}</span></div>)}</DataPanel></div>;
}
