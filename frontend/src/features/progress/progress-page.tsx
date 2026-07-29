"use client";

import { IconAward, IconBrain, IconCircleCheck, IconTarget } from "@tabler/icons-react";
import { useEffect, useState } from "react";
import { AppShellLoading } from "@/components/layout/app-shell";
import { fetchDashboard, fetchMyProgress, type DashboardResource, type ProgressResource } from "@/lib/api";
import { formatDateTime } from "@/lib/utils";

export function ProgressPage() {
  const [dashboard, setDashboard] = useState<DashboardResource | null>(null);
  const [progress, setProgress] = useState<ProgressResource[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  async function load() {
    setLoading(true);
    setError(null);
    try {
      const [dashboardData, progressData] = await Promise.all([fetchDashboard(), fetchMyProgress()]);
      setDashboard(dashboardData);
      setProgress(progressData);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : "Failed to load progress");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void load();
  }, []);

  if (loading) return <AppShellLoading label="Loading progress..." />;

  if (error) {
    return (
      <div className="py-20 text-center">
        <p className="font-semibold text-destructive" role="alert">{error}</p>
        <button type="button" onClick={() => void load()} className="mt-4 rounded-xl bg-primary px-5 py-2.5 font-bold text-primary-foreground">
          Retry
        </button>
      </div>
    );
  }

  const overview = dashboard?.overview ?? { completed_lessons: 0, quiz_attempts: 0, average_score: 0 };

  return (
    <div className="space-y-8">
      <section>
        <h2 className="mb-6 font-display text-2xl font-bold">Overview</h2>
        <div className="grid gap-4 sm:grid-cols-3">
          <Metric icon={<IconAward />} label="Lessons Done" value={overview.completed_lessons} />
          <Metric icon={<IconBrain />} label="Quiz Attempts" value={overview.quiz_attempts} />
          <Metric icon={<IconTarget />} label="Average Score" value={`${overview.average_score}%`} />
        </div>
      </section>

      <section className="grid gap-6 lg:grid-cols-2">
        <Activity title="Completed Lessons" empty="No lessons completed yet.">
          {progress.map((item) => (
            <ActivityRow
              key={item.id}
              title={item.lesson?.title ?? `Lesson #${item.lesson_id ?? item.id}`}
              detail={item.lesson?.course?.title}
              date={item.completed_at}
            />
          ))}
        </Activity>

        <Activity title="Recent Quiz Attempts" empty="No quiz attempts yet.">
          {(dashboard?.recent_attempts ?? []).map((attempt) => (
            <ActivityRow
              key={attempt.id}
              title={`Quiz #${attempt.quiz_id ?? "-"}`}
              detail={`Score: ${attempt.score}%`}
              date={attempt.completed_at}
            />
          ))}
        </Activity>
      </section>
    </div>
  );
}

function Metric({ icon, label, value }: { icon: React.ReactNode; label: string; value: number | string }) {
  return (
    <div className="rounded-xl border-2 border-border bg-white p-6">
      <span className="text-primary">{icon}</span>
      <p className="mt-3 font-display text-3xl font-black text-primary">{value}</p>
      <p className="mt-1 font-display text-sm font-bold uppercase tracking-widest text-muted-foreground">{label}</p>
    </div>
  );
}

function Activity({ title, empty, children }: { title: string; empty: string; children: React.ReactNode[] }) {
  return (
    <div className="rounded-xl border-2 border-border bg-white p-5">
      <h3 className="mb-4 font-display text-lg font-bold">{title}</h3>
      {children.length ? <div className="space-y-3">{children}</div> : <p className="text-sm text-muted-foreground">{empty}</p>}
    </div>
  );
}

function ActivityRow({ title, detail, date }: { title: string; detail?: string; date?: string }) {
  return (
    <div className="flex items-center gap-3 rounded-xl bg-muted/50 p-3">
      <IconCircleCheck className="h-5 w-5 shrink-0 text-primary" />
      <div className="min-w-0">
        <p className="truncate font-bold">{title}</p>
        <p className="text-xs text-muted-foreground">{[detail, formatDateTime(date)].filter(Boolean).join(" · ")}</p>
      </div>
    </div>
  );
}
