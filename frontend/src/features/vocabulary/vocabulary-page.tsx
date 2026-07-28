"use client";

import { useCallback, useEffect, useState } from "react";
import { IconPlayerPlay, IconSearch } from "@tabler/icons-react";
import { api, type VocabularyItem } from "@/lib/api";
import { AppShellLoading } from "@/components/layout/app-shell";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";

export function VocabularyPage() {
  const [words, setWords] = useState<VocabularyItem[]>([]);
  const [search, setSearch] = useState("");
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [state, setState] = useState<"loading" | "ready" | "error">("loading");
  const [message, setMessage] = useState("");
  const [playing, setPlaying] = useState<number>();

  const load = useCallback(async () => {
    setState("loading");
    setMessage("");
    try {
      const response = await api.vocabulary({ search: query, page, perPage: 24 });
      setWords(response.data);
      setLastPage(response.meta.last_page);
      setState("ready");
    } catch (reason) {
      setMessage(reason instanceof Error ? reason.message : "Không thể tải từ vựng.");
      setState("error");
    }
  }, [page, query]);

  useEffect(() => { void load(); }, [load]);

  async function speak(item: VocabularyItem) {
    setPlaying(item.id);
    setMessage("");
    let source = item.external_audio_url;
    let generated = false;
    try {
      if (!source) {
        source = await api.textToSpeech(item.word);
        generated = true;
      }
      const audio = new Audio(source);
      audio.addEventListener("ended", () => {
        if (generated && source) URL.revokeObjectURL(source);
        setPlaying(undefined);
      }, { once: true });
      await audio.play();
    } catch {
      if (generated && source) URL.revokeObjectURL(source);
      setPlaying(undefined);
      setMessage("Không phát được audio cho từ này.");
    }
  }

  if (state === "loading") return <AppShellLoading label="Loading vocabulary..." />;
  if (state === "error") return <Card className="mx-auto max-w-2xl p-10 text-center"><p role="alert" className="font-bold">{message}</p><Button className="mt-5" onClick={load}>Thử lại</Button></Card>;

  return (
    <div className="space-y-6">
      <header className="flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
          <p className="font-bold uppercase tracking-wider text-primary">LexiLingo catalog</p>
          <h2 className="font-display text-3xl font-bold">Từ vựng</h2>
          <p className="mt-1 text-muted-foreground">Nghe phát âm và dùng các từ đã lưu trong phiên học FSRS.</p>
        </div>
        <form className="flex w-full max-w-md gap-2" onSubmit={(event) => { event.preventDefault(); setPage(1); setQuery(search.trim()); }}>
          <label className="relative flex-1">
            <span className="sr-only">Tìm từ vựng</span>
            <IconSearch className="absolute left-3 top-3 h-5 w-5 text-muted-foreground" />
            <Input className="pl-10" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Tìm theo từ tiếng Anh" />
          </label>
          <Button type="submit">Tìm</Button>
        </form>
      </header>

      {message && <p role="status" className="rounded-xl bg-amber-50 p-4 font-bold text-amber-900">{message}</p>}
      {words.length ? (
        <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {words.map((item) => (
            <Card key={item.id} className="flex min-h-52 flex-col justify-between p-5">
              <div>
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <h3 className="font-display text-2xl font-bold text-primary">{item.word}</h3>
                    {item.pronunciation && <p className="mt-1 font-mono text-sm text-muted-foreground">{item.pronunciation}</p>}
                  </div>
                  <button type="button" onClick={() => speak(item)} disabled={playing === item.id} aria-label={`Phát âm ${item.word}`} className="rounded-xl bg-accent p-3 text-primary disabled:opacity-50">
                    <IconPlayerPlay className="h-5 w-5" />
                  </button>
                </div>
                <p className="mt-5 text-lg font-bold">{item.meaning}</p>
                {item.definition && <p className="mt-2 text-sm text-muted-foreground">{item.definition}</p>}
              </div>
              <div className="mt-5 flex flex-wrap gap-2 text-xs font-bold uppercase text-muted-foreground">
                {item.part_of_speech && <span className="rounded-full bg-muted px-3 py-1">{item.part_of_speech}</span>}
                {item.difficulty_level && <span className="rounded-full bg-accent px-3 py-1 text-primary">{item.difficulty_level}</span>}
              </div>
            </Card>
          ))}
        </section>
      ) : <Card className="p-10 text-center font-bold text-muted-foreground">Không tìm thấy từ phù hợp.</Card>}

      <nav aria-label="Phân trang từ vựng" className="flex items-center justify-center gap-4">
        <Button variant="outline" disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>Trang trước</Button>
        <span className="font-bold tabular-nums">{page} / {lastPage}</span>
        <Button variant="outline" disabled={page >= lastPage} onClick={() => setPage((value) => value + 1)}>Trang sau</Button>
      </nav>
    </div>
  );
}
