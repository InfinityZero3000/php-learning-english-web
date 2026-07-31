"use client";

import Link from "next/link";
import {
  IconArrowRight,
  IconBook2,
  IconBrain,
  IconCheck,
  IconClock,
  IconHeadphones,
  IconSparkles,
  IconTargetArrow,
  IconTrophy,
} from "@tabler/icons-react";
import { useEffect, useState } from "react";
import { AppShellLoading } from "@/components/layout/app-shell";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { api, type SupervisedDashboard } from "@/lib/api";

const stateLabels = {
  new: "Thẻ mới",
  learning: "Đang học",
  review: "Ôn tập",
  relearning: "Học lại",
};

const stateColors = {
  new: "bg-sky-500",
  learning: "bg-amber-500",
  review: "bg-emerald-500",
  relearning: "bg-rose-500",
};

export function ProgressPage() {
  const [dashboard, setDashboard] = useState<SupervisedDashboard>();
  const [state, setState] = useState<"loading" | "ready" | "error">("loading");
  const [message, setMessage] = useState("");

  async function load() {
    setState("loading");
    setMessage("");
    try {
      setDashboard(await api.supervisedDashboard());
      setState("ready");
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Không thể tải tiến độ học.");
      setState("error");
    }
  }

  useEffect(() => { void load(); }, []);

  if (state === "loading") return <AppShellLoading label="Đang tải tiến độ…" />;
  if (state === "error" || !dashboard) {
    return <Card className="mx-auto max-w-xl p-8 text-center">
      <p role="alert" className="font-bold">{message}</p>
      <Button className="mt-5" onClick={load}>Thử lại</Button>
    </Card>;
  }

  const maxForecast = Math.max(1, ...dashboard.fsrs.forecast.map((item) => item.count));

  return <div className="space-y-8 pb-8">
    <header className="relative isolate overflow-hidden rounded-[2rem] bg-slate-950 px-6 py-8 text-white shadow-2xl sm:px-10 sm:py-10">
      <div aria-hidden="true" className="absolute -right-16 -top-24 h-72 w-72 rounded-full bg-cyan-400/20 blur-3xl" />
      <div aria-hidden="true" className="absolute -bottom-32 left-1/3 h-64 w-64 rounded-full bg-violet-500/20 blur-3xl" />
      <div className="relative grid items-end gap-8 lg:grid-cols-[1fr_auto]">
        <div className="max-w-2xl">
          <p className="flex items-center gap-2 text-xs font-black uppercase tracking-[.22em] text-cyan-300">
            <IconSparkles className="h-4 w-4" /> Learning intelligence
          </p>
          <h2 className="mt-4 font-display text-4xl font-black leading-tight sm:text-5xl">Hành trình của bạn</h2>
          <p className="mt-4 max-w-xl text-base leading-7 text-slate-300">
            Tiến độ course, nhịp ghi nhớ và hoạt động luyện tập được tính từ dữ liệu học thật của bạn.
          </p>
        </div>
        <Link
          href="/review"
          className="inline-flex h-14 items-center justify-center gap-3 rounded-2xl bg-cyan-300 px-6 font-display font-black text-slate-950 transition hover:-translate-y-0.5 hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-cyan-300"
        >
          {dashboard.fsrs.due_count > 0 ? `Ôn ${dashboard.fsrs.due_count} thẻ ngay` : "Mở chế độ ôn tập"}
          <IconArrowRight className="h-5 w-5" />
        </Link>
      </div>
    </header>

    <section aria-labelledby="memory-title" className="grid gap-5 xl:grid-cols-[1.15fr_.85fr]">
      <Card className="overflow-hidden border-0 bg-gradient-to-br from-indigo-950 via-indigo-900 to-violet-900 p-6 text-white shadow-xl sm:p-8">
        <div className="flex flex-wrap items-start justify-between gap-6">
          <div>
            <p className="text-xs font-black uppercase tracking-[.2em] text-indigo-200">Memory signal</p>
            <h3 id="memory-title" className="mt-2 font-display text-3xl font-black">Sức khỏe ghi nhớ</h3>
          </div>
          <div className="grid h-14 w-14 place-items-center rounded-2xl bg-white/10"><IconBrain className="h-7 w-7" /></div>
        </div>
        <div className="mt-8 grid gap-4 sm:grid-cols-3">
          <InsightMetric label="Retention" value={dashboard.fsrs.retention == null ? null : `${Math.round(dashboard.fsrs.retention * 100)}%`} />
          <InsightMetric label="Stability TB" value={dashboard.fsrs.average_stability == null ? null : `${dashboard.fsrs.average_stability} ngày`} />
          <InsightMetric label="Difficulty TB" value={dashboard.fsrs.average_difficulty == null ? null : dashboard.fsrs.average_difficulty} />
        </div>
        <p className="mt-5 text-sm text-indigo-200">{dashboard.fsrs.reviewed_cards} thẻ đã có lịch sử ôn để phân tích.</p>
      </Card>

      <Card className="p-6 sm:p-8">
        <div className="flex items-center justify-between gap-4">
          <div>
            <p className="text-xs font-black uppercase tracking-[.18em] text-primary">FSRS states</p>
            <h3 className="mt-1 font-display text-2xl font-black">Trạng thái thẻ</h3>
          </div>
          <IconTargetArrow className="h-7 w-7 text-primary" />
        </div>
        <div className="mt-6 grid grid-cols-2 gap-3">
          {dashboard.fsrs.state_distribution.map((item) => <div key={item.state} className="rounded-2xl border border-border/70 bg-muted/40 p-4">
            <span aria-hidden="true" className={`mb-3 block h-2 w-8 rounded-full ${stateColors[item.state]}`} />
            <p className="text-sm font-bold text-muted-foreground">{stateLabels[item.state]}</p>
            <p className="mt-1 font-display text-3xl font-black tabular-nums">{item.count}</p>
          </div>)}
        </div>
      </Card>
    </section>

    <section aria-labelledby="forecast-title">
      <div className="mb-4 flex items-end justify-between gap-4">
        <div>
          <p className="text-xs font-black uppercase tracking-[.18em] text-primary">Next seven days</p>
          <h3 id="forecast-title" className="mt-1 font-display text-2xl font-black">Lịch thẻ đến hạn</h3>
        </div>
        <p className="text-sm font-bold text-muted-foreground">UTC · do server tính</p>
      </div>
      <Card className="p-5 sm:p-7">
        <ul aria-label="Lịch thẻ đến hạn trong 7 ngày" className="grid h-52 grid-cols-7 items-end gap-2 sm:gap-4">
        {dashboard.fsrs.forecast.map((item) => {
          const label = shortDate(item.date);
          return <li key={item.date} className="flex h-full min-w-0 flex-col items-center justify-end gap-2">
            <span className="sr-only">{label}: {item.count} thẻ</span>
            <span aria-hidden="true" className="text-xs font-black tabular-nums">{item.count}</span>
            <div aria-hidden="true" className="flex h-36 w-full items-end rounded-xl bg-muted/70 p-1">
              <div className="w-full rounded-lg bg-gradient-to-t from-primary to-cyan-400 transition-[height]" style={{ height: `${Math.max(item.count ? 10 : 2, item.count / maxForecast * 100)}%` }} />
            </div>
            <span aria-hidden="true" className="text-[11px] font-bold text-muted-foreground sm:text-xs">{label}</span>
          </li>;
        })}
        </ul>
      </Card>
    </section>

    <section aria-labelledby="courses-title">
      <div className="mb-4 flex items-center gap-3">
        <div className="grid h-11 w-11 place-items-center rounded-2xl bg-primary/10 text-primary"><IconBook2 className="h-6 w-6" /></div>
        <div><p className="text-xs font-black uppercase tracking-[.18em] text-primary">Course map</p><h3 id="courses-title" className="font-display text-2xl font-black">Tiến độ khóa học</h3></div>
      </div>
      <div className="grid gap-5 lg:grid-cols-2">
        {dashboard.courses.map((course) => <Card key={course.id} className="overflow-hidden">
          <div className="border-b border-border/70 bg-muted/30 p-6">
            <div className="flex items-start justify-between gap-4">
              <div><h4 className="font-display text-2xl font-black">{course.title}</h4><p className="mt-1 text-sm text-muted-foreground">{course.completed_lessons}/{course.total_lessons} lesson hoàn thành</p></div>
              <span className="rounded-full bg-primary px-3 py-1 text-sm font-black text-primary-foreground">{course.progress_percent}%</span>
            </div>
            <div role="progressbar" aria-label={`Tiến độ ${course.title}`} aria-valuenow={course.progress_percent} aria-valuemin={0} aria-valuemax={100} className="mt-5 h-2 overflow-hidden rounded-full bg-border">
              <div className="h-full rounded-full bg-gradient-to-r from-primary to-cyan-400" style={{ width: `${course.progress_percent}%` }} />
            </div>
          </div>
          <div className="space-y-5 p-6">
            {course.units.map((unit) => <article key={unit.id ?? "general"} aria-labelledby={`unit-${course.id}-${unit.id ?? "general"}`}>
              <div className="flex items-center justify-between gap-3">
                <h5 id={`unit-${course.id}-${unit.id ?? "general"}`} className="font-display text-lg font-black">{unit.title}</h5>
                <span className="text-xs font-bold text-muted-foreground">{unit.completed_lessons}/{unit.total_lessons}</span>
              </div>
              <ul className="mt-3 space-y-2">
                {unit.lessons.map((lesson) => <li key={lesson.id} aria-label={`${lesson.title}: ${lesson.completed ? "Đã hoàn thành" : "Chưa hoàn thành"}`} className="flex items-center gap-3 rounded-xl bg-muted/50 px-3 py-2.5">
                  <span aria-hidden="true" className={`grid h-6 w-6 shrink-0 place-items-center rounded-full ${lesson.completed ? "bg-emerald-500 text-white" : "border-2 border-border bg-card text-transparent"}`}><IconCheck className="h-3.5 w-3.5" /></span>
                  <span className={lesson.completed ? "font-semibold text-muted-foreground line-through" : "font-semibold"}>{lesson.title}</span>
                </li>)}
              </ul>
            </article>)}
            <Button variant="outline" asChildCompat="a" className="w-full"><Link href={`/courses/${course.id}`}>Mở course path <IconArrowRight className="h-4 w-4" /></Link></Button>
          </div>
        </Card>)}
        {dashboard.courses.length === 0 && <EmptyState text="Chưa có khóa học đang theo học." />}
      </div>
    </section>

    <section className="grid gap-5 xl:grid-cols-3">
      <ActivityPanel icon={<IconClock />} eyebrow="Recent sessions" title="Phiên học gần đây">
        {dashboard.recent_sessions.map((session) => <div key={session.id} className="rounded-2xl bg-muted/50 p-4">
          <div className="flex items-center justify-between gap-3"><p className="font-bold">{session.lesson?.title ?? "Phiên học tổng hợp"}</p><Status status={session.status} /></div>
          <p className="mt-1 text-sm text-muted-foreground">{session.course?.title ?? "Course"} · {formatDate(session.started_at)}</p>
        </div>)}
        {dashboard.recent_sessions.length === 0 && <p className="text-sm text-muted-foreground">Chưa có phiên học gần đây.</p>}
      </ActivityPanel>

      <ActivityPanel icon={<IconTrophy />} eyebrow={`${dashboard.quiz_performance.attempts} attempts`} title="Kết quả quiz">
        <div className="mb-4 flex gap-3">
          <SmallMetric label="Điểm TB" value={dashboard.quiz_performance.average_score == null ? "—" : `${dashboard.quiz_performance.average_score}%`} />
          <SmallMetric label="Đúng" value={dashboard.quiz_performance.correct_rate == null ? "—" : `${dashboard.quiz_performance.correct_rate}%`} />
        </div>
        {dashboard.quiz_performance.recent.map((attempt) => <div key={attempt.id} className="flex items-center justify-between gap-3 rounded-2xl bg-muted/50 p-4">
          <div className="min-w-0"><p className="truncate font-bold">{attempt.quiz_title}</p><p className="truncate text-sm text-muted-foreground">{attempt.lesson_title}</p></div>
          <span className="font-display text-xl font-black text-primary">{attempt.score}%</span>
        </div>)}
        {dashboard.quiz_performance.recent.length === 0 && <p className="text-sm text-muted-foreground">Chưa có kết quả quiz.</p>}
      </ActivityPanel>

      <ActivityPanel icon={<IconHeadphones />} eyebrow={`${dashboard.listening.minutes_listened} phút nghe`} title="Listening">
        <div className="mb-4 flex gap-3">
          <SmallMetric label="Shadowing" value={dashboard.listening.shadowing_attempts} />
          <SmallMetric label="Phát âm tốt nhất" value={dashboard.listening.best_pronunciation_score == null ? "—" : Math.round(dashboard.listening.best_pronunciation_score)} />
        </div>
        {dashboard.listening.recent.map((item) => <div key={item.id} className="rounded-2xl bg-muted/50 p-4">
          <div className="flex items-center justify-between gap-3"><p className="truncate font-bold">{item.title}</p><span className="font-black text-primary">{item.watched_percentage}%</span></div>
          <p className="mt-1 text-sm text-muted-foreground">{item.shadowing_attempts} lượt shadowing</p>
        </div>)}
        {dashboard.listening.recent.length === 0 && <p className="text-sm text-muted-foreground">Chưa có bài luyện nghe.</p>}
      </ActivityPanel>
    </section>
  </div>;
}

