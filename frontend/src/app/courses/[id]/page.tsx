"use client";

import { use, useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import {
  IconArrowLeft,
  IconBook2,
  IconCircleCheck,
  IconClock,
  IconLock,
  IconPlayerPlay,
  IconRefresh,
  IconSparkles,
  IconTrophy,
} from "@tabler/icons-react";
import { api, lessonQuizzes, type LessonQuizSummary } from "@/lib/api";
import type { CourseLearningPath, CoursePathLesson } from "@/types/api";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";

export default function CourseDetailPage({ params }: { params: Promise<{ id: string }> }) {
  return <CoursePathView courseId={Number(use(params).id)} />;
}

export function CoursePathView({ courseId }: { courseId: number }) {
  const router = useRouter();
  const [path, setPath] = useState<CourseLearningPath>();
  const [quizzes, setQuizzes] = useState<Record<number, LessonQuizSummary[]>>({});
  const [state, setState] = useState<"loading" | "ready" | "error">("loading");
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    setState("loading");
    setMessage("");
    try {
      const next = await api.coursePath(courseId);
      setPath(next);
      const visibleLessons = next.enrollment
        ? next.units.flatMap((unit) => unit.lessons).filter((lesson) => lesson.status !== "locked")
        : [];
      const quizEntries = await Promise.all(visibleLessons.map(async (lesson) => [
        lesson.id,
        await lessonQuizzes.list(lesson.id).catch(() => []),
      ] as const));
      setQuizzes(Object.fromEntries(quizEntries));
      setState("ready");
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : "Không thể tải lộ trình.");
      setState("error");
    }
  }, [courseId]);

  useEffect(() => { void load(); }, [load]);

  async function enroll() {
    setBusy(true);
    setMessage("");
    try {
      await api.enroll(courseId);
      await load();
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : "Không thể tham gia khóa học.");
    } finally {
      setBusy(false);
    }
  }

  async function continueLearning() {
    if (!path?.next_action) return;
    if (path.next_action.type === "resume_session") {
      router.push(`/session/${path.next_action.session_id}`);
      return;
    }

    setBusy(true);
    setMessage("");
    try {
      const session = await api.startSession(path.next_action.enrollment_id, path.next_action.lesson_id);
      router.push(`/session/${session.id}`);
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : "Không thể mở phiên học.");
    } finally {
      setBusy(false);
    }
  }

  if (state === "loading") {
    return <Card className="mx-auto max-w-5xl overflow-hidden p-0">
      <div className="h-2 animate-pulse bg-gradient-to-r from-primary via-cyan-400 to-secondary" />
      <div className="grid min-h-64 place-items-center p-10"><div className="text-center"><IconBook2 aria-hidden="true" className="mx-auto h-10 w-10 text-primary" /><p aria-live="polite" className="mt-4 font-display text-lg font-bold">Đang tải lộ trình…</p></div></div>
    </Card>;
  }

  if (state === "error" || !path) {
    return <Card className="mx-auto max-w-4xl p-10 text-center"><IconRefresh aria-hidden="true" className="mx-auto h-10 w-10 text-primary" /><p role="alert" className="mt-4 font-bold">{message || "Không tìm thấy khóa học."}</p><Button className="mt-5" onClick={load}>Thử lại</Button></Card>;
  }

  const action = path.next_action;
  const completed = path.enrollment?.status === "completed";
  const actionLabel = action?.type === "resume_session"
    ? "Tiếp tục phiên đang học"
    : action?.type === "start_lesson"
      ? "Tiếp tục học"
      : completed
        ? "Đã hoàn thành lộ trình"
        : path.enrollment
          ? "Chưa có bài khả dụng"
        : "Tham gia khóa học";

  return <div className="mx-auto max-w-6xl space-y-8 pb-10">
    <Link href="/courses" className="inline-flex items-center gap-2 rounded-lg font-bold text-primary outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-4"><IconArrowLeft className="h-5 w-5" />Danh sách khóa học</Link>

    <header className="relative isolate overflow-hidden rounded-[2rem] border-2 border-primary/20 bg-[#063f5b] px-6 py-8 text-white shadow-lifted sm:px-10 sm:py-11">
      <div aria-hidden="true" className="absolute -right-16 -top-20 -z-10 h-64 w-64 rounded-full bg-cyan-300/25 blur-2xl" />
      <div aria-hidden="true" className="absolute -bottom-24 left-1/3 -z-10 h-56 w-56 rounded-full bg-secondary/25 blur-3xl" />
      <div className="grid items-end gap-8 lg:grid-cols-[1fr_18rem]">
        <div>
          <p className="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-black uppercase tracking-[.18em]"><IconSparkles className="h-4 w-4" />Editorial learning path</p>
          <h1 className="mt-5 max-w-3xl font-display text-4xl font-bold leading-tight text-balance sm:text-5xl">{path.course.title}</h1>
          <p className="mt-4 max-w-3xl text-base leading-7 text-white/80 text-pretty">{path.course.description || "Một lộ trình rõ ràng từ bài đầu tiên đến khả năng sử dụng tiếng Anh tự tin."}</p>
          <div className="mt-6 flex flex-wrap gap-2 text-sm font-bold">
            <span className="rounded-full bg-white/10 px-4 py-2">{path.course.estimated_duration ?? 0} phút</span>
            <span className="rounded-full bg-white/10 px-4 py-2">{path.course.total_xp ?? 0} XP</span>
            <span className="rounded-full bg-white/10 px-4 py-2">{path.progress.total_lessons} bài học</span>
          </div>
        </div>
        <div className="rounded-3xl border border-white/20 bg-white/10 p-5 backdrop-blur-sm">
          <div className="flex items-end justify-between gap-4"><div><p className="text-xs font-black uppercase tracking-[.16em] text-white/65">Tiến độ tổng</p><p className="mt-1 text-5xl font-black tabular-nums">{path.progress.percentage}%</p></div><IconTrophy aria-hidden="true" className="h-12 w-12 text-secondary" /></div>
          <Progress value={path.progress.percentage} className="mt-5 bg-white/15 [&>div]:bg-secondary" />
          <p className="mt-3 text-sm font-bold text-white/75">{path.progress.completed_lessons}/{path.progress.total_lessons} bài đã hoàn thành</p>
        </div>
      </div>
    </header>

    {message && <p role="alert" className="rounded-2xl border border-red-200 bg-red-50 p-4 font-bold text-red-800">{message}</p>}

    <section className="grid gap-5 rounded-3xl border-2 border-border bg-card p-5 shadow-sm lg:grid-cols-[1fr_auto] lg:items-center lg:p-7" aria-label="Hành động tiếp theo">
      <div><p className="text-xs font-black uppercase tracking-[.18em] text-primary">Bước tiếp theo</p><h2 className="mt-2 font-display text-2xl font-bold">{action?.type === "resume_session" ? "Tiếp tục đúng nơi bạn dừng lại" : action?.type === "start_lesson" ? "Bài phù hợp tiếp theo đã sẵn sàng" : completed ? "Bạn đã đi hết lộ trình hiện tại" : path.enrollment ? "Hiện chưa có bài học khả dụng" : "Bắt đầu lộ trình của riêng bạn"}</h2><p className="mt-1 text-muted-foreground">{action?.lesson_id ? `Server đã chọn bài #${action.lesson_id} theo tiến độ và prerequisite của bạn.` : path.enrollment && !completed ? "Hoàn thành prerequisite còn thiếu hoặc thử lại khi nội dung mới được xuất bản." : "Tiến độ sẽ được lưu theo từng bài học."}</p></div>
      <Button size="lg" disabled={busy || Boolean(path.enrollment && !action)} onClick={path.enrollment ? continueLearning : enroll}><IconPlayerPlay className="h-5 w-5" />{busy ? "Đang xử lý…" : actionLabel}</Button>
    </section>

    <section aria-labelledby="course-units-title">
      <div className="mb-5 flex flex-wrap items-end justify-between gap-3"><div><p className="text-xs font-black uppercase tracking-[.2em] text-primary">Course map</p><h2 id="course-units-title" className="mt-1 font-display text-3xl font-bold">Các chặng học</h2></div><p className="max-w-lg text-sm text-muted-foreground">Trạng thái mở khóa do server tính từ tiến độ thật; hoàn thành prerequisite để đi tiếp.</p></div>
      {path.units.length ? <div className="space-y-6">{path.units.map((unit, unitIndex) => <article key={unit.id ?? `general-${unitIndex}`} className="overflow-hidden rounded-[1.75rem] border-2 border-border bg-card shadow-sm">
        <div className="relative flex flex-wrap items-start justify-between gap-4 border-b-2 border-border p-6">
          <span aria-hidden="true" className="absolute inset-y-0 left-0 w-2" style={{ backgroundColor: safeColor(unit.background_color) }} />
          <div className="pl-3"><p className="text-xs font-black uppercase tracking-[.18em] text-primary">Unit {unit.id === null ? "General" : String(unitIndex + 1).padStart(2, "0")}</p><h3 className="mt-1 font-display text-2xl font-bold">{unit.title}</h3><p className="mt-2 max-w-2xl text-sm text-muted-foreground">{unit.description || "A focused group of lessons in your course journey."}</p></div>
          <div className="min-w-44 rounded-2xl bg-muted p-4"><div className="flex justify-between text-xs font-black"><span>UNIT PROGRESS</span><span>{unit.completed_lessons}/{unit.total_lessons}</span></div><Progress value={unit.total_lessons ? unit.completed_lessons / unit.total_lessons * 100 : 0} className="mt-3 h-2" /></div>
        </div>
        <div className="relative divide-y divide-border px-4 sm:px-6">{unit.lessons.map((lesson, lessonIndex) => <LessonRow key={lesson.id} lesson={lesson} index={lessonIndex} quiz={quizzes[lesson.id]?.[0]} courseId={courseId} />)}</div>
      </article>)}</div> : <Card className="border-dashed p-10 text-center"><IconBook2 aria-hidden="true" className="mx-auto h-10 w-10 text-muted-foreground" /><p className="mt-4 font-bold">Khóa học chưa có bài học đã xuất bản.</p></Card>}
    </section>
  </div>;
}

