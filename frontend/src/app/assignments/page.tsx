"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { IconCalendarDue, IconChecklist, IconPlayerPlay, IconRefresh } from "@tabler/icons-react";
import { api, type LearnerAssignment } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";

export default function AssignmentsPage() {
  const router = useRouter();
  const [items, setItems] = useState<LearnerAssignment[]>([]);
  const [state, setState] = useState<"loading" | "ready" | "error">("loading");
  const [message, setMessage] = useState("");
  const [starting, setStarting] = useState<number>();

  async function load() {
    setState("loading");
    setMessage("");
    try {
      setItems(await api.learningAssignments());
      setState("ready");
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : "Không thể tải assignments.");
      setState("error");
    }
  }

  useEffect(() => { void load(); }, []);

  async function start(id: number) {
    setStarting(id);
    setMessage("");
    try {
      const session = await api.startAssignment(id);
      router.push(`/session/${session.id}`);
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : "Không thể bắt đầu bài được giao.");
      setStarting(undefined);
    }
  }

  if (state === "loading") return <Status text="Đang tải bài giáo viên giao…" />;
  if (state === "error") return <Status text={message} action={<Button onClick={load}><IconRefresh className="h-5 w-5" />Thử lại</Button>} />;

  const active = items.filter((item) => item.status === "pending" || item.status === "in_progress");
  const history = items.filter((item) => !active.includes(item));

  return <div className="mx-auto max-w-5xl space-y-7">
    <header className="grid gap-5 rounded-3xl border-2 border-primary/20 bg-accent p-7 md:grid-cols-[1fr_auto] md:items-end">
      <div><p className="font-bold uppercase tracking-[.14em] text-primary">Teacher-guided</p><h2 className="mt-2 font-display text-4xl font-bold text-balance">Assignments</h2><p className="mt-2 max-w-2xl text-muted-foreground">Bài luyện được giao riêng dựa trên bằng chứng học tập của bạn.</p></div>
      <div className="rounded-2xl bg-card px-6 py-4 text-center shadow-sm"><p className="text-3xl font-bold tabular-nums text-primary">{active.length}</p><p className="text-xs font-bold uppercase text-muted-foreground">đang chờ</p></div>
    </header>
    {message && <p role="alert" className="rounded-xl border-2 border-amber-200 bg-amber-50 p-4 text-amber-900">{message}</p>}
    <section aria-labelledby="active-assignments"><h3 id="active-assignments" className="mb-3 font-display text-2xl font-bold">Cần hoàn thành</h3>
      <div className="grid gap-4 md:grid-cols-2">
        {active.map((item) => <AssignmentCard key={item.id} item={item} action={<Button onClick={() => start(item.id)} disabled={starting === item.id}><IconPlayerPlay className="h-5 w-5" />{starting === item.id ? "Đang mở…" : item.status === "in_progress" ? "Tiếp tục" : "Bắt đầu"}</Button>} />)}
        {active.length === 0 && <Card className="p-8 text-center md:col-span-2"><IconChecklist className="mx-auto h-10 w-10 text-emerald-600" /><p className="mt-3 font-bold">Không có assignment đang chờ.</p></Card>}
      </div>
    </section>
    {history.length > 0 && <section aria-labelledby="assignment-history"><h3 id="assignment-history" className="mb-3 font-display text-2xl font-bold">Lịch sử</h3><div className="grid gap-3 md:grid-cols-2">{history.map((item) => <AssignmentCard key={item.id} item={item} />)}</div></section>}
  </div>;
}

function AssignmentCard({ item, action }: { item: LearnerAssignment; action?: React.ReactNode }) {
  const target = item.lesson?.title ?? (item.vocabulary ? `${item.vocabulary.word} · ${item.vocabulary.meaning}` : "Nội dung đã được gỡ");
  return <Card className="flex flex-col p-6"><div className="flex items-start justify-between gap-3"><div><span className="rounded-full bg-muted px-3 py-1 text-xs font-bold uppercase">{item.status.replace("_", " ")}</span><h4 className="mt-3 font-display text-xl font-bold">{target}</h4><p className="mt-1 text-sm text-muted-foreground">Giáo viên: {item.teacher.name}</p></div><IconChecklist aria-hidden="true" className="shrink-0 text-primary" /></div>
    {item.instructions && <p className="mt-4 rounded-xl bg-muted p-3 text-sm">{item.instructions}</p>}
    {item.due_at && <p className="mt-4 flex items-center gap-2 text-sm font-semibold"><IconCalendarDue aria-hidden="true" className="h-5 w-5 text-primary" /><time dateTime={item.due_at}>{new Intl.DateTimeFormat("vi-VN", { dateStyle: "medium", timeStyle: "short" }).format(new Date(item.due_at))}</time></p>}
    {action && <div className="mt-auto pt-5">{action}</div>}
  </Card>;
}

function Status({ text, action }: { text: string; action?: React.ReactNode }) {
  return <Card className="mx-auto max-w-xl p-10 text-center"><p role="status" className="font-bold">{text}</p>{action && <div className="mt-5">{action}</div>}</Card>;
}
