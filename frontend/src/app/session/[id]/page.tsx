"use client";

import { use, useEffect, useRef, useState } from "react";
import { IconBulb, IconCheck, IconRobot, IconVolume } from "@tabler/icons-react";
import { api, type Assistance, type LearningSession } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";

export default function SessionPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const sessionId = Number(id);
  const [session, setSession] = useState<LearningSession>();
  const [answer, setAnswer] = useState("");
  const [result, setResult] = useState<{ is_correct: boolean }>();
  const [assistance, setAssistance] = useState<Assistance>();
  const [busy, setBusy] = useState(false);
  const started = useRef(Date.now());
  useEffect(() => { api.nextActivity(sessionId).then(setSession); }, [sessionId]);
  const activity = session?.activity;

  async function submit() {
    if (!activity || !answer.trim()) return;
    setBusy(true);
    try {
      const attempt = await api.submitAttempt(sessionId, { activity_id: activity.id, answer, duration_ms: Date.now() - started.current, hint_count: 0 });
      setResult(attempt);
      const feedback = await api.traceCag({ session_id: sessionId, activity_id: activity.id, input_type: "answer", text: answer });
      setAssistance(feedback.assistance);
    } finally { setBusy(false); }
  }

  async function finish() {
    await api.completeSession(sessionId);
    window.location.href = `/session/${sessionId}/summary`;
  }

  if (!activity) return <Card className="mx-auto max-w-2xl p-10 text-center"><h2 className="font-display text-3xl font-bold">Phiên học đã sẵn sàng kết thúc</h2><Button className="mt-6" onClick={finish}>Xem tổng kết</Button></Card>;
  return (
    <div className="mx-auto max-w-3xl space-y-5">
      <div className="flex items-center justify-between"><div><p className="font-bold uppercase tracking-wider text-primary">Focused session</p><h2 className="font-display text-3xl font-bold">Nhớ lại chủ động</h2></div><span className="rounded-full bg-accent px-4 py-2 text-sm font-bold text-primary">1 hoạt động</span></div>
      <Card className="p-7 md:p-10">
        <div className="flex items-start justify-between gap-4"><div><p className="text-sm font-bold uppercase tracking-widest text-muted-foreground">Dịch sang tiếng Việt</p><p className="mt-6 font-display text-5xl font-bold">{activity.word}</p></div><button aria-label="Phát âm thanh" className="rounded-2xl bg-accent p-3 text-primary"><IconVolume /></button></div>
        <label className="mt-12 block font-bold" htmlFor="answer">Câu trả lời</label>
        <Input id="answer" className="mt-2 h-14 text-lg" value={answer} onChange={(event) => setAnswer(event.target.value)} disabled={Boolean(result)} autoFocus />
        {!result ? <Button className="mt-5 w-full" size="lg" onClick={submit} disabled={busy || !answer.trim()}>{busy ? "Đang phân tích…" : "Kiểm tra"}</Button> : (
          <div className={`mt-6 rounded-2xl border-2 p-5 ${result.is_correct ? "border-emerald-300 bg-emerald-50" : "border-amber-300 bg-amber-50"}`}>
            <p className="flex items-center gap-2 font-display text-xl font-bold">{result.is_correct ? <IconCheck className="text-emerald-700" /> : <IconBulb className="text-amber-700" />}{result.is_correct ? "Chính xác" : `Đáp án: ${activity.meaning}`}</p>
            {assistance && <div className="mt-4 border-t border-current/10 pt-4"><p className="flex items-center gap-2 font-bold"><IconRobot className="h-5 w-5" />TraceCAG {assistance.degraded ? "fallback" : ""}</p><p className="mt-1">{assistance.feedback}</p></div>}
            <Button className="mt-5 w-full" onClick={finish}>Hoàn tất phiên</Button>
          </div>
        )}
      </Card>
    </div>
  );
}
