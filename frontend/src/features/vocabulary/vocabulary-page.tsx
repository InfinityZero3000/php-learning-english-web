"use client";

import { useCallback, useEffect, useState } from "react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { IconSearch } from "@tabler/icons-react";
import { api, type CatalogTopic, type VocabularyItem } from "@/lib/api";
import { AppShellLoading } from "@/components/layout/app-shell";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Dialog } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { VocabularyCard } from "./vocabulary-card";

function positive(value: string | null) { const number = Number(value); return Number.isInteger(number) && number > 0 ? number : 1; }
function optionalPositive(value: string | null) { const number = Number(value); return Number.isInteger(number) && number > 0 ? number : undefined; }

export function VocabularyPage() {
  const router = useRouter(); const pathname = usePathname(); const params = useSearchParams();
  const view = params.get("view") === "saved" ? "saved" : "all";
  const query = params.get("search") ?? "";
  const topicId = optionalPositive(params.get("topic_id"));
  const page = positive(params.get("page"));
  const [words, setWords] = useState<VocabularyItem[]>([]); const [topics, setTopics] = useState<CatalogTopic[]>([]);
  const [search, setSearch] = useState(query); const [lastPage, setLastPage] = useState(1);
  const [state, setState] = useState<"loading" | "ready" | "error">("loading"); const [message, setMessage] = useState(""); const [playing, setPlaying] = useState<number>(); const [detail, setDetail] = useState<VocabularyItem>(); const [savingId, setSavingId] = useState<number>();

  const updateQuery = useCallback((changes: Record<string, string | null>) => {
    const next = new URLSearchParams(params.toString());
    Object.entries(changes).forEach(([key, value]) => value ? next.set(key, value) : next.delete(key));
    router.replace(`${pathname}${next.size ? `?${next}` : ""}`);
  }, [params, pathname, router]);

  const load = useCallback(async () => {
    setState("loading"); setMessage("");
    try {
      const requested = optionalPositive(params.get("word"));
      const [response, availableTopics, requestedWord] = await Promise.all([api.vocabulary({ search: query, page, perPage: 24, topicId, saved: view === "saved" }), api.catalogTopics(), requested ? api.vocabularyItem(requested) : Promise.resolve(undefined)]);
      setWords(response.data); setLastPage(response.meta.last_page); setTopics(availableTopics);
      setDetail(requestedWord);
      setState("ready");
    } catch (reason) { setMessage(reason instanceof Error ? reason.message : "Không thể tải từ vựng."); setState("error"); }
  }, [page, params, query, topicId, view]);
  useEffect(() => { setSearch(query); void load(); }, [load, query]);

  async function speak(item: VocabularyItem) {
    setPlaying(item.id); setMessage(""); let source = item.audio_url ?? item.external_audio_url; let generated = false;
    try { if (!source) { source = await api.textToSpeech(item.word); generated = true; } const audio = new Audio(source); audio.addEventListener("ended", () => { if (generated && source) URL.revokeObjectURL(source); setPlaying(undefined); }, { once: true }); await audio.play(); }
    catch { if (generated && source) URL.revokeObjectURL(source); setPlaying(undefined); setMessage("Không phát được audio cho từ này."); }
  }

  async function bookmark(item: VocabularyItem) {
    setSavingId(item.id); setMessage("");
    try { const result = await api.bookmarkVocabulary(item.id, !item.is_bookmarked); setWords((current) => current.map((word) => word.id === item.id ? { ...word, is_bookmarked: result.bookmarked } : word)); if (detail?.id === item.id) setDetail({ ...detail, is_bookmarked: result.bookmarked }); }
    catch { setMessage("Không thể cập nhật từ đã lưu. Hãy thử lại."); }
    finally { setSavingId(undefined); }
  }

  if (state === "loading") return <AppShellLoading label="Loading vocabulary..." />;
  if (state === "error") return <Card className="mx-auto max-w-2xl p-10 text-center"><p role="alert" className="font-bold">{message}</p><Button className="mt-5" onClick={load}>Thử lại</Button></Card>;

  return <div className="space-y-6">
    <header className="flex flex-col justify-between gap-4 md:flex-row md:items-end"><div><p className="font-bold uppercase tracking-wider text-primary">LexiLingo catalog</p><h2 className="font-display text-3xl font-bold">Từ vựng</h2><p className="mt-1 text-muted-foreground">Tìm, nghe phát âm và lưu từ cần ôn.</p></div><form className="flex w-full max-w-md gap-2" onSubmit={(event) => { event.preventDefault(); updateQuery({ search: search.trim() || null, page: null }); }}><label className="relative flex-1"><span className="sr-only">Tìm từ vựng</span><IconSearch className="absolute left-3 top-3 h-5 w-5 text-muted-foreground" /><Input className="pl-10" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Tìm theo từ tiếng Anh" /></label><Button type="submit">Tìm</Button></form></header>
    <div className="flex flex-col gap-3 rounded-xl border-2 border-border bg-card p-4 sm:flex-row sm:items-end"><div className="flex gap-2" role="group" aria-label="Chế độ xem từ vựng"><Button type="button" variant={view === "all" ? "default" : "outline"} onClick={() => updateQuery({ view: null, page: null })}>Tất cả</Button><Button type="button" variant={view === "saved" ? "default" : "outline"} onClick={() => updateQuery({ view: "saved", page: null })}>Đã lưu</Button></div><label className="block min-w-52 flex-1 text-sm font-bold">Chủ đề<Select value={topicId ? String(topicId) : ""} onChange={(event) => updateQuery({ topic_id: event.target.value || null, page: null })}><option value="">Tất cả chủ đề</option>{topics.map((topic) => <option key={topic.id} value={topic.id}>{topic.name}</option>)}</Select></label></div>
    {message ? <p aria-live="polite" className="rounded-xl bg-amber-50 p-4 font-bold text-amber-900">{message}</p> : null}
    {words.length ? <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">{words.map((item) => <VocabularyCard key={item.id} item={item} isPlaying={playing === item.id} isSaving={savingId === item.id} onBookmark={(word) => void bookmark(word)} onSpeak={(word) => void speak(word)} onOpenDetail={(word) => { setDetail(word); updateQuery({ word: String(word.id) }); }} />)}</section> : <Card className="p-10 text-center font-bold text-muted-foreground">{view === "saved" ? "Bạn chưa lưu từ nào. Hãy quay lại danh mục để bắt đầu." : "Không tìm thấy từ phù hợp."}{view === "saved" ? <Button variant="outline" className="mt-4" onClick={() => updateQuery({ view: null, page: null })}>Xem tất cả từ</Button> : null}</Card>}
    <nav aria-label="Phân trang từ vựng" className="flex items-center justify-center gap-4"><Button variant="outline" disabled={page <= 1} onClick={() => updateQuery({ page: String(page - 1) })}>Trang trước</Button><span className="font-bold tabular-nums">{page} / {lastPage}</span><Button variant="outline" disabled={page >= lastPage} onClick={() => updateQuery({ page: String(page + 1) })}>Trang sau</Button></nav>
    <Dialog open={Boolean(detail)} onClose={() => { setDetail(undefined); updateQuery({ word: null }); }} title={detail?.word ?? "Chi tiết từ vựng"} className="max-w-lg"><div className="space-y-3">{detail?.pronunciation ? <p className="font-mono text-primary">{detail.pronunciation}</p> : null}<p className="text-lg font-bold">{detail?.meaning}</p>{detail?.definition ? <p>{detail.definition}</p> : null}{detail?.example ? <p className="rounded-xl bg-muted p-3 italic">{detail.example}</p> : null}{detail?.lesson ? <p className="text-sm text-muted-foreground">Bài học: {detail.lesson.title}</p> : null}{detail?.topic ? <p className="text-sm text-muted-foreground">Chủ đề: {detail.topic.name}</p> : null}{detail?.image_url ? <img src={detail.image_url} alt="" className="max-h-64 w-full rounded-xl object-cover" /> : null}{detail ? <Button type="button" variant="outline" onClick={() => void speak(detail)}>Nghe phát âm</Button> : null}</div></Dialog>
  </div>;
}
