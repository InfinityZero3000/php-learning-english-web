"use client";

import { use, useEffect, useState } from "react";
import Link from "next/link";
import { IconCircleCheck, IconConfetti } from "@tabler/icons-react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import type { LearningSession } from "@/lib/api";

export default function SessionSummaryPage({ params }: { params: Promise<{ id: string }> }) {
  const id = use(params).id;
  const [session, setSession] = useState<LearningSession>();

  useEffect(() => {
    const stored = sessionStorage.getItem(`session-summary:${id}`);
    if (stored) {
      try { setSession(JSON.parse(stored)); } catch { sessionStorage.removeItem(`session-summary:${id}`); }
    }
  }, [id]);

  const events = Number(session?.summary?.events ?? 0);
  const completedLesson = session?.summary?.completed_lesson_id;
  return <div className="mx-auto max-w-2xl"><Card className="p-10 text-center">
    <IconConfetti aria-hidden="true" className="mx-auto h-16 w-16 text-secondary" />
    <IconCircleCheck aria-hidden="true" className="mx-auto mt-4 h-12 w-12 text-emerald-600" />
    <h2 className="mt-5 font-display text-4xl font-bold text-balance">Hoàn thành phiên học</h2>
    <p className="mt-3 text-pretty text-muted-foreground">{completedLesson ? "Lesson đã được hoàn thành và tiến độ đã lưu." : "Bài luyện giáo viên giao đã hoàn thành; tiến độ khóa học không bị thay đổi."}</p>
    <div className="mx-auto mt-7 grid max-w-sm grid-cols-2 gap-3">
      <div className="rounded-2xl bg-muted p-4"><p className="text-3xl font-bold tabular-nums">{events}</p><p className="text-xs text-muted-foreground">hoạt động</p></div>
      <div className="rounded-2xl bg-muted p-4"><p className="text-3xl font-bold">{session?.status === "completed" ? "Done" : "—"}</p><p className="text-xs text-muted-foreground">trạng thái</p></div>
    </div>
    <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row"><Button asChildCompat="a"><Link href="/review">Ôn từ đến hạn</Link></Button><Button variant="outline" asChildCompat="a"><Link href="/">Về Today</Link></Button></div>
  </Card></div>;
}
