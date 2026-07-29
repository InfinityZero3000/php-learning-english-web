"use client";

import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";
import { IconHeadphones, IconSearch } from "@tabler/icons-react";
import { api, ApiError, type YouTubeVideo } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";

export default function ListeningPage() {
  const [query, setQuery] = useState("English conversation");
  const [videos, setVideos] = useState<YouTubeVideo[]>([]);
  const [next, setNext] = useState<string>();
  const [message, setMessage] = useState("");
  const [loading, setLoading] = useState(true);

  async function search(pageToken?: string) {
    setLoading(true); setMessage("");
    try {
      const page = await api.youtubeSearch({ q: query, page_token: pageToken, per_page: 12 });
      setVideos(pageToken ? (items) => [...items, ...page.items] : page.items);
      setNext(page.next_page_token);
    } catch (reason) {
      setMessage(reason instanceof ApiError && reason.status === 503 ? "Listening practice chưa được bật cho tài khoản này." : reason instanceof Error ? reason.message : "Không thể tải video.");
    } finally { setLoading(false); }
  }
  useEffect(() => { void search(); }, []); // eslint-disable-line react-hooks/exhaustive-deps
  function submit(event: FormEvent) { event.preventDefault(); void search(); }

  return <div className="mx-auto max-w-6xl space-y-7">
    <header><p className="font-bold uppercase tracking-[.14em] text-primary">Listening practice · Luyện nghe</p><h2 className="font-display text-4xl font-bold">Nghe, đọc caption, rồi shadowing</h2><p className="mt-2 text-muted-foreground">Chọn video có caption để bắt đầu một lesson nghe.</p></header>
    <form onSubmit={submit} className="flex gap-2"><Input aria-label="Tìm video luyện nghe" value={query} onChange={(event) => setQuery(event.target.value)} /><Button disabled={loading || !query.trim()}><IconSearch className="h-5 w-5" />Tìm</Button></form>
    {message && <p role="alert" className="rounded-xl border-2 border-amber-200 bg-amber-50 p-4 text-amber-900">{message}</p>}
    {loading && <p role="status" aria-live="polite">Đang tìm video…</p>}
    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">{videos.map((video) => <Card key={video.id} className="overflow-hidden">{video.thumbnail_url ? <img src={video.thumbnail_url} alt="" width={480} height={270} loading="lazy" className="aspect-video w-full object-cover" /> : <div className="grid aspect-video place-items-center bg-muted"><IconHeadphones /></div>}<div className="p-5"><h3 className="line-clamp-2 font-display text-lg font-bold">{video.title}</h3><p className="mt-1 text-sm text-muted-foreground">{video.channel}</p>{video.has_captions === false ? <p className="mt-4 text-sm font-bold text-amber-800">Không có caption · chưa thể bắt đầu</p> : <Button asChildCompat="a" className="mt-4 w-full"><Link href={`/listening/${encodeURIComponent(video.id)}?title=${encodeURIComponent(video.title)}&channel=${encodeURIComponent(video.channel || "")}&thumbnail=${encodeURIComponent(video.thumbnail_url || "")}&duration=${video.duration_seconds || 0}`}>Bắt đầu lesson</Link></Button>}</div></Card>)}</div>
    {next && <Button variant="outline" onClick={() => search(next)} disabled={loading}>Xem thêm</Button>}
  </div>;
}
