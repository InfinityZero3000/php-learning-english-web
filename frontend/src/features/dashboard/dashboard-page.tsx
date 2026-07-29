"use client";

import Link from "next/link";
import { IconChevronRight, IconPlayerPlayFilled, IconPresentation, IconTrophy } from "@tabler/icons-react";
import { useEffect, useState } from "react";
import { navigationIcons } from "@/components/icons/app-icons";
import { AppShellLoading } from "@/components/layout/app-shell";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { api, type LearnerAssignment, type LearningPlan, type SupervisedDashboard } from "@/lib/api";

type TodayData = {
  plan: LearningPlan;
  dashboard: SupervisedDashboard;
  assignments: LearnerAssignment[];
  due: number;
};

export function DashboardPage() {
  const [data, setData] = useState<TodayData | null>(null);
  const [error, setError] = useState("");

  useEffect(() => {
    let active = true;
    Promise.all([
      api.learningPlan(),
      api.supervisedDashboard(),
      api.learningAssignments(),
      api.fsrsDue(1),
    ])
      .then(([plan, dashboard, assignments, due]) => {
        if (active) setData({ plan, dashboard, assignments, due: due.meta.total });
      })
      .catch((reason) => {
        if (active) setError(reason instanceof Error ? reason.message : "Không thể tải kế hoạch hôm nay.");
      });
    return () => {
      active = false;
    };
  }, []);

  if (!data && !error) return <AppShellLoading label="Loading today..." />;

  const activeAssignments = data?.assignments.filter((item) => ["assigned", "in_progress"].includes(item.status)).length ?? 0;
  const nextHref = activeAssignments ? "/assignments" : data?.due ? "/review" : "/courses";

  return (
    <div className="space-y-6">
      <section className="relative min-h-[260px] overflow-hidden rounded-xl bg-primary p-6 text-primary-foreground md:p-8">
        <div className="relative z-10 max-w-2xl">
          <p className="font-display text-sm font-bold uppercase tracking-widest text-sky-100">Kế hoạch có giám sát</p>
          <h2 className="mt-2 font-display text-[28px] font-bold leading-tight">Sẵn sàng học hôm nay?</h2>
          <p className="mt-3 text-[17px] font-bold text-sky-100">
            {activeAssignments} bài được giao · {data?.due ?? 0} thẻ FSRS đến hạn.
          </p>
          <Link href={nextHref} className="mt-10 inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-white px-8 font-display text-sm font-bold uppercase tracking-[0.05em] text-primary btn-press transition hover:bg-sky-50">
            <IconPlayerPlayFilled className="h-5 w-5" />
            Tiếp tục học
          </Link>
        </div>
        <div className="absolute right-14 top-12 hidden h-40 w-40 items-center justify-center rounded-full bg-sky-200/20 text-white sm:flex">
          <IconPresentation className="h-20 w-20" stroke={1.6} />
        </div>
      </section>

      {error && <p role="alert" className="rounded-xl border-2 border-red-200 bg-red-50 p-4 font-bold text-red-800">{error}</p>}

      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Metric href="/assignments" icon={<navigationIcons.quiz />} label="Assignments" value={activeAssignments} sub="Đang cần hoàn thành" />
        <Metric href="/review" icon={<navigationIcons.words />} label="Due Today" value={data?.due ?? 0} sub="FSRS cards" />
        <Metric href="/progress" icon={<IconTrophy />} label="Average Score" value={`${Math.round(data?.dashboard.overview.average_score ?? 0)}%`} sub="Kết quả gần đây" />
        <Metric href="/progress" icon={<navigationIcons.progress />} label="Lessons" value={data?.dashboard.overview.completed_lessons ?? 0} sub="Đã hoàn thành" />
      </section>

      <Card>
        <CardHeader>
          <CardTitle>Kế hoạch ưu tiên</CardTitle>
          <CardDescription>Thứ tự do hệ thống giám sát và FSRS đề xuất.</CardDescription>
        </CardHeader>
        <CardContent className="grid gap-3 md:grid-cols-2">
          {data?.plan.items.length ? data.plan.items.slice(0, 6).map((item) => (
            <div key={`${item.type}-${item.id}`} className="flex items-center justify-between rounded-xl bg-muted p-4">
              <div>
                <p className="font-display font-bold">{planLabel(item.type)}</p>
                <p className="text-sm font-semibold text-muted-foreground">Ưu tiên {item.priority}</p>
              </div>
              <IconChevronRight className="h-5 w-5 text-primary" />
            </div>
          )) : (
            <p className="text-sm font-semibold text-muted-foreground">Chưa có hoạt động bắt buộc. Bạn có thể chọn một khóa học mới.</p>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

function Metric({ href, icon, label, value, sub }: { href: string; icon: React.ReactNode; label: string; value: string | number; sub: string }) {
  return (
    <Link href={href}>
      <Card className="h-full transition hover:border-primary">
        <CardContent className="flex items-center gap-4 p-5">
          <span className="text-primary [&>svg]:h-8 [&>svg]:w-8">{icon}</span>
          <span>
            <span className="block text-sm font-bold text-muted-foreground">{label}</span>
            <span className="block font-display text-2xl font-bold">{value}</span>
            <span className="text-xs font-semibold text-muted-foreground">{sub}</span>
          </span>
        </CardContent>
      </Card>
    </Link>
  );
}

function planLabel(type: LearningPlan["items"][number]["type"]) {
  return {
    teacher_lesson: "Bài học giáo viên giao",
    teacher_vocabulary_practice: "Luyện từ vựng được giao",
    fsrs_review: "Ôn tập FSRS",
    course_activity: "Hoạt động khóa học",
    pronunciation_task: "Luyện phát âm",
    remediation: "Củng cố kiến thức",
  }[type];
}
