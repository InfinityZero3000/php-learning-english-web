'use client';

import { useCallback, useEffect, useState } from 'react';
import AdminLayout from '@/components/AdminLayout';
import { DataPanel, PageHeading, StateNotice, StatCard } from '@/components/AdminDataView';
import { adminLearning, type AdminQuizAnalytics } from '@/lib/api';
import Link from 'next/link';

export default function QuizzesPage() { return <AdminLayout title="Quizzes"><PageContent /></AdminLayout>; }
function PageContent() {
  const [data, setData] = useState<AdminQuizAnalytics>(); const [error, setError] = useState('');
  const load = useCallback(() => { setError(''); return adminLearning.quizzes().then(setData).catch((e) => setError(e instanceof Error ? e.message : 'Không thể tải quiz analytics.')); }, []);
  useEffect(() => { void Promise.resolve().then(load); }, [load]);
  if (error) return <StateNotice state="error" message={error} retry={load} />;
  if (!data) return <StateNotice state="loading" />;
  return <div className="space-y-6"><div className="flex flex-wrap items-end justify-between gap-4"><PageHeading title="Quiz performance" description="Hiệu suất quiz tổng hợp theo khóa học trong 30 ngày gần nhất." /><Link href="/quiz-management" className="rounded-xl bg-[#006590] px-5 py-3 font-bold text-white">Author quizzes</Link></div><section className="grid gap-4 sm:grid-cols-2"><StatCard label="Attempts" value={data.attempts} /><StatCard label="Courses with attempts" value={data.by_course.length} accent="#843ab4" /></section><DataPanel title="Course breakdown">{data.by_course.length === 0 ? <StateNotice state="empty" /> : <div className="overflow-x-auto"><table className="w-full text-left"><thead><tr><th className="p-3">Course</th><th className="p-3">Attempts</th><th className="p-3">Average score</th></tr></thead><tbody>{data.by_course.map((row) => <tr key={row.course_id} className="border-t"><td className="p-3 font-bold">{row.course_title}</td><td className="p-3">{row.attempts ?? 0}</td><td className="p-3">{row.average_score == null ? '—' : `${row.average_score}%`}</td></tr>)}</tbody></table></div>}</DataPanel></div>;
}
