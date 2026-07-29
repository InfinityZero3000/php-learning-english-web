'use client';

import { useState } from 'react';
import AdminLayout from '@/components/AdminLayout';
import { DataPanel, DateRange, PageHeading } from '@/components/AdminDataView';
import { adminLearning } from '@/lib/api';

export default function ReportsPage() { return <AdminLayout title="Reports"><PageContent /></AdminLayout>; }
function PageContent() {
  const [from, setFrom] = useState(''); const [to, setTo] = useState(''); const [state, setState] = useState<'idle' | 'loading' | 'error'>('idle'); const [message, setMessage] = useState('');
  async function download() {
    setState('loading'); setMessage('');
    try {
      const blob = await adminLearning.report(from || undefined, to || undefined);
      const url = URL.createObjectURL(blob); const anchor = document.createElement('a');
      anchor.href = url; anchor.download = 'learning-report.csv'; document.body.appendChild(anchor); anchor.click(); anchor.remove(); URL.revokeObjectURL(url); setState('idle');
    } catch (error) { setMessage(error instanceof Error ? error.message : 'Không thể tải report.'); setState('error'); }
  }
  return <div className="space-y-6"><PageHeading title="Learning reports" description="Xuất CSV tổng hợp theo khóa học; không bao gồm PII của người học." /><DataPanel title="CSV report"><div className="space-y-5"><DateRange from={from} to={to} onFrom={setFrom} onTo={setTo} onApply={() => void download()} /><p className="text-sm text-[#3e4850]">Cột: period, course, enrollments, started, completed, completion rate và average score.</p>{message && <p role="alert" className="rounded-xl bg-[#ffdad6] p-4 font-bold text-[#93000a]">{message}</p>}<button disabled={state === 'loading'} onClick={() => void download()} className="rounded-xl bg-[#006590] px-5 py-3 font-bold text-white disabled:opacity-50">{state === 'loading' ? 'Đang tạo…' : 'Tải CSV'}</button></div></DataPanel></div>;
}
