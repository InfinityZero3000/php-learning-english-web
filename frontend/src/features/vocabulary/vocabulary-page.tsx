"use client";

import {
  IconBookmark,
  IconBookmarkFilled,
  IconLanguage,
  IconSearch,
  IconVolume,
} from "@tabler/icons-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { AppShellLoading } from "@/components/layout/app-shell";
import { useToast } from "@/components/ui/toast";
import { ApiError, fetchBookmarks, toggleBookmarkVocabulary } from "@/lib/api";
import type { BookmarkResource } from "@/lib/api";
import { useAuth } from "@/features/auth/auth-context";
import { loginHref } from "@/features/auth/route-policy";

const INITIAL_COUNT = 40;
const SCROLL_BATCH = 40;
const MAX_VISIBLE = 600;

export function VocabularyPage() {
  const router = useRouter();
  const { status } = useAuth();
  const { toast } = useToast();

  const [bookmarks, setBookmarks] = useState<BookmarkResource[]>([]);
  const [loading, setLoading] = useState(true);
  const [hasMore, setHasMore] = useState(false);
  const [total, setTotal] = useState(0);
  const [query, setQuery] = useState("");
  const [toggling, setToggling] = useState<Set<number>>(new Set());
  const loadingMoreRef = useRef(false);
  const hasMoreRef = useRef(false);
  const loadedCountRef = useRef(0);

  // Only show vocabulary bookmarks
  const vocabBookmarks = useMemo(
    () => bookmarks.filter((b) => b.bookmark_type === "vocabulary"),
    [bookmarks],
  );

  const filtered = useMemo(() => {
    if (!query.trim()) return vocabBookmarks;
    const q = query.toLowerCase();
    return vocabBookmarks.filter((b) => {
      const word = b.vocabulary?.word?.toLowerCase() ?? "";
      const meaning = b.vocabulary?.meaning?.toLowerCase() ?? "";
      return word.includes(q) || meaning.includes(q);
    });
  }, [vocabBookmarks, query]);

  const load = useCallback(
    async (offset: number, size: number, mode: "replace" | "append") => {
      try {
        const page = Math.floor(offset / size) + 1;
        const res = await fetchBookmarks({ bookmark_type: "vocabulary", page, per_page: size });
        const newBookmarks = res.data ?? [];
        const meta = res.meta;

        setBookmarks((current) => {
          const merged = mode === "append" ? [...current, ...newBookmarks] : newBookmarks;
          loadedCountRef.current = merged.length;
          return merged;
        });

        const lastPage = meta?.last_page ?? 1;
        const currentPage = meta?.current_page ?? 1;
        const more = currentPage < lastPage && loadedCountRef.current < MAX_VISIBLE;
        setHasMore(more);
        hasMoreRef.current = currentPage < lastPage;
        setTotal(meta?.total ?? 0);
      } catch (err) {
        if (err instanceof ApiError && err.status === 401) {
          router.push(loginHref("/vocabulary"));
        } else {
          toast("Could not load vocabulary bookmarks.", "error");
        }
      } finally {
        setLoading(false);
        loadingMoreRef.current = false;
      }
    },
    [router, toast],
  );

  const loadNextBatch = useCallback(() => {
    if (loadingMoreRef.current || !hasMoreRef.current) return;
    if (loadedCountRef.current >= MAX_VISIBLE) return;
    loadingMoreRef.current = true;
    void load(loadedCountRef.current, SCROLL_BATCH, "append");
  }, [load]);

  // Initial load
  useEffect(() => {
    setLoading(true);
    void load(0, INITIAL_COUNT, "replace");
  }, [load]);

  // Infinite scroll
  useEffect(() => {
    let lastY = window.scrollY;
    let frame = 0;

    function handleScroll() {
      if (frame) return;
      frame = window.requestAnimationFrame(() => {
        const currentY = window.scrollY;
        const isScrollingDown = currentY > lastY;
        lastY = currentY;
        frame = 0;

        if (isScrollingDown) {
          const distanceToBottom = document.documentElement.scrollHeight - currentY - window.innerHeight;
          if (distanceToBottom < 400) {
            loadNextBatch();
          }
        }
      });
    }

    window.addEventListener("scroll", handleScroll, { passive: true });
    return () => {
      window.removeEventListener("scroll", handleScroll);
      if (frame) window.cancelAnimationFrame(frame);
    };
  }, [loadNextBatch]);

  async function handleToggle(b: BookmarkResource) {
    if (status !== "authenticated") {
      router.push(loginHref("/vocabulary"));
      return;
    }
    const vocabId = b.vocabulary?.id;
    if (!vocabId) return;

    setToggling((prev) => new Set([...prev, vocabId]));
    try {
      await toggleBookmarkVocabulary(vocabId);
      // Remove from list
      setBookmarks((current) => current.filter((bm) => bm.id !== b.id));
      setTotal((prev) => prev - 1);
      toast("Bookmark removed.", "success");
    } catch (err) {
      if (err instanceof ApiError && err.status === 401) {
        router.push(loginHref("/vocabulary"));
      } else {
        toast("Failed to toggle bookmark.", "error");
      }
    } finally {
      setToggling((prev) => {
        const next = new Set(prev);
        next.delete(vocabId);
        return next;
      });
    }
  }

  function speak(word: string) {
    if (typeof speechSynthesis !== "undefined") {
      const u = new SpeechSynthesisUtterance(word);
      u.lang = "en-US";
      speechSynthesis.cancel();
      speechSynthesis.speak(u);
    }
  }

  if (loading) {
    return <AppShellLoading label="Loading vocabulary..." />;
  }

  return (
    <div className="mx-auto max-w-5xl">
      {/* Header */}
      <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="font-display text-[28px] font-bold text-foreground">My Bookmarked Words</h1>
          <p className="mt-1 text-sm font-semibold text-muted-foreground">
            {total > 0 ? `${total} bookmark${total !== 1 ? "s" : ""}` : "Words you've saved for review"}
          </p>
        </div>
        {/* Search */}
        <div className="relative w-full max-w-xs">
          <IconSearch className="absolute left-3.5 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-muted-foreground" />
          <input
            className="w-full rounded-xl border-2 border-border bg-white py-2.5 pl-10 pr-4 text-sm font-semibold text-foreground placeholder:text-muted-foreground/60 focus:border-primary focus:outline-none"
            placeholder="Search bookmarks..."
            value={query}
            onChange={(e) => setQuery(e.target.value)}
          />
        </div>
      </div>

      {/* Empty */}
      {vocabBookmarks.length === 0 && !loading && (
        <div className="rounded-xl border-2 border-dashed border-border bg-muted/20 py-16 text-center">
          <IconBookmark className="mx-auto mb-4 h-12 w-12 text-muted-foreground/40" />
          <p className="text-sm font-semibold text-muted-foreground">
            {query ? "No bookmarks match your search." : "No bookmarked vocabulary yet."}
          </p>
          <p className="mt-1 text-xs font-medium text-muted-foreground/70">
            Browse courses and bookmark words to see them here.
          </p>
        </div>
      )}

      {/* Words grid */}
      {filtered.length > 0 && (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {filtered.map((b) => {
            const word = b.vocabulary;
            if (!word) return null;
            const isToggling = toggling.has(word.id);
            return (
              <div
                key={b.id}
                className="group relative flex flex-col rounded-xl border-2 border-border bg-white p-4 transition hover:border-primary/30"
              >
                {/* Bookmark toggle */}
                <button
                  type="button"
                  disabled={isToggling}
                  onClick={() => handleToggle(b)}
                  className="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-muted/50 text-yellow-500 transition hover:bg-yellow-50 disabled:opacity-50"
                  aria-label="Remove bookmark"
                >
                  {isToggling ? (
                    <span className="h-4 w-4 animate-spin rounded-full border-2 border-muted-foreground/30 border-t-yellow-500" />
                  ) : (
                    <IconBookmarkFilled className="h-5 w-5" />
                  )}
                </button>

                {/* Word */}
                <div className="mb-1.5 flex items-center gap-2 pr-10">
                  <h3 className="font-display text-lg font-bold text-foreground">{word.word}</h3>
                  <button
                    type="button"
                    onClick={() => speak(word.word)}
                    className="text-muted-foreground hover:text-primary"
                    aria-label="Pronounce"
                  >
                    <IconVolume className="h-4 w-4" />
                  </button>
                </div>

                {/* Meaning */}
                {word.meaning && (
                  <p className="mb-2 text-sm font-semibold leading-relaxed text-foreground/80">{word.meaning}</p>
                )}

                {/* Translation */}
                {typeof word.translation === "string" && word.translation.length > 0 && (
                  <p className="mb-2 text-xs italic text-muted-foreground">
                    {word.translation}
                  </p>
                )}
              </div>
            );
          })}
        </div>
      )}

      {/* Load more indicator */}
      {hasMore && filtered.length > 0 && (
        <p className="mt-6 text-center text-xs font-semibold text-muted-foreground">
          Scroll to load more...
        </p>
      )}
    </div>
  );
}
