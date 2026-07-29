"use client";

import { use, useCallback, useEffect, useRef, useState } from "react";
import { IconBulb, IconCheck, IconMicrophone, IconPlayerStop, IconRefresh, IconRobot, IconVolume } from "@tabler/icons-react";
import { api, type Assistance, type LearningSession, type PronunciationAssessment } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";

export default function SessionPage({ params }: { params: Promise<{ id: string }> }) {
  const sessionId = Number(use(params).id);
  const [session, setSession] = useState<LearningSession>();
  const [answer, setAnswer] = useState("");
  const [result, setResult] = useState<{ is_correct: boolean }>();
  const [assistance, setAssistance] = useState<Assistance>();
  const [pronunciation, setPronunciation] = useState<PronunciationAssessment>();
  const [hint, setHint] = useState("");
  const [hintCount, setHintCount] = useState(0);
  const [state, setState] = useState<"loading" | "ready" | "error">("loading");
  const [submitting, setSubmitting] = useState(false);
  const [translating, setTranslating] = useState(false);
  const [listening, setListening] = useState(false);
  const [voiceBusy, setVoiceBusy] = useState(false);
  const [finishing, setFinishing] = useState(false);
  const [recording, setRecording] = useState(false);
  const [message, setMessage] = useState("");
  const started = useRef(Date.now());
  const recorder = useRef<MediaRecorder | null>(null);

  const loadActivity = useCallback(async () => {
    setState("loading");
    setMessage("");
    try {
      setSession(await api.nextActivity(sessionId));
      setAnswer("");
      setResult(undefined);
      setAssistance(undefined);
      setPronunciation(undefined);
      setHint("");
      setHintCount(0);
      started.current = Date.now();
      setState("ready");
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : "Không thể tải hoạt động.");
      setState("error");
    }
  }, [sessionId]);

  useEffect(() => { void loadActivity(); }, [loadActivity]);
  const activity = session?.activity;

  async function submit() {
    if (!activity || !answer.trim()) return;
    setSubmitting(true);
    setMessage("");
    try {
      const attempt = await api.submitAttempt(sessionId, { activity_id: activity.id, answer, duration_ms: Date.now() - started.current, hint_count: hintCount });
      setResult(attempt);
      try {
        const feedback = await api.traceCag({ session_id: sessionId, activity_id: activity.id, input_type: "answer", text: answer });
        setAssistance(feedback.assistance);
      } catch {
        setMessage("TraceCAG tạm thời không khả dụng; kết quả học vẫn đã được lưu.");
      }
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : "Không thể lưu câu trả lời.");
    } finally {
      setSubmitting(false);
    }
  }

  async function requestHint() {
    if (!activity || hint) return;
    setTranslating(true);
    setMessage("");
    try {
      const translated = await api.translate(activity.word);
      setHint(translated.translated_text || activity.meaning);
      setHintCount((count) => count + 1);
    } catch {
      setHint(activity.meaning);
      setMessage("Đang dùng nghĩa đã lưu vì gợi ý AI tạm thời không khả dụng.");
    } finally {
      setTranslating(false);
    }
  }

  async function playAudio() {
    if (!activity) return;
    setListening(true);
    setMessage("");
    try {
      const source = activity.audio_url || await api.textToSpeech(activity.word);
      const audio = new Audio(source);
      audio.addEventListener("ended", () => { if (!activity.audio_url) URL.revokeObjectURL(source); }, { once: true });
      await audio.play();
    } catch {
      setMessage("Không phát được audio; bạn vẫn có thể tiếp tục bằng văn bản.");
    } finally {
      setListening(false);
    }
  }

  async function toggleRecording() {
    if (recording) {
      recorder.current?.stop();
      setRecording(false);
      return;
    }
    if (!activity || !navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === "undefined") {
      setMessage("Trình duyệt không hỗ trợ ghi âm; hãy nhập câu trả lời bằng bàn phím.");
      return;
    }
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      const chunks: Blob[] = [];
      const nextRecorder = new MediaRecorder(stream);
      nextRecorder.ondataavailable = (event) => { if (event.data.size) chunks.push(event.data); };
      nextRecorder.onstop = async () => {
        stream.getTracks().forEach((track) => track.stop());
        const audio = new Blob(chunks, { type: nextRecorder.mimeType || "audio/webm" });
        setVoiceBusy(true);
        try {
          const [transcription, assessment] = await Promise.all([
            api.speechToText(audio, sessionId, activity.id),
            api.assessPronunciation(audio, activity.word, sessionId, activity.id),
          ]);
          const transcript = transcription.transcript;
          if (transcript) setAnswer(transcript);
          if (Number.isFinite(assessment.score)) setPronunciation(assessment);
        } catch {
          setMessage("Voice AI tạm thời không khả dụng; hãy tiếp tục bằng văn bản.");
        } finally {
          setVoiceBusy(false);
        }
      };
      recorder.current = nextRecorder;
      nextRecorder.start();
      setRecording(true);
    } catch {
      setMessage("Không truy cập được microphone. Kiểm tra quyền trình duyệt hoặc nhập câu trả lời.");
    }
  }

  async function finish() {
    setFinishing(true);
    try {
      const completed = await api.completeSession(sessionId);
      sessionStorage.setItem(`session-summary:${sessionId}`, JSON.stringify(completed));
      window.location.href = `/session/${sessionId}/summary`;
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : "Không thể hoàn tất phiên.");
      setFinishing(false);
    }
  }

  if (state === "loading") return <Status text="Đang tải hoạt động tiếp theo…" />;
  if (state === "error") return <Status text={message} action={<Button onClick={loadActivity}><IconRefresh className="h-5 w-5" />Thử lại</Button>} />;
  if (!activity) return <Card className="mx-auto max-w-2xl p-10 text-center"><h2 className="font-display text-3xl font-bold">Bạn đã hoàn tất mọi hoạt động</h2><p className="mt-2 text-muted-foreground">Xem tổng kết để lưu tiến độ lesson.</p>{message && <p role="alert" className="mt-4 text-red-700">{message}</p>}<Button className="mt-6" onClick={finish} disabled={finishing}>{finishing ? "Đang lưu…" : "Hoàn tất & xem tổng kết"}</Button></Card>;

  const progress = session?.progress ?? { completed: 0, total: 1 };
  const percent = progress.total ? Math.round((progress.completed / progress.total) * 100) : 0;
  return <div className="mx-auto max-w-3xl space-y-5">
    <header className="flex items-end justify-between gap-4"><div><p className="font-bold uppercase tracking-wider text-primary">Focused session</p><h2 className="font-display text-3xl font-bold">Nhớ lại chủ động</h2></div><span className="rounded-full bg-accent px-4 py-2 text-sm font-bold text-primary tabular-nums">{progress.completed + 1}/{progress.total}</span></header>
    <div role="progressbar" aria-label="Tiến độ phiên học" aria-valuemin={0} aria-valuemax={100} aria-valuenow={percent} className="h-3 overflow-hidden rounded-full bg-muted"><div className="h-full rounded-full bg-gradient-to-r from-primary to-emerald-500 transition-[width]" style={{ width: `${percent}%` }} /></div>
    {message && <p role="status" aria-live="polite" className="rounded-xl border-2 border-amber-200 bg-amber-50 p-4 text-amber-900">{message}</p>}
    <Card className="p-7 md:p-10">
      <div className="flex items-start justify-between gap-4"><div className="min-w-0"><p className="text-sm font-bold uppercase tracking-widest text-muted-foreground">Dịch sang tiếng Việt</p><p className="mt-6 break-words font-display text-5xl font-bold">{activity.word}</p></div><button type="button" onClick={playAudio} disabled={listening} aria-label={`Phát âm ${activity.word}`} className="rounded-2xl bg-accent p-3 text-primary hover:bg-primary/15 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary disabled:opacity-50"><IconVolume aria-hidden="true" /></button></div>
      <label className="mt-12 block font-bold" htmlFor="answer">Câu trả lời</label>
      <div className="mt-2 flex gap-2"><Input id="answer" name="answer" autoComplete="off" className="h-14 text-lg" value={answer} onChange={(event) => setAnswer(event.target.value)} disabled={Boolean(result)} /><button type="button" onClick={toggleRecording} disabled={voiceBusy || Boolean(result)} aria-label={recording ? "Dừng ghi âm" : "Trả lời bằng giọng nói"} className={`grid h-14 w-14 shrink-0 place-items-center rounded-xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary ${recording ? "bg-red-600 text-white" : "bg-accent text-primary"}`}>{recording ? <IconPlayerStop aria-hidden="true" /> : <IconMicrophone aria-hidden="true" />}</button></div>
      {!result && <div className="mt-3"><Button type="button" variant="ghost" onClick={requestHint} disabled={translating || Boolean(hint)}><IconBulb className="h-5 w-5" />{translating ? "Đang dịch…" : hint ? "Đã dùng gợi ý" : "Gợi ý nghĩa"}</Button>{hint && <p role="status" className="mt-2 rounded-xl bg-amber-50 p-3 text-amber-900">Gợi ý: {hint}</p>}</div>}
      {pronunciation && <p className="mt-3 font-bold">Pronunciation: <span className="tabular-nums text-primary">{pronunciation.score.toFixed(0)}/100</span> · {pronunciation.score >= 80 ? "Tốt" : pronunciation.score >= 60 ? "Đang tiến bộ" : "Thử lại chậm hơn"}{pronunciation.feedback && <span className="block text-sm font-normal text-muted-foreground">{pronunciation.feedback}</span>}</p>}
      {!result ? <Button className="mt-5 w-full" size="lg" onClick={submit} disabled={submitting || !answer.trim()}>{submitting ? "Đang lưu…" : "Kiểm tra"}</Button> : <div className={`mt-6 rounded-2xl border-2 p-5 ${result.is_correct ? "border-emerald-300 bg-emerald-50" : "border-amber-300 bg-amber-50"}`}>
        <p className="flex items-center gap-2 font-display text-xl font-bold">{result.is_correct ? <IconCheck aria-hidden="true" className="text-emerald-700" /> : <IconBulb aria-hidden="true" className="text-amber-700" />}{result.is_correct ? "Chính xác" : `Đáp án: ${activity.meaning}`}</p>
        {assistance && <div className="mt-4 border-t border-current/10 pt-4"><p className="flex items-center gap-2 font-bold"><IconRobot aria-hidden="true" className="h-5 w-5" />AI Coach {assistance.degraded ? "· hướng dẫn dự phòng" : ""}</p><p className="mt-1 text-pretty">{assistance.feedback}</p><details className="mt-3 text-sm"><summary className="cursor-pointer font-bold">Xem phân tích và gợi ý</summary><p className="mt-2">{assistance.diagnosis.summary}</p>{assistance.hints.map((item) => <p key={`${item.level}-${item.text}`} className="mt-1">• {item.text}</p>)}<p className="mt-2 font-bold">Bước đề xuất: {assistance.recommended_action}</p></details></div>}
        <Button className="mt-5 w-full" onClick={loadActivity}>Hoạt động tiếp theo</Button>
      </div>}
    </Card>
  </div>;
}

function Status({ text, action }: { text: string; action?: React.ReactNode }) { return <Card className="mx-auto max-w-2xl p-10 text-center"><p role="status" className="font-bold">{text}</p>{action && <div className="mt-5">{action}</div>}</Card>; }