function LessonRow({ lesson, index, quiz, courseId }: { lesson: CoursePathLesson; index: number; quiz?: LessonQuizSummary; courseId: number }) {
  const completed = lesson.status === "completed";
  const locked = lesson.status === "locked";
  return <article aria-label={lesson.title} className="grid gap-4 py-5 sm:grid-cols-[3rem_1fr_auto] sm:items-center">
    <span aria-hidden="true" className={`grid h-11 w-11 place-items-center rounded-2xl border-2 font-display font-black ${completed ? "border-emerald-200 bg-emerald-50 text-emerald-700" : locked ? "border-border bg-muted text-muted-foreground" : "border-primary/25 bg-accent text-primary"}`}>{completed ? <IconCircleCheck /> : locked ? <IconLock /> : index + 1}</span>
    <div>
      <div className="flex flex-wrap items-center gap-2"><h4 className="font-display text-lg font-bold">{lesson.title}</h4><Status lesson={lesson} /></div>
      <p className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground"><span className="inline-flex items-center gap-1"><IconClock className="h-4 w-4" />{lesson.estimated_minutes ?? 0} phút</span><span>{lesson.xp_reward ?? 0} XP</span></p>
      {lesson.locked_reason && <p className="mt-2 text-sm font-bold text-amber-800">{lesson.locked_reason}</p>}
    </div>
    {quiz && !locked ? <Button asChildCompat="a" variant="outline" size="sm"><Link href={`/quiz?lesson_quiz=${quiz.id}&course=${courseId}&lesson=${lesson.id}`}>Làm quiz</Link></Button> : <span aria-hidden="true" />}
  </article>;
}

function Status({ lesson }: { lesson: CoursePathLesson }) {
  const copy = lesson.status === "completed" ? "Hoàn thành" : lesson.status === "locked" ? "Đang khóa" : lesson.is_current ? "Bài hiện tại" : "Khả dụng";
  return <span className={`rounded-full px-2.5 py-1 text-[11px] font-black uppercase tracking-wider ${lesson.status === "completed" ? "bg-emerald-100 text-emerald-800" : lesson.status === "locked" ? "bg-muted text-muted-foreground" : "bg-accent text-primary"}`}>{copy}</span>;
}

function safeColor(color?: string | null) {
  return color && /^#(?:[A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/.test(color) ? color : "#1cb0f6";
}
