"use client";

import { useEffect, useState } from "react";
import { IconAlertTriangle, IconChecklist, IconUsers } from "@tabler/icons-react";
import { api, type SupervisionAlert, type TeacherAssignment, type TeacherLearner } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

export default function TeacherPage() {
  const [learners, setLearners] = useState<TeacherLearner[]>([]);
  const [alerts, setAlerts] = useState<SupervisionAlert[]>([]);
  const [assignments, setAssignments] = useState<TeacherAssignment[]>([]);
  useEffect(() => { Promise.all([api.teacherLearners(), api.teacherAlerts(), api.teacherAssignments()]).then(([l, a, work]) => { setLearners(l); setAlerts(a); setAssignments(work); }); }, []);

  async function resolve(alert: SupervisionAlert) {
    const updated = await api.resolveAlert(alert.id, "teacher_reviewed", "Đã xem bằng chứng và lên kế hoạch hỗ trợ.");
    setAlerts((items) => items.map((item) => item.id === updated.id ? updated : item));
  }

  return <div className="mx-auto max-w-7xl space-y-7">
    <div><p className="font-bold uppercase tracking-[.14em] text-primary">Supervised learning</p><h2 className="font-display text-4xl font-bold">Teacher workspace</h2><p className="mt-2 text-muted-foreground">Chỉ hiển thị học viên đã được phân công và bằng chứng học tập cần thiết.</p></div>
    <div className="grid gap-4 md:grid-cols-3">
      <Metric icon={IconUsers} value={learners.length} label="Học viên" />
      <Metric icon={IconAlertTriangle} value={alerts.filter((item) => item.state === "open").length} label="Cảnh báo mở" />
      <Metric icon={IconChecklist} value={assignments.filter((item) => item.status !== "completed").length} label="Bài đang giao" />
    </div>
    <div className="grid gap-6 xl:grid-cols-[1.5fr_1fr]">
      <Card><CardHeader><CardTitle>Cảnh báo cần can thiệp</CardTitle></CardHeader><CardContent className="space-y-3">{alerts.length === 0 ? <Empty text="Chưa có cảnh báo." /> : alerts.map((alert) => <article key={alert.id} className="rounded-2xl border-2 border-border p-5"><div className="flex flex-wrap items-start justify-between gap-3"><div><span className={`rounded-full px-3 py-1 text-xs font-bold uppercase ${alert.severity === "critical" ? "bg-red-100 text-red-800" : "bg-amber-100 text-amber-800"}`}>{alert.severity}</span><h3 className="mt-3 font-display text-xl font-bold">{alert.learner?.name} · {alert.rule_key.replaceAll("_", " ")}</h3><p className="mt-1 text-sm text-muted-foreground">{alert.evidence.length} snapshot bằng chứng · {alert.state}</p></div>{alert.state === "open" && <Button size="sm" onClick={() => resolve(alert)}>Đánh dấu đã xử lý</Button>}</div></article>)}</CardContent></Card>
      <Card><CardHeader><CardTitle>Học viên được giao</CardTitle></CardHeader><CardContent className="space-y-3">{learners.map((learner) => <div key={learner.id} className="rounded-2xl bg-muted p-4"><p className="font-bold">{learner.name}</p><p className="text-sm text-muted-foreground">{learner.email}</p></div>)}{learners.length === 0 && <Empty text="Chưa có học viên được phân công." />}</CardContent></Card>
    </div>
  </div>;
}

function Metric({ icon: Icon, value, label }: { icon: typeof IconUsers; value: number; label: string }) {
  return <Card className="flex items-center gap-4 p-5"><span className="rounded-2xl bg-accent p-3 text-primary"><Icon /></span><div><p className="text-3xl font-bold">{value}</p><p className="text-sm text-muted-foreground">{label}</p></div></Card>;
}
function Empty({ text }: { text: string }) { return <p className="rounded-xl bg-muted p-5 text-center text-muted-foreground">{text}</p>; }
