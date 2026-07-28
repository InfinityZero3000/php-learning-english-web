"use client";

import { use, useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { IconArrowLeft, IconCircleCheck, IconLock } from "@tabler/icons-react";
import { api, type CourseDetail, type CourseProgress, type Enrollment, type LessonCard } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";

export default function CourseDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const courseId = Number(use(params).id);
  const [course, setCourse] = useState<CourseDetail>();
  const [lessons, setLessons] = useState<LessonCard[]>([]);
  const [progress, setProgress] = useState<CourseProgress>();
  const [enrollment, setEnrollment] = useState<Enrollment>();
  const [state, setState] = useState<"loading" | "ready" | "error">("loading");
  const [message, setMessage] = useState("");

  const load = useCallback(async () => {
    setState("loading");
    try {
      const [detail, lessonPage, mine] = await Promise.all([
        api.catalogCourse(courseId), api.catalogCourseLessons(courseId), api.enrollments(),
      ]);
      setCourse(detail);
      setLessons(lessonPage.data);
      const current = mine.find((item) => item.course_id === courseId);
      setEnrollment(current);
      if (current) setProgress(await api.courseProgress(courseId));
      setState("ready");
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : "Không thể tải lộ trình.");
      setState("error");
    }
  }, [courseId]);

  useEffect(() => { void load(); }, [load]);

  async function enroll() {
    try {
      setEnrollment(await api.enroll(courseId));
      setProgress(await api.courseProgress(courseId));
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : "Không thể tham gia khóa học.");
    }
  }

  async function start() {
    if (!enrollment) return;
    try {
      const session = await api.startSession(enrollment.id);
      window.location.href = `/session/${session.id}`;
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : "Không thể mở phiên học.");
    }
  }

  if (state === "loading") return <Card className="mx-auto max-w-4xl p-10 text-center"><p aria-live="polite" className="font-bold">Đang tải lộ trình…</p></Card>;
  if (state === "error" || !course) return <Card className="mx-auto max-w-4xl p-10 text-center"><p role="alert" className="font-bold">{message || "Không tìm thấy khóa học."}</p><Button className="mt-5" onClick={load}>Thử lại</Button></Card>;

  const completed = progress?.completed_lessons ?? 0;
  return <div className="mx-auto max-w-5xl space-y-7">
    <Link href="/courses" className="inline-flex items-center gap-2 font-bold text-primary hover:underline"><IconArrowLeft className="h-5 w-5" />Danh sách khóa học</Link>
    <header className="rounded-3xl bg-gradient-to-br from-primary to-cyan-700 p-8 text-white shadow-xl">
      <p className="text-sm font-bold uppercase tracking-[.14em]">{course.level?.name ?? "Course path"}</p>
      <h2 className="mt-3 font-display text-4xl font-bold text-balance">{course.title}</h2>
      <p className="mt-3 max-w-3xl text-pretty text-white/85">{course.description || "Lộ trình học có giám sát và ôn tập FSRS."}</p>
      <div className="mt-6 flex flex-wrap items-center gap-3"><span className="rounded-full bg-white/15 px-4 py-2 font-bold">{completed}/{progress?.total_lessons ?? lessons.length} lesson</span><span className="rounded-full bg-white/15 px-4 py-2 font-bold">{progress?.progress_percent ?? 0}% hoàn thành</span></div>
    </header>
    {message && <p role="alert" className="rounded-xl bg-red-50 p-4 text-red-800">{message}</p>}
    <div className="flex justify-end">{enrollment ? <Button onClick={start}>Học lesson tiếp theo</Button> : <Button onClick={enroll}>Tham gia khóa học</Button>}</div>
    <section className="space-y-3" aria-labelledby="lesson-path-title">
      <h3 id="lesson-path-title" className="font-display text-2xl font-bold">Lesson path</h3>
      {lessons.map((lesson, index) => {
        const done = index < completed;
        const available = Boolean(enrollment) && index <= completed;
        return <article key={lesson.id} className={`flex items-center gap-4 rounded-2xl border-2 p-5 ${available ? "border-primary/30 bg-card" : "border-border bg-muted/60"}`}>
          <span aria-hidden="true" className={`grid h-11 w-11 shrink-0 place-items-center rounded-full ${done ? "bg-emerald-100 text-emerald-700" : available ? "bg-accent text-primary" : "bg-muted text-muted-foreground"}`}>{done ? <IconCircleCheck /> : available ? index + 1 : <IconLock />}</span>
          <div className="min-w-0"><h4 className="truncate font-display text-lg font-bold">{lesson.title}</h4><p className="text-sm text-muted-foreground">{lesson.vocabularies_count ?? 0} từ · {lesson.estimated_minutes ?? 0} phút</p></div>
        </article>;
      })}
      {lessons.length === 0 && <Card className="p-8 text-center"><p>Khóa học chưa có lesson đã xuất bản.</p></Card>}
    </section>
  </div>;
}