function InsightMetric({ label, value }: { label: string; value: string | number | null }) {
  return <div className="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
    <p className="text-xs font-bold uppercase tracking-wider text-indigo-200">{label}</p>
    <p className="mt-2 font-display text-3xl font-black">{value ?? "Chưa đủ dữ liệu"}</p>
  </div>;
}

function SmallMetric({ label, value }: { label: string; value: string | number }) {
  return <div className="min-w-0 flex-1 rounded-xl border border-border/70 p-3"><p className="text-[11px] font-bold text-muted-foreground">{label}</p><p className="mt-1 font-display text-lg font-black">{value}</p></div>;
}

function ActivityPanel({ icon, eyebrow, title, children }: { icon: React.ReactNode; eyebrow: string; title: string; children: React.ReactNode }) {
  return <Card className="p-5">
    <div className="mb-5 flex items-start justify-between gap-4"><div><p className="text-xs font-black uppercase tracking-[.16em] text-primary">{eyebrow}</p><h3 className="mt-1 font-display text-xl font-black">{title}</h3></div><span className="text-primary">{icon}</span></div>
    <div className="space-y-3">{children}</div>
  </Card>;
}

function Status({ status }: { status: string }) {
  return <span className="rounded-full bg-primary/10 px-2.5 py-1 text-[11px] font-black uppercase text-primary">{status === "completed" ? "Hoàn thành" : "Đang học"}</span>;
}

function EmptyState({ text }: { text: string }) {
  return <Card className="grid min-h-44 place-items-center border-dashed p-8 text-center text-muted-foreground">{text}</Card>;
}

function shortDate(date: string) {
  const [, month, day] = date.split("-");
  return `${day}/${month}`;
}

function formatDate(date: string | null) {
  if (!date) return "Chưa bắt đầu";
  return new Intl.DateTimeFormat("vi-VN", { day: "2-digit", month: "2-digit", hour: "2-digit", minute: "2-digit" }).format(new Date(date));
}
