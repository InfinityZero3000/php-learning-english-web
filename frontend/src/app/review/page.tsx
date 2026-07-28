"use client";

import { useEffect, useState } from "react";
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
  const current = cards[0];
  useEffect(() => { api.fsrsDue().then((response) => setCards(response.data)); }, []);

  async function rate(rating: typeof ratings[number][0]) {
    if (!current) return;
    await api.fsrsReview(current, rating);
    setCards((items) => items.slice(1));
    setRevealed(false);
  }

  if (!current) return <div className="mx-auto max-w-2xl"><Card className="p-10 text-center"><IconBrain className="mx-auto h-14 w-14 text-primary" /><h2 className="mt-4 font-display text-3xl font-bold">Đã ôn xong hôm nay</h2><p className="mt-2 text-muted-foreground">FSRS sẽ đưa từ trở lại đúng lúc cần thiết.</p></Card></div>;
  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <div className="flex items-end justify-between"><div><p className="font-bold uppercase tracking-wider text-primary">FSRS-6 review</p><h2 className="font-display text-3xl font-bold">{cards.length} từ đến hạn</h2></div><IconRefresh className="h-7 w-7 text-muted-foreground" /></div>
      <button type="button" onClick={() => setRevealed(true)} className="block min-h-96 w-full rounded-3xl border-2 border-border bg-card p-10 text-center shadow-xl transition hover:border-primary focus:outline-none focus:ring-4 focus:ring-primary/20">
        <p className="text-sm font-bold uppercase tracking-[.2em] text-muted-foreground">{revealed ? "Đáp án" : "Từ vựng"}</p>
        <p className="mt-10 font-display text-5xl font-bold">{current.word}</p>
        {revealed ? <><div className="mx-auto my-8 h-px max-w-sm bg-border" /><p className="text-2xl font-bold text-primary">{current.meaning}</p><p className="mt-3 text-muted-foreground">{current.definition}</p></> : <p className="mt-12 text-muted-foreground">Chạm để lật thẻ</p>}
      </button>
      {revealed && <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">{ratings.map(([value, label, color]) => <button key={value} onClick={() => rate(value)} className={`rounded-2xl px-4 py-4 font-display font-bold uppercase text-white shadow-md ${color}`}>{label}</button>)}</div>}
    </div>
  );
}
