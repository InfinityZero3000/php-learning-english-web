"use client";

import { useCallback, useEffect, useState } from "react";
import { IconBrain, IconRefresh, IconSparkles } from "@tabler/icons-react";
import { ApiError, api, type FsrsCard, type FsrsPreview, type FsrsRating } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";

const ratings = [
  ["again", "Lại", "border-rose-200 bg-rose-600"],
  ["hard", "Khó", "border-orange-200 bg-orange-600"],
  ["good", "Tốt", "border-emerald-200 bg-emerald-600"],
  ["easy", "Dễ", "border-blue-200 bg-blue-600"],
] as const satisfies ReadonlyArray<readonly [FsrsRating, string, string]>;

export default function ReviewPage() {
  const [cards, setCards] = useState<FsrsCard[]>([]);
  const [revealed, setRevealed] = useState(false);
  const [state, setState] = useState<"loading" | "ready" | "error">("loading");
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const [preview, setPreview] = useState<FsrsPreview>();
  const [previewState, setPreviewState] = useState<"idle" | "loading" | "ready" | "error">("idle");
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

  useEffect(() => {
    if (!revealed || !current) return;
    let active = true;
    setPreviewState("loading");
    setPreview(undefined);
    api.fsrsPreview(current)
      .then((result) => {
        if (!active) return;
        if (result.card_id !== current.id || result.base_revision !== current.revision) {
          setPreviewState("error");
          return;
        }
        setPreview(result);
        setPreviewState("ready");
      })
      .catch(() => {
        if (active) setPreviewState("error");
      });
    return () => { active = false; };
  }, [current, revealed]);

  const rate = useCallback(async (rating: FsrsRating) => {
    if (!current || busy) return;
    setBusy(true);
    setMessage("");
    try {
      await api.fsrsReview(current, rating);
      setCards((items) => items.slice(1));
      setRevealed(false);
      setPreview(undefined);
      setPreviewState("idle");
    } catch (reason) {
      if (reason instanceof ApiError && reason.status === 409) {
        try {
          const response = await api.fsrsDue();
          setCards(response.data);
          setRevealed(false);
          setPreview(undefined);
          setPreviewState("idle");
          setMessage("Thẻ đã thay đổi ở nơi khác. Hàng đợi mới nhất đã được tải lại.");
        } catch {
          setMessage("Thẻ đã thay đổi nhưng chưa thể tải lại hàng đợi. Vui lòng thử lại.");
        }
      } else {
        setMessage(reason instanceof Error ? reason.message : "Không thể lưu đánh giá FSRS.");
      }
    } finally {
      setBusy(false);
    }
  }, [busy, current]);

  const reveal = useCallback(() => {
    if (revealed) return;
    setPreviewState("loading");
    setRevealed(true);
  }, [revealed]);

  useEffect(() => {
    function keydown(event: KeyboardEvent) {
      if (event.repeat || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey || isInteractive(event.target)) return;
      if (event.code === "Space" && !revealed) {
        event.preventDefault();
        reveal();
      }
      if (revealed && ["Digit1", "Digit2", "Digit3", "Digit4"].includes(event.code)) {
        const rating = ratings[Number(event.code.at(-1)) - 1]?.[0];
        if (rating) void rate(rating);
      }
    }
    window.addEventListener("keydown", keydown);
    return () => window.removeEventListener("keydown", keydown);
  }, [rate, reveal, revealed]);

  if (state === "loading") {
    return <div className="mx-auto max-w-2xl"><Card className="p-10 text-center"><p aria-live="polite" className="font-bold">Đang tải hàng đợi FSRS…</p></Card></div>;
  }
  if (state === "error") {
    return <div className="mx-auto max-w-2xl"><Card className="p-10 text-center"><p role="alert" className="font-bold">{message}</p><Button className="mt-5" onClick={load}>Thử lại</Button></Card></div>;
  }
  if (!current) {
    return <div className="mx-auto max-w-2xl"><Card className="p-10 text-center"><IconBrain className="mx-auto h-14 w-14 text-primary" /><h2 className="mt-4 font-display text-3xl font-bold">Đã ôn xong hôm nay</h2><p className="mt-2 text-muted-foreground">FSRS sẽ đưa từ trở lại đúng lúc cần thiết.</p></Card></div>;
  }

  return <div className="mx-auto max-w-3xl space-y-6 pb-8">
    <header className="relative overflow-hidden rounded-[2rem] bg-slate-950 p-7 text-white shadow-xl sm:p-9">
      <div aria-hidden="true" className="absolute -right-20 -top-20 h-52 w-52 rounded-full bg-cyan-400/20 blur-3xl" />
      <div className="relative flex items-end justify-between gap-5">
        <div>
          <p className="flex items-center gap-2 text-xs font-black uppercase tracking-[.2em] text-cyan-300"><IconSparkles className="h-4 w-4" /> FSRS-6 review</p>
          <h2 className="mt-3 font-display text-3xl font-black">{cards.length} từ đến hạn</h2>
          <p className="mt-2 text-sm text-slate-300">Space để lật · phím 1–4 để đánh giá</p>
        </div>
        <IconRefresh className="h-8 w-8 text-cyan-300" />
      </div>
    </header>

    {message && <p role="alert" className="rounded-2xl border border-amber-200 bg-amber-50 p-4 font-semibold text-amber-900">{message}</p>}

    <button
      type="button"
      onClick={reveal}
      aria-expanded={revealed}
      className="group block min-h-96 w-full rounded-[2rem] border border-border bg-card p-8 text-center shadow-xl transition hover:-translate-y-0.5 hover:border-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary sm:p-12"
    >
      <p className="text-xs font-black uppercase tracking-[.22em] text-muted-foreground">{revealed ? "Đáp án" : "Từ vựng"}</p>
      <p className="mt-10 font-display text-5xl font-black sm:text-6xl">{current.word}</p>
      {revealed
        ? <><div className="mx-auto my-8 h-px max-w-sm bg-border" /><p className="text-2xl font-black text-primary">{current.meaning}</p>{current.definition && <p className="mx-auto mt-3 max-w-lg leading-7 text-muted-foreground">{current.definition}</p>}</>
        : <p className="mt-12 font-semibold text-muted-foreground transition group-hover:text-primary">Chạm để lật thẻ</p>}
    </button>

    {revealed && <>
      {previewState === "error" && <div role="status" className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <p className="font-black">Chưa có dự đoán</p>
        <p className="mt-1">Bạn vẫn có thể tự đánh giá; lịch FSRS sẽ được tính khi lưu.</p>
      </div>}
      <div role="group" aria-label="Đánh giá mức độ ghi nhớ" className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        {ratings.map(([value, label, color], index) => {
          const interval = preview?.ratings.find((item) => item.rating === value);
          return <button
            key={value}
            onClick={() => rate(value)}
            disabled={busy}
            className={`rounded-2xl border px-4 py-4 font-display font-black uppercase text-white shadow-md transition hover:-translate-y-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 ${color}`}
          >
            <span className="block">{label}</span>
            <span className="mt-1 block text-xs font-semibold normal-case opacity-90">
              {previewState === "loading" ? "Đang tính…" : interval ? formatInterval(interval.interval_seconds) : `Phím ${index + 1}`}
            </span>
            <span className="mt-1 block text-[10px] font-normal opacity-75">Phím {index + 1}</span>
          </button>;
        })}
      </div>
    </>}
  </div>;
}

function formatInterval(value: number) {
  const seconds = Math.max(1, Math.round(value));
  const units = [
    [86400, "ngày"],
    [3600, "giờ"],
    [60, "phút"],
    [1, "giây"],
  ] as const;
  const parts: string[] = [];
  let remaining = seconds;
  for (const [size, label] of units) {
    const amount = Math.floor(remaining / size);
    if (amount > 0) {
      parts.push(`${amount} ${label}`);
      remaining %= size;
    }
    if (parts.length === 2) break;
  }
  return parts.join(" ");
}

function isInteractive(target: EventTarget | null) {
  return target instanceof HTMLElement
    && (target.isContentEditable || target.closest("a, button, input, select, textarea, [contenteditable='true']") !== null);
}
