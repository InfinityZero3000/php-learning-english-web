"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { IconArrowRight, IconBook2, IconBrain, IconClock, IconSchool } from "@tabler/icons-react";
import { api, ApiError, type Enrollment, type LearningPlan } from "@/lib/api";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";

function message(reason: unknown) {
  if (reason instanceof ApiError && reason.status === 401) return "Hãy đăng nhập để xem kế hoạch học tập hôm nay.";
  return reason instanceof Error ? reason.message : "Không thể tải kế hoạch hôm nay.";
}

export function TodayPage() {
  const [plan, setPlan] = useState<LearningPlan | null>(null);
  const [enrollments, setEnrollments] = useState<Enrollment[]>([]);
  const [error, setError] = useState("");

  useEffect(() => {
    Promise.all([api.learningPlan(), api.enrollments()])
      .then(([nextPlan, nextEnrollments]) => { setPlan(nextPlan); setEnrollments(nextEnrollments); })
      .catch((reason) => setError(message(reason)));
  }, []);

  const counts = (type: string) => plan?.items.filter((item) => item.type === type).length ?? 0;
  return (
    <div className="mx-auto max-w-6xl space-y-8">
      <section className="overflow-hidden rounded-3xl border-2 border-primary/20 bg-gradient-to-br from-[#006590] to-[#0891b2] p-7 text-white shadow-xl md:p-10">
        <p className="font-display text-sm font-bold uppercase tracking-[0.14em] text-cyan-100">Kế hoạch hôm nay</p>
        <h2 className="mt-3 max-w-2xl font-display text-3xl font-bold leading-tight md:text-5xl">Một phiên tập trung, tiến bộ rõ ràng.</h2>
        <div className="mt-7 flex flex-wrap gap-3 text-sm font-bold">
          <span className="rounded-full bg-white/15 px-4 py-2"><IconClock className="mr-2 inline h-5 w-5" />~{Math.max(5, (plan?.items.length ?? 0) * 3)} phút</span>
          <span className="rounded-full bg-white/15 px-4 py-2">{plan?.items.length ?? "…"} hoạt động</span>
        </div>
      </section>
      {error && <p role="alert" className="rounded-xl border-2 border-red-200 bg-red-50 p-4 text-red-800">{error}</p>}
      <section className="grid gap-4 md:grid-cols-3">
        <Metric icon={IconSchool} label="Giáo viên giao" value={counts("teacher_lesson") + counts("teacher_vocabulary_practice")} tone="bg-amber-100 text-amber-800" />
        <Metric icon={IconBrain} label="FSRS đến hạn" value={counts("fsrs_review")} tone="bg-cyan-100 text-cyan-800" />
        <Metric icon={IconBook2} label="Bài tiếp theo" value={counts("course_activity")} tone="bg-emerald-100 text-emerald-800" />
      </section>
      <Card className="flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between"><div><p className="font-bold uppercase tracking-wider text-primary">Listening practice · Luyện nghe</p><h3 className="font-display text-2xl font-bold">Học với video có caption</h3><p className="text-sm text-muted-foreground">Nghe chủ động, dịch từng câu và luyện shadowing.</p></div><Button asChildCompat="a"><Link href="/listening">Khám phá video</Link></Button></Card>
      {(plan?.items.length ?? 0) > 0 && (
        <Card>
          <CardHeader><CardTitle>Ưu tiên hôm nay</CardTitle></CardHeader>
          <CardContent className="space-y-3">
            {plan?.items.map((item) => <PlanItem key={`${item.type}-${item.id}`} item={item} onError={setError} />)}
          </CardContent>
        </Card>
      )}
      <Card>
        <CardHeader><CardTitle>Lộ trình đang học</CardTitle></CardHeader>
        <CardContent className="space-y-3">
          {enrollments.length === 0 ? (
            <div className="rounded-2xl bg-muted p-6 text-center">
              <p className="font-bold">Bạn chưa tham gia khóa học nào.</p>
              <Button asChildCompat="a" className="mt-4"><Link href="/courses">Khám phá khóa học</Link></Button>
            </div>
          ) : enrollments.map((item) => (
            <div key={item.id} className="flex flex-col gap-4 rounded-2xl border-2 border-border p-5 sm:flex-row sm:items-center sm:justify-between">
              <div><p className="text-xs font-bold uppercase tracking-wider text-primary">{item.status}</p><h3 className="font-display text-xl font-bold">{item.title}</h3></div>
              <StartButton enrollment={item} onError={setError} />
            </div>
          ))}
        </CardContent>
      </Card>
    </div>
  );
}

function PlanItem({ item, onError }: { item: LearningPlan["items"][number]; onError: (message: string) => void }) {
  const [busy, setBusy] = useState(false);
  const labels = {
    teacher_lesson: "Bài học giáo viên giao",
    teacher_vocabulary_practice: "Luyện từ vựng giáo viên giao",
    fsrs_review: "Ôn tập FSRS",
    course_activity: "Bài tiếp theo trong khóa học",
    pronunciation_task: "Luyện phát âm trong lesson tiếp theo",
    remediation: "Ôn lại nội dung đang yếu",
  };
  if (item.type === "fsrs_review" || item.type === "remediation") {
    return <div className="flex items-center justify-between rounded-2xl border-2 border-border p-4"><span className="font-bold">{labels[item.type]}</span><Button asChildCompat="a"><Link href="/review">Bắt đầu</Link></Button></div>;
  }
  async function start() {
    setBusy(true);
    try {
      const session = item.type === "course_activity" || item.type === "pronunciation_task"
        ? await api.startSession(item.id)
        : await api.startAssignment(item.id);
      window.location.href = `/session/${session.id}`;
    } catch (reason) {
      onError(reason instanceof Error ? reason.message : "Không thể mở hoạt động.");
    }
    finally { setBusy(false); }
  }
  return <div className="flex items-center justify-between rounded-2xl border-2 border-border p-4"><span className="font-bold">{labels[item.type]}</span><Button onClick={start} disabled={busy}>{busy ? "Đang mở…" : "Bắt đầu"}</Button></div>;
}

function Metric({ icon: Icon, label, value, tone }: { icon: typeof IconBrain; label: string; value: number; tone: string }) {
  return <Card className="p-5"><div className="flex items-center gap-4"><span className={`rounded-2xl p-3 ${tone}`}><Icon className="h-7 w-7" /></span><div><p className="text-3xl font-bold">{value}</p><p className="text-sm text-muted-foreground">{label}</p></div></div></Card>;
}

function StartButton({ enrollment, onError }: { enrollment: Enrollment; onError: (message: string) => void }) {
  const [busy, setBusy] = useState(false);
  async function start() {
    setBusy(true);
    try { const session = await api.startSession(enrollment.id); window.location.href = `/session/${session.id}`; }
    catch (reason) { onError(reason instanceof Error ? reason.message : "Không thể mở phiên học."); }
    finally { setBusy(false); }
  }
  return <Button onClick={start} disabled={busy}>{busy ? "Đang mở…" : "Học tiếp"}<IconArrowRight className="h-5 w-5" /></Button>;
}
