"use client";

import { use, useEffect, useRef, useState } from "react";
import { useSearchParams } from "next/navigation";
import { IconMicrophone, IconPlayerStop } from "@tabler/icons-react";
import { api, type Caption, type ListeningSession, type PronunciationAssessment } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";

type Player = { getCurrentTime(): number; getDuration(): number; seekTo(seconds: number, allowSeekAhead: boolean): void; setPlaybackRate(rate: number): void; loadModule(name: string): void; unloadModule(name: string): void };
declare global { interface Window { YT?: { Player: new (element: HTMLElement, options: object) => Player }; onYouTubeIframeAPIReady?: () => void } }

export default function ListeningLessonPage({ params }: { params: Promise<{ video: string }> }) {
  const video = decodeURIComponent(use(params).video);
  const search = useSearchParams();
  const [session, setSession] = useState<ListeningSession>();
  const [captions, setCaptions] = useState<Caption[]>([]);
  const [active, setActive] = useState(0);
  const [selected, setSelected] = useState<Caption>();
  const [translation, setTranslation] = useState("");
  const [assessment, setAssessment] = useState<PronunciationAssessment>();
  const [message, setMessage] = useState("");
  const [recording, setRecording] = useState(false);
  const [showCaptions, setShowCaptions] = useState(true);
  const playerHost = useRef<HTMLDivElement>(null);
  const player = useRef<Player | undefined>(undefined);
  const sessionRef = useRef<ListeningSession | undefined>(undefined);
  const recorder = useRef<MediaRecorder | undefined>(undefined);
  useEffect(() => { sessionRef.current = session; }, [session]);

  async function save() { if (sessionRef.current && player.current) await api.updateListening(sessionRef.current.id, player.current.getCurrentTime(), player.current.getDuration()).then(setSession).catch(() => setMessage("Chưa lưu được vị trí xem; hãy thử lại.")); }

  useEffect(() => {
    api.youtubeCaptions(video).then(async (captionData) => {
      if (!captionData.items?.length) throw new Error("Video này không có caption nên chưa thể bắt đầu lesson.");
      setCaptions(captionData.items);
      setSession(await api.startListening({ id: video, title: search.get("title") || "YouTube listening", channel: search.get("channel") || undefined, thumbnail_url: search.get("thumbnail") || undefined, duration_seconds: Number(search.get("duration")) || undefined }));
    }).catch((reason) => setMessage(reason instanceof Error ? reason.message : "Không thể mở lesson."));
  }, [search, video]);

  useEffect(() => {
    if (!playerHost.current) return;
    const ready = () => { if (playerHost.current && window.YT) player.current = new window.YT.Player(playerHost.current, { videoId: video, playerVars: { cc_load_policy: 1 }, events: { onStateChange: (event: { data: number }) => { if (event.data === 0 || event.data === 2) void save(); } } }); };
    if (window.YT) ready(); else { window.onYouTubeIframeAPIReady = ready; const script = document.createElement("script"); script.src = "https://www.youtube.com/iframe_api"; document.head.appendChild(script); }
    const timer = window.setInterval(() => {
      const time = player.current?.getCurrentTime() ?? 0;
      const index = captions.findLastIndex((caption) => caption.start <= time);
      if (index >= 0) setActive(index);
      void save();
    }, 5000);
    return () => window.clearInterval(timer);
  }, [captions, video]);

  async function translate(caption: Caption) { setSelected(caption); setTranslation(""); try { setTranslation((await api.translate(caption.text)).translated_text); } catch { setMessage("Không dịch được caption lúc này."); } }
  async function toggleRecording() {
    if (recording) { recorder.current?.stop(); setRecording(false); return; }
    if (!selected || !session || !navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === "undefined") { setMessage("Chọn một caption và cho phép microphone, hoặc tiếp tục nghe bằng văn bản."); return; }
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true }); const chunks: Blob[] = []; const next = new MediaRecorder(stream);
      next.ondataavailable = (event) => { if (event.data.size) chunks.push(event.data); };
      next.onstop = async () => { stream.getTracks().forEach((track) => track.stop()); try { const result = await api.shadowCaption(session.id, new Blob(chunks, { type: next.mimeType || "audio/webm" }), selected.text); setAssessment(result); setSession((current) => current ? { ...current, shadowing_attempts: current.shadowing_attempts + 1, best_pronunciation_score: Math.max(current.best_pronunciation_score || 0, result.score) } : current); } catch { setMessage("Chấm phát âm tạm thời lỗi; tiến độ xem vẫn được giữ."); } };
      recorder.current = next; next.start(); setRecording(true);
    } catch { setMessage("Không truy cập được microphone; bạn vẫn có thể tiếp tục nghe."); }
  }
  async function complete() { if (!session) return; try { setSession(await api.completeListening(session.id)); } catch (reason) { setMessage(reason instanceof Error ? reason.message : "Chưa đủ điều kiện hoàn thành."); } }

  return <div className="mx-auto grid max-w-6xl gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
    <div className="space-y-5"><header><p className="font-bold uppercase tracking-[.14em] text-primary">Listening lesson · Bài luyện nghe</p><h2 className="font-display text-3xl font-bold">{search.get("title") || "YouTube listening"}</h2></header>{message && <p role="alert" aria-live="polite" className="rounded-xl border-2 border-amber-200 bg-amber-50 p-4">{message}</p>}<div className="aspect-video overflow-hidden rounded-2xl bg-black"><div ref={playerHost} className="h-full w-full" /></div><div className="flex flex-wrap gap-2"><label className="font-bold">Tốc độ <select className="ml-2 rounded-lg border p-2" defaultValue="1" onChange={(event) => player.current?.setPlaybackRate(Number(event.target.value))}><option value="0.75">0.75×</option><option value="1">1×</option><option value="1.25">1.25×</option></select></label><Button variant="outline" onClick={() => { const next = !showCaptions; setShowCaptions(next); if (next) player.current?.loadModule("captions"); else player.current?.unloadModule("captions"); }}>{showCaptions ? "Ẩn caption video" : "Hiện caption video"}</Button><Button variant="outline" asChildCompat="a"><a href={`https://www.youtube.com/watch?v=${encodeURIComponent(video)}`} target="_blank" rel="noreferrer">Mở trên YouTube</a></Button></div>{session && <Card className="p-5"><p className="font-bold">Đã xem {session.watched_percentage}% · Shadowing {session.shadowing_attempts}</p><Button className="mt-3" onClick={complete} disabled={session.status === "completed"}>{session.status === "completed" ? "Đã hoàn thành" : "Hoàn thành lesson"}</Button></Card>}</div>
    <Card className="max-h-[75vh] overflow-y-auto p-4"><h3 className="font-display text-xl font-bold">Captions</h3><div className="mt-3 space-y-2">{captions.map((caption, index) => <button key={`${caption.start}-${index}`} type="button" onClick={() => { player.current?.seekTo(caption.start, true); void translate(caption); }} className={`w-full rounded-xl p-3 text-left focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary ${index === active ? "bg-accent text-primary" : "hover:bg-muted"}`}><span className="mr-2 text-xs tabular-nums text-muted-foreground">{Math.floor(caption.start / 60)}:{String(Math.floor(caption.start % 60)).padStart(2, "0")}</span>{caption.text}</button>)}</div>{selected && <div className="sticky bottom-0 mt-4 rounded-xl border-2 bg-card p-4"><p className="font-bold">{selected.text}</p>{translation && <p className="mt-1 text-sm">{translation}</p>}<Button className="mt-3 w-full" onClick={toggleRecording}>{recording ? <IconPlayerStop className="h-5 w-5" /> : <IconMicrophone className="h-5 w-5" />}{recording ? "Dừng ghi" : "Shadow câu này"}</Button>{assessment && <p aria-live="polite" className="mt-2 font-bold">{assessment.score.toFixed(0)}/100 · {assessment.feedback}</p>}</div>}</Card>
  </div>;
}
