"use client";

import { IconAward, IconBooks, IconBrain, IconCircleCheck, IconCircleX, IconSchool, IconTarget, IconTrophy } from "@tabler/icons-react";
import { useEffect, useMemo, useState } from "react";
import { AppShellLoading } from "@/components/layout/app-shell";
import { fetchDashboard, fetchMyProgress, type AttemptResource, type DashboardResource, type ProgressResource } from "@/lib/api";
import { formatDateTime } from "@/lib/utils";

export function ProgressPage() {
  const [dashboard, setDashboard] = useState<DashboardResource | null>(null);
  const [progress, setProgress] = useState<ProgressResource[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const loadAll = async () => {
      const [dashboardRes, progressRes] = await Promise.allSettled([
        fetchDashboard(),
        fetchMyProgress(),
      ]);

      if (dashboardRes.status === "fulfilled") {
        setDashboard(dashboardRes.value);
      }

      if (progressRes.status === "fulfilled") {
        setProgress(progressRes.value);
        if (progressRes.status === "fulfilled") setError(null);
      } else {
        setError((progressRes.reason as Error)?.message ?? "Failed to load progress");
        setProgress([]);
      }

      setLoading(false);
    };
    loadAll();
  }, []);

  const totalWords = dashboard?.total_words ?? 0;
  const completedLessons = dashboard?.completed_lessons ?? 0;
  const totalLessons = dashboard?.total_lessons ?? 0;
  const recentAttempts = dashboard?.recent_attempts ?? [];

  const overallPct = totalLessons > 0 ? Math.round((completedLessons / totalLessons) * 100) : 0;

  function accuracyColor(pct: number) {
    if (pct >= 80) return "text-primary font-bold";
    if (pct >= 60) return "text-secondary-foreground font-bold";
    return "text-destructive font-bold";
  }

  if (loading) return <AppShellLoading label="Loading progress..." />;

  return (
    <div className="space-y-8">
      {/* Overview Stats */}
      <section>
        <h2 className="mb-6 font-display text-[24px] font-bold text-foreground">Overview</h2>
        <div className="grid grid-cols-2 gap-6 md:grid-cols-4">
          <div className="flex flex-col gap-2 rounded-xl border-2 border-border bg-white p-6">
            <IconBooks className="h-6 w-6 text-muted-foreground" />
            <p className="font-display text-[32px] font-black leading-none text-primary">{totalWords}</p>
            <p className="font-display text-[15px] font-bold uppercase tracking-widest text-muted-foreground">Total Words</p>
          </div>
          <div className="flex flex-col gap-2 rounded-xl border-2 border-border bg-white p-6">
            <IconAward className="h-6 w-6 text-primary" />
            <p className="font-display text-[32px] font-black leading-none text-primary">{completedLessons}</p>
            <p className="font-display text-[15px] font-bold uppercase tracking-widest text-muted-foreground">Lessons Done</p>
          </div>
          <div className="flex flex-col gap-2 rounded-xl border-2 border-border bg-white p-6">
            <IconSchool className="h-6 w-6 text-[#f4bf00]" />
            <p className="font-display text-[32px] font-black leading-none text-[#f4bf00]">{totalLessons}</p>
            <p className="font-display text-[15px] font-bold uppercase tracking-widest text-muted-foreground">Total Lessons</p>
          </div>
          <div className="flex flex-col gap-2 rounded-xl border-2 border-border bg-white p-6">
            <IconTarget className="h-6 w-6 text-muted-foreground" />
            <p className={`font-display text-[32px] font-black leading-none ${accuracyColor(overallPct)}`}>{overallPct}%</p>
            <p className="font-display text-[15px] font-bold uppercase tracking-widest text-muted-foreground">Progress</p>
          </div>
        </div>
      </section>

      {/* Bookmarks & Summary Stats */}
      <section className="grid grid-cols-2 gap-6 md:grid-cols-4">
        <div className="flex flex-col gap-2 rounded-xl bg-accent p-6">
          <IconBooks className="h-6 w-6 text-primary" />
          <p className="font-display text-[32px] font-black leading-none text-primary">{dashboard?.total_bookmarks ?? 0}</p>
          <p className="font-display text-[15px] font-bold uppercase tracking-widest text-accent-foreground">Bookmarks</p>
        </div>
        <div className="flex flex-col gap-2 rounded-xl border-2 border-border bg-white p-6">
          <IconBrain className="h-6 w-6 text-muted-foreground" />
          <p className="font-display text-[32px] font-black leading-none text-foreground">{recentAttempts.length}</p>
          <p className="font-display text-[15px] font-bold uppercase tracking-widest text-muted-foreground">Recent Quizzes</p>
        </div>
        <div className="flex flex-col gap-2 rounded-xl bg-[#ffdf92] p-6">
          <IconTrophy className="h-6 w-6 text-secondary-foreground" />
          <p className="font-display text-[32px] font-black leading-none text-secondary-foreground">
            {progress.length > 0
              ? Math.round(
                  progress.reduce((sum, p) => sum + (p.progress_percent ?? 0), 0) / progress.length
                )
              : 0}
            %
          </p>
          <p className="font-display text-[15px] font-bold uppercase tracking-widest text-secondary-foreground">Avg Score</p>
        </div>
        <div className="flex flex-col gap-2 rounded-xl bg-[#ffdad6] p-6">
          <IconCircleX className="h-6 w-6 text-destructive" />
          <p className="font-display text-[32px] font-black leading-none text-destructive">{totalLessons - completedLessons}</p>
          <p className="font-display text-[15px] font-bold uppercase tracking-widest text-[#93000a]">Remaining</p>
        </div>
      </section>

      {/* Course Progress + Recent Quiz Sessions */}
      <section className="grid gap-6 md:grid-cols-2">
        {/* Course Progress */}
        <div className="rounded-xl border-2 border-border bg-white p-5">
          <h3 className="mb-4 font-display text-[17px] font-bold text-foreground">
            Course Progress
          </h3>
          {error ? (
            <div>
              <p className="font-body text-[14px] text-destructive font-semibold">⚠ {error}</p>
              <button
                type="button"
                onClick={() => { window.location.reload(); }}
                className="mt-3 rounded-xl bg-primary px-4 py-2 text-sm font-bold text-primary-foreground"
              >
                Retry
              </button>
            </div>
          ) : progress.length === 0 ? (
            <p className="font-body text-[15px] text-muted-foreground">No courses started yet.</p>
          ) : (
            <div className="flex flex-col gap-3">
              {progress.map((p) => {
                const pct = p.progress_percent ?? 0;
                return (
                  <div key={p.id} className="flex flex-col gap-1">
                    <div className="flex items-center justify-between">
                      <span className="font-display text-[15px] font-bold text-foreground">
                        {p.course?.title ?? `Course #${p.id}`}
                      </span>
                      <span className="font-display text-[13px] font-bold text-muted-foreground">
                        {p.completed_lessons ?? 0}/{p.total_lessons ?? 0} ({pct}%)
                      </span>
                    </div>
                    <div className="h-2.5 overflow-hidden rounded-full bg-muted">
                      <div
                        className="h-full rounded-full bg-primary transition-[width] duration-500"
                        style={{ width: `${pct}%` }}
                      />
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>

        {/* Recent Quiz Sessions */}
        <div className="rounded-xl border-2 border-border bg-white p-5">
          <h3 className="mb-4 font-display text-[17px] font-bold text-foreground">
            Recent Quiz Sessions
          </h3>
          {recentAttempts.length === 0 ? (
            <p className="font-body text-[15px] text-muted-foreground">No quiz sessions yet.</p>
          ) : (
            <div className="flex flex-col gap-2">
              {recentAttempts.map((a: AttemptResource) => {
                const pct =
                  a.score != null
                    ? a.score
                    : a.total_questions && a.correct_answers != null
                      ? Math.round((a.correct_answers / a.total_questions) * 100)
                      : 0;
                return (
                  <div
                    key={a.id}
                    className="flex items-center justify-between rounded-xl border border-muted bg-white px-4 py-3"
                  >
                    <div className="flex flex-col gap-0.5">
                      <span className="font-display text-[15px] font-bold text-foreground">
                        {a.correct_answers ?? 0} / {a.total_questions ?? 0} correct
                      </span>
                      <span className="font-body text-[13px] text-muted-foreground">
                        {formatDateTime(a.created_at ?? a.completed_at)}
                      </span>
                    </div>
                    <span
                      className={`font-display text-[20px] font-black ${
                        pct >= 80
                          ? "text-primary"
                          : pct >= 60
                            ? "text-secondary-foreground"
                            : "text-destructive"
                      }`}
                    >
                      {pct}%
                    </span>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </section>
    </div>
  );
}