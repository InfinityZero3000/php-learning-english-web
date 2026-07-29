"use client";

import { useEffect, useState } from "react";
import { AppShellLoading } from "@/components/layout/app-shell";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { api, type CourseProgress, type ListeningSession, type SupervisedDashboard } from "@/lib/api";

export function ProgressPage() {
  const [dashboard, setDashboard] = useState<SupervisedDashboard>();
  const [courses, setCourses] = useState<CourseProgress[]>([]);
  const [listening, setListening] = useState<ListeningSession[]>([]);
  const [state, setState] = useState<"loading" | "ready" | "error">("loading");
  const [message, setMessage] = useState("");

  async function load() {
    setState("loading"); setMessage("");
    try {
      const [summary, enrollments, listeningSessions] = await Promise.all([
        api.supervisedDashboard(), api.enrollments(), api.listeningProgress().catch(() => []),
      ]);
      const progress = await Promise.all(enrollments.map((item) => api.courseProgress(item.course_id)));
      setDashboard(summary); setCourses(progress); setListening(listeningSessions); setState("ready");
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Không thể tải tiến độ học."); setState("error");
    }
  }

  useEffect(() => { void load(); }, []);
  if (state === "loading") return <AppShellLoading label="Đang tải tiến độ…" />;
  if (state === "error") return <Card className="mx-auto max-w-xl p-8 text-center"><p role="alert" className="font-bold">{message}</p><Button className="mt-5" onClick={load}>Thử lại</Button></Card>;

  return <div className="space-y-8">
    <header><p className="font-bold uppercase tracking-wider text-primary">Learning journey · Hành trình học</p><h2 className="font-display text-4xl font-bold">Progress</h2></header>
    <section aria-labelledby="overview-title"><h3 id="overview-title" className="mb-4 font-display text-2xl font-bold">Tổng quan</h3><div className="grid gap-4 sm:grid-cols-3">
      {[["Lesson hoàn thành", dashboard?.overview.completed_lessons ?? 0], ["Quiz đã làm", dashboard?.overview.quiz_attempts ?? 0], ["Điểm quiz trung bình", `${dashboard?.overview.average_score ?? 0}%`]].map(([label, value]) => <Card key={label} className="p-6"><p className="text-sm font-bold text-muted-foreground">{label}</p><p className="mt-2 font-display text-4xl font-black tabular-nums text-primary">{value}</p></Card>)}
    </div></section>
    <section aria-labelledby="course-progress-title"><h3 id="course-progress-title" className="mb-4 font-display text-2xl font-bold">Course progress · Tiến độ khóa học</h3><div className="grid gap-4 md:grid-cols-2">
      {courses.map((item) => <Card key={item.course.id} className="p-5"><div className="flex min-w-0 justify-between gap-4"><h4 className="truncate font-bold">{item.course.title}</h4><span className="font-bold tabular-nums text-primary">{item.progress_percent}%</span></div><div role="progressbar" aria-label={`Tiến độ ${item.course.title}`} aria-valuenow={item.progress_percent} aria-valuemin={0} aria-valuemax={100} className="mt-3 h-2 overflow-hidden rounded-full bg-muted"><div className="h-full rounded-full bg-primary" style={{ width: `${item.progress_percent}%` }} /></div><p className="mt-2 text-sm text-muted-foreground">{item.completed_lessons}/{item.total_lessons} lesson</p></Card>)}
      {courses.length === 0 && <p className="rounded-xl bg-muted p-5 text-muted-foreground">Chưa có khóa học đang theo học.</p>}
    </div></section>
    <section aria-labelledby="listening-progress-title"><h3 id="listening-progress-title" className="mb-4 font-display text-2xl font-bold">Listening · Luyện nghe</h3><div className="grid gap-4 md:grid-cols-2">
      {listening.map((item) => <Card key={item.id} className="p-5"><div className="flex min-w-0 justify-between gap-4"><h4 className="line-clamp-1 font-bold">{item.title}</h4><span className="font-bold tabular-nums text-primary">{item.watched_percentage}%</span></div><p className="mt-2 text-sm text-muted-foreground">{item.shadowing_attempts} shadowing · điểm tốt nhất {item.best_pronunciation_score == null ? "—" : Math.round(item.best_pronunciation_score)}</p></Card>)}
      {listening.length === 0 && <p className="rounded-xl bg-muted p-5 text-muted-foreground">Chưa có lesson nghe.</p>}
    </div></section>
  </div>;
}
