"use client";

import { useCallback, useEffect, useState } from "react";
import { IconBrain, IconRefresh } from "@tabler/icons-react";
import { api, type FsrsCard } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";

const ratings = [
  ["again", "Lại", "bg-red-600"],
  ["hard", "Khó", "bg-orange-600"],
  ["good", "Tốt", "bg-emerald-600"],
  ["easy", "Dễ", "bg-blue-600"]
] as const;

export default function ReviewPage() {
  const [cards, setCards] = useState<FsrsCard[]>([]);
  const [revealed, setRevealed] = useState(false);
  const [state, setState] = useState<"loading" | "ready" | "error">("loading");
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const current = cards[0];
  async function load() {
    setState("loading");
    setMessage("");
    try {
      const response = await api.fsrsDue();
      setCards(response.data);
      setState("ready");
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : "Không thể tải hàng đợi FSRS.");
      setState("error");
    }
  }
  useEffect(() => { void load(); }, []);

  const rate = useCallback(async (rating: typeof ratings[number][0]) => {
    if (!current) return;
    setBusy(true);
    try {
      await api.fsrsReview(current, rating);
      setCards((items) => items.slice(1));
      setRevealed(false);
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : "Không thể lưu đánh giá FSRS.");
    } finally {
      setBusy(false);
    }
  }, [current]);

  useEffect(() => {
    function keydown(event: KeyboardEvent) {
      if (event.code === "Space" && !revealed) { event.preventDefault(); setRevealed(true); }
      if (revealed && ["Digit1", "Digit2", "Digit3", "Digit4"].includes(event.code)) {
        const rating = ratings[Number(event.code.at(-1)) - 1]?.[0];
        if (rating) void rate(rating);
      }
    }
    window.addEventListener("keydown", keydown);
    return () => window.removeEventListener("keydown", keydown);
  }, [rate, revealed]);

  if (state === "loading") return <div className="mx-auto max-w-2xl"><Card className="p-10 text-center"><p aria-live="polite" className="font-bold">Đang tải hàng đợi FSRS…</p></Card></div>;
  if (state === "error") return <div className="mx-auto max-w-2xl"><Card className="p-10 text-center"><p role="alert" className="font-bold">{message}</p><Button className="mt-5" onClick={load}>Thử lại</Button></Card></div>;
  if (!current) return <div className="mx-auto max-w-2xl"><Card className="p-10 text-center"><IconBrain className="mx-auto h-14 w-14 text-primary" /><h2 className="mt-4 font-display text-3xl font-bold">Đã ôn xong hôm nay</h2><p className="mt-2 text-muted-foreground">FSRS sẽ đưa từ trở lại đúng lúc cần thiết.</p></Card></div>;
  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <div className="flex items-end justify-between"><div><p className="font-bold uppercase tracking-wider text-primary">FSRS-6 review</p><h2 className="font-display text-3xl font-bold">{cards.length} từ đến hạn</h2></div><IconRefresh className="h-7 w-7 text-muted-foreground" /></div>
      {message && <p role="alert" className="rounded-xl bg-red-50 p-4 text-red-800">{message}</p>}
      <button type="button" onClick={() => setRevealed(true)} aria-expanded={revealed} className="block min-h-96 w-full rounded-3xl border-2 border-border bg-card p-10 text-center shadow-xl transition hover:border-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary">
        <p className="text-sm font-bold uppercase tracking-[.2em] text-muted-foreground">{revealed ? "Đáp án" : "Từ vựng"}</p>
        <p className="mt-10 font-display text-5xl font-bold">{current.word}</p>
        {revealed ? <><div className="mx-auto my-8 h-px max-w-sm bg-border" /><p className="text-2xl font-bold text-primary">{current.meaning}</p><p className="mt-3 text-muted-foreground">{current.definition}</p></> : <p className="mt-12 text-muted-foreground">Chạm để lật thẻ</p>}
      </button>
      {revealed && <div role="group" aria-label="Đánh giá mức độ ghi nhớ" className="grid grid-cols-2 gap-3 sm:grid-cols-4">{ratings.map(([value, label, color], index) => <button key={value} onClick={() => rate(value)} disabled={busy} className={`rounded-2xl px-4 py-4 font-display font-bold uppercase text-white shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 ${color}`}><span className="block">{label}</span><span className="mt-1 block text-xs font-normal opacity-85">Phím {index + 1}</span></button>)}</div>}
    </div>
  );
}
