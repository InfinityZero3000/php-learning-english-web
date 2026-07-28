'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import AdminLayout from '@/components/AdminLayout';
import StatsCard from '@/components/StatsCard';
import {
  adminCourses,
  adminUsers,
  auth,
  operations,
  type AdminCourse,
  type OperationsOverview,
} from '@/lib/api';

type DashboardData = {
  role: string;
  users: number;
  courses: AdminCourse[];
  operations: OperationsOverview | null;
};

export default function DashboardPage() {
  const [data, setData] = useState<DashboardData | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;

    (async () => {
      try {
        const user = await auth.me();
        const [users, courses, platform] = await Promise.all([
          adminUsers.list({ perPage: 1 }),
          adminCourses.list(),
          user.role === 'super_admin' ? operations.overview() : Promise.resolve(null),
        ]);
        if (active) setData({ role: user.role ?? 'admin', users: users.meta.total, courses: courses.data, operations: platform });
      } catch (reason) {
        if (active) setError(reason instanceof Error ? reason.message : 'Không thể tải dashboard.');
      }
    })();

    return () => {
      active = false;
    };
  }, []);

  const published = data?.courses.filter((course) => course.status === 'published').length ?? 0;
  const lessons = data?.courses.reduce((total, course) => total + (course.lessons_count ?? 0), 0) ?? 0;

  return (
    <AdminLayout title="Dashboard">
      <div className="space-y-8">
        <section className="rounded-3xl p-8 md:p-10 flex flex-col md:flex-row gap-8 items-center justify-between"
          style={{ backgroundColor: '#e8f4ff', border: '2px solid #1cb0f6' }}>
          <div className="space-y-4">
            <p className="text-xs font-black uppercase tracking-[0.2em]" style={{ color: '#006590' }}>LexiLingo Control Center</p>
            <h2 className="text-4xl font-extrabold max-w-xl" style={{ color: '#00405d', letterSpacing: '-0.02em' }}>
              Quản lý nội dung và vận hành học tập.
            </h2>
            <p className="text-sm font-medium max-w-xl" style={{ color: '#3e4850' }}>
              Dữ liệu trên trang này được đọc trực tiếp từ API quản trị đang hoạt động.
            </p>
            <div className="flex flex-wrap gap-3">
              <DashboardLink href="/courses" label="Quản lý khóa học" primary />
              <DashboardLink href="/users" label="Quản lý người dùng" />
              {data?.role === 'super_admin' && <DashboardLink href="/operations" label="AI & Monitoring" />}
            </div>
          </div>
          <div className="w-40 h-40 rounded-full flex items-center justify-center shrink-0"
            style={{ backgroundColor: '#006590', border: '8px solid #004c6e' }}>
            <span className="material-symbols-outlined" style={{ color: '#fff', fontSize: 70 }}>admin_panel_settings</span>
          </div>
        </section>

        {error && (
          <div role="alert" className="rounded-2xl p-4 font-bold text-sm" style={{ backgroundColor: '#ffdad6', color: '#93000a', border: '2px solid #ffb4ab' }}>
            {error}
          </div>
        )}

        <section aria-label="Thống kê quản trị" className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <StatsCard icon="group" label="Người dùng" value={data ? data.users.toLocaleString() : '...'} />
          <StatsCard icon="auto_stories" label="Khóa học" value={data ? data.courses.length : '...'} color="#843ab4" />
          <StatsCard icon="publish" label="Đã xuất bản" value={data ? published : '...'} color="#755b00" />
          <StatsCard icon="menu_book" label="Bài học" value={data ? lessons : '...'} color="#006590" />
        </section>

        {data?.role === 'super_admin' && data.operations && (
          <section className="rounded-3xl p-6" style={{ backgroundColor: '#fff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
            <div className="flex flex-wrap items-center justify-between gap-4">
              <div>
                <p className="text-xs font-black uppercase tracking-widest" style={{ color: '#6e7881' }}>Super Admin</p>
                <h3 className="text-xl font-extrabold mt-1">Tình trạng dịch vụ AI</h3>
              </div>
              <Link href="/operations" className="font-bold text-sm" style={{ color: '#006590' }}>Mở monitoring →</Link>
            </div>
            <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-5">
              {Object.entries(data.operations.services).map(([service, healthy]) => (
                <div key={service} className="rounded-xl p-4 flex items-center justify-between" style={{ backgroundColor: '#f5f3f3' }}>
                  <span className="font-bold capitalize">{service.replaceAll('_', ' ')}</span>
                  <span className="text-xs font-black uppercase" style={{ color: healthy ? '#006c4c' : '#93000a' }}>
                    {healthy ? 'Online' : 'Offline'}
                  </span>
                </div>
              ))}
              <div className="rounded-xl p-4 flex items-center justify-between" style={{ backgroundColor: '#f5f3f3' }}>
                <span className="font-bold">Cảnh báo mở</span>
                <span className="font-black" style={{ color: data.operations.open_alerts ? '#93000a' : '#006c4c' }}>
                  {data.operations.open_alerts}
                </span>
              </div>
            </div>
          </section>
        )}
      </div>
    </AdminLayout>
  );
}

function DashboardLink({ href, label, primary = false }: { href: string; label: string; primary?: boolean }) {
  return (
    <Link href={href} className="btn-tactile px-6 py-3 rounded-xl font-bold text-sm"
      style={primary
        ? { backgroundColor: '#006590', color: '#fff', borderBottom: '4px solid #004c6e' }
        : { backgroundColor: '#fff', color: '#006590', border: '2px solid #006590', borderBottom: '4px solid #006590' }}>
      {label}
    </Link>
  );
}
