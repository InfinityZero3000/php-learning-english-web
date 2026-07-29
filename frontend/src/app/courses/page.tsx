"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { IconBook2, IconClock, IconLayersLinked } from "@tabler/icons-react";
import { api, type CourseCard, type Enrollment } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";

export default function CoursesPage() {
  const [courses, setCourses] = useState<CourseCard[]>([]);
  const [enrollments, setEnrollments] = useState<Enrollment[]>([]);
  const [busy, setBusy] = useState<number>();
  const [state, setState] = useState<"loading" | "ready" | "error">("loading");
  const [message, setMessage] = useState("");
  async function load() {
    setState("loading");
    try {
      const [catalog, mine] = await Promise.all([api.catalogCourses(), api.enrollments()]);
      setCourses(catalog.data); setEnrollments(mine); setState("ready");
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : "Không thể tải khóa học.");
      setState("error");
    }
  }
  useEffect(() => { void load(); }, []);

  async function choose(course: CourseCard) {
    setBusy(course.id);
    try {
      const enrollment = await api.enroll(course.id);
      setEnrollments((current) => current.some((item) => item.id === enrollment.id) ? current : [...current, enrollment]);
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : "Không thể tham gia khóa học.");
    } finally { setBusy(undefined); }
  }

  return (
    <div className="mx-auto max-w-6xl space-y-7">
      <div><p className="font-bold uppercase tracking-[.14em] text-primary">Nội dung từ LexiLingo</p><h2 className="font-display text-4xl font-bold">Chọn con đường phù hợp</h2><p className="mt-2 text-muted-foreground">Nội dung được lưu cục bộ; tiến độ và lịch ôn thuộc tài khoản của bạn.</p></div>
      <Card className="flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between"><div><p className="font-bold text-primary">Listening practice · Luyện nghe</p><p className="text-sm text-muted-foreground">Bổ sung video có caption vào lộ trình hôm nay.</p></div><Button asChildCompat="a"><Link href="/listening">Khám phá</Link></Button></Card>
      {message && <p role="alert" className="rounded-xl border-2 border-red-200 bg-red-50 p-4 text-red-800">{message}</p>}
      {state === "loading" && <Card className="p-10 text-center"><p aria-live="polite" className="font-bold">Đang tải khóa học…</p></Card>}
      {state === "error" && <Button onClick={load}>Thử lại</Button>}
      {state === "ready" && courses.length === 0 && <Card className="p-10 text-center"><p className="font-bold">Chưa có khóa học đã xuất bản.</p></Card>}
      <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        {courses.map((course) => {
          const enrolled = enrollments.some((item) => item.course_id === course.id);
          return <Card key={course.id} className="flex min-h-72 flex-col overflow-hidden">
            <div className="h-2 bg-gradient-to-r from-primary to-cyan-400" />
            <div className="flex flex-1 flex-col p-6">
              <span className="mb-5 grid h-12 w-12 place-items-center rounded-2xl bg-accent text-primary"><IconBook2 /></span>
              <h3 className="font-display text-2xl font-bold">{course.title}</h3>
              <p className="mt-2 line-clamp-3 text-sm text-muted-foreground">{course.description || "Khóa học thực hành theo lộ trình."}</p>
              <div className="mt-5 flex gap-4 text-xs font-bold text-muted-foreground"><span><IconLayersLinked className="mr-1 inline h-4 w-4" />{course.lessons_count ?? 0} bài</span><span><IconClock className="mr-1 inline h-4 w-4" />{course.estimated_duration ?? 0} phút</span></div>
              <div className="mt-auto grid gap-2 pt-5">
                <Button variant="outline" asChildCompat="a"><Link href={`/courses/${course.id}`}>Xem lộ trình</Link></Button>
                <Button className="w-full" disabled={busy === course.id || enrolled} onClick={() => choose(course)}>{enrolled ? "Đã tham gia" : busy === course.id ? "Đang lưu…" : "Tham gia khóa học"}</Button>
              </div>
            </div>
          </Card>;
        })}
      </div>
    </div>
  );
}
