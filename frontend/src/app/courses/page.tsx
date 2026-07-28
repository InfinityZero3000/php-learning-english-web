"use client";

import { useEffect, useState } from "react";
import { IconBook2, IconClock, IconLayersLinked } from "@tabler/icons-react";
import { api, type CourseCard, type Enrollment } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";

export default function CoursesPage() {
  const [courses, setCourses] = useState<CourseCard[]>([]);
  const [enrollments, setEnrollments] = useState<Enrollment[]>([]);
  const [busy, setBusy] = useState<number>();
  useEffect(() => { Promise.all([api.catalogCourses(), api.enrollments()]).then(([catalog, mine]) => { setCourses(catalog.data); setEnrollments(mine); }); }, []);

  async function choose(course: CourseCard) {
    setBusy(course.id);
    try {
      const enrollment = await api.enroll(course.id);
      setEnrollments((current) => current.some((item) => item.id === enrollment.id) ? current : [...current, enrollment]);
    } finally { setBusy(undefined); }
  }

  return (
    <div className="mx-auto max-w-6xl space-y-7">
      <div><p className="font-bold uppercase tracking-[.14em] text-primary">Nội dung từ LexiLingo</p><h2 className="font-display text-4xl font-bold">Chọn con đường phù hợp</h2><p className="mt-2 text-muted-foreground">Nội dung được lưu cục bộ; tiến độ và lịch ôn thuộc tài khoản của bạn.</p></div>
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
              <Button className="mt-auto w-full" variant={enrolled ? "outline" : "default"} disabled={busy === course.id || enrolled} onClick={() => choose(course)}>{enrolled ? "Đã tham gia" : busy === course.id ? "Đang lưu…" : "Tham gia khóa học"}</Button>
            </div>
          </Card>;
        })}
      </div>
    </div>
  );
}
