"use client";

import { useSearchParams } from "next/navigation";
import {
  IconBooks,
  IconCheck,
  IconEdit,
  IconLoader2,
  IconPhotoOff,
  IconPlus,
  IconSearch,
  IconSparkles,
  IconTrash,
  IconX
} from "@tabler/icons-react";
import { FormEvent, useCallback, useEffect, useRef, useState, useMemo } from "react";
import { AppShellLoading } from "@/components/layout/app-shell";
import { Dialog } from "@/components/ui/dialog";
import { Select } from "@/components/ui/select";
import { useToast } from "@/components/ui/toast";
import { api } from "@/lib/api";
import type { DictionaryLookup, DifficultyLevel, PageResponse, Topic, Word } from "@/types/api";

const INITIAL_WORD_COUNT = 20;
const SCROLL_WORD_BATCH_SIZE = 16;
// Hard cap on DOM word nodes. Beyond this, auto-scroll stops and the user must
// use search/filters. Prevents unbounded DOM growth with large vocabularies.
const MAX_VISIBLE_WORDS = 120;

const emptyWord: Partial<Word> = {
  word: "",
  translation: "",
  pronunciation: "",
  category: "",
  difficulty: "INTERMEDIATE",
  example: "",
  imageUrl: ""
};

function DifficultyPill({ difficulty }: { difficulty?: string }) {
  if (!difficulty) return null;
  const styles: Record<string, string> = {
    BEGINNER: "bg-accent text-accent-foreground",
    INTERMEDIATE: "bg-secondary/40 text-secondary-foreground",
    ADVANCED: "bg-destructive/15 text-destructive"
  };
  const labels: Record<string, string> = {
    BEGINNER: "Beginner",
    INTERMEDIATE: "Intermediate",
    ADVANCED: "Advanced"
  };
  return (
    <span
      className={`shrink-0 rounded-full px-2 py-0.5 font-display text-[0.7rem] font-bold uppercase tracking-[0.04em] ${styles[difficulty] ?? "bg-muted text-muted-foreground"}`}
    >
      {labels[difficulty] ?? difficulty}
    </span>
  );
}

function EnrichmentPill({ status }: { status?: string }) {
  const s = status ?? "NOT_REQUESTED";
  const conf: Record<string, { cls: string; label: string }> = {
    NOT_REQUESTED: { cls: "bg-muted text-muted-foreground border border-border", label: "Not enriched" },
    PENDING: { cls: "bg-secondary/40 text-secondary-foreground border border-secondary/60", label: "Enriching..." },
    RUNNING: { cls: "bg-secondary/40 text-secondary-foreground border border-secondary/60", label: "Enriching..." },
    PARTIAL: { cls: "bg-accent text-accent-foreground border border-accent/60", label: "Partial" },
    COMPLETED: { cls: "bg-emerald-100 text-emerald-800 border border-emerald-300", label: "Enriched" },
    FAILED: { cls: "bg-destructive/10 text-destructive border border-destructive/30", label: "Failed" }
  };
  const { cls, label } = conf[s] ?? conf.NOT_REQUESTED;
  return (
    <span className={`rounded-full px-2 py-0.5 font-display text-[0.68rem] font-bold uppercase ${cls}`}>
      {label}
    </span>
  );
}

function WordImage({ word }: { word: Word }) {
  const [failed, setFailed] = useState(false);
  const showImage = word.imageUrl && !failed;

  useEffect(() => {
    setFailed(false);
  }, [word.imageUrl]);

  if (showImage) {
    return (
      <img
        src={word.imageUrl}
        alt={word.word}
        loading="lazy"
        onError={() => setFailed(true)}
        className="aspect-video w-full rounded-lg border border-border bg-muted object-cover"
      />
    );
  }

  return (
    <div
      aria-label={`${word.word} image placeholder`}
      className="flex aspect-video w-full flex-col items-center justify-center gap-2 rounded-lg border border-border bg-[linear-gradient(135deg,#e0f2fe_0%,#fef3c7_52%,#dcfce7_100%)] text-center text-primary"
    >
      <IconPhotoOff className="h-8 w-8 opacity-75" strokeWidth={1.8} />
      <span className="max-w-[85%] truncate font-display text-[0.78rem] font-bold uppercase tracking-[0.04em] text-foreground/70">
        {word.word}
      </span>
    </div>
  );
}

function normalizeWordPage(
  payload: PageResponse<Word> | Word[] | undefined,
  offset: number,
  size: number
): PageResponse<Word> {
  if (!payload) {
    return {
      content: [],
      number: Math.floor(offset / size),
      size,
      totalElements: 0,
      totalPages: 0,
      last: true
    };
  }

  if (!Array.isArray(payload)) {
    return {
      content: payload.content ?? [],
      number: payload.number ?? Math.floor(offset / size),
      size: payload.size ?? size,
      totalElements: payload.totalElements ?? payload.content?.length ?? 0,
      totalPages: payload.totalPages ?? 1,
      last: payload.last ?? true
    };
  }

  const content = payload.slice(offset, offset + size);
  return {
    content,
    number: Math.floor(offset / size),
    size,
    totalElements: payload.length,
    totalPages: Math.ceil(payload.length / size),
    last: offset + size >= payload.length
  };
}

function WordDetail3DModal({ word, onClose }: { word: Word | null, onClose: () => void }) {
  const cardRef = useRef<HTMLDivElement>(null);
  const rafRef = useRef<number>(0);

  if (!word) return null;

  const handleMouseMove = (e: React.MouseEvent) => {
    const clientX = e.clientX;
    const clientY = e.clientY;
    if (rafRef.current) cancelAnimationFrame(rafRef.current);
    rafRef.current = requestAnimationFrame(() => {
      const card = cardRef.current;
      if (!card) return;
      const rect = card.getBoundingClientRect();
      const rotateX = ((clientY - rect.top - rect.height / 2) / (rect.height / 2)) * -10;
      const rotateY = ((clientX - rect.left - rect.width / 2) / (rect.width / 2)) * 10;
      card.style.transform = `rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.02, 1.02, 1.02)`;
    });
  };

  const handleMouseLeave = () => {
    if (rafRef.current) cancelAnimationFrame(rafRef.current);
    if (cardRef.current) cardRef.current.style.transform = "rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)";
  };

  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/40 p-4 xl:p-8 backdrop-blur-sm transition-opacity" onClick={onClose}>
      <div
        className="group relative w-full max-w-[380px] animate-in fade-in zoom-in-95 duration-300"
        style={{ perspective: "1000px" }}
        onClick={(e) => e.stopPropagation()}
      >
        <div
          ref={cardRef}
          className="relative transition-all duration-300 ease-out"
          style={{ transformStyle: "preserve-3d" }}
          onMouseMove={handleMouseMove}
          onMouseLeave={handleMouseLeave}
        >
          {/* Shadow layer behind */}
          <div className="absolute inset-0 rounded-2xl bg-black/20 blur-xl transition-all duration-300" style={{ transform: "translateZ(-30px) translateY(10px)" }}></div>
          
          <div className="relative flex flex-col gap-4 overflow-hidden rounded-2xl border-4 border-white bg-white p-6 shadow-2xl transition-colors" style={{ transform: "translateZ(20px)" }}>
            <WordImage word={word} />
            <div className="flex flex-col gap-1">
              <div className="flex items-start justify-between gap-2">
                <h2 className="font-display text-2xl font-bold text-foreground">{word.word}</h2>
                <DifficultyPill difficulty={word.difficulty} />
              </div>
              {word.pronunciation && (
                <p className="font-mono text-[0.85rem] text-primary">{word.pronunciation}</p>
              )}
            </div>

            <p className="font-body text-lg font-semibold text-muted-foreground">{word.translation}</p>

            {word.example && (
              <div className="rounded-xl bg-muted/50 p-4">
                <p className="font-body text-[0.9rem] italic text-muted-foreground/80">&ldquo;{word.example}&rdquo;</p>
              </div>
            )}

            <div className="flex flex-wrap gap-1.5 pt-1">
              {word.category && <span className="rounded-full border border-border bg-muted px-2.5 py-1 font-display text-[0.7rem] font-semibold text-muted-foreground">{word.category}</span>}
              {word.topic && <span className="rounded-full bg-accent px-2.5 py-1 font-display text-[0.7rem] font-semibold text-accent-foreground">{word.topic.name}</span>}
              {word.cefrLevel && <span className="rounded-full bg-secondary/40 px-2.5 py-1 font-display text-[0.7rem] font-semibold text-secondary-foreground">{word.cefrLevel}</span>}
              {word.partOfSpeech && <span className="rounded-full border border-border px-2.5 py-1 font-display text-[0.7rem] text-muted-foreground">{word.partOfSpeech}</span>}
            </div>

            <div className="mt-2 flex justify-end">
               <button type="button" onClick={onClose} className="btn-press rounded-xl bg-primary px-6 py-2.5 font-display text-[14px] font-bold uppercase tracking-[0.02em] text-primary-foreground">
                 Close
               </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

export function VocabularyPage() {
  const params = useSearchParams();
  const [words, setWords] = useState<Word[]>([]);
  const [categories, setCategories] = useState<string[]>([]);
  const [topics, setTopics] = useState<Topic[]>([]);
  const [search, setSearch] = useState("");
  const [querySearch, setQuerySearch] = useState("");
  const [suggestedWords, setSuggestedWords] = useState<Word[]>([]);
  const [showSuggestions, setShowSuggestions] = useState(false);
  
  const [category, setCategory] = useState(params.get("category") || "");
  const [difficulty, setDifficulty] = useState("");
  const [topicId, setTopicId] = useState("");
  const [cefr, setCefr] = useState("");
  const [editing, setEditing] = useState<Partial<Word> | null>(null);
  const [deleting, setDeleting] = useState<Word | null>(null);
  const [viewingWord, setViewingWord] = useState<Word | null>(null);
  const [initialLoading, setInitialLoading] = useState(true);
  const [hasMore, setHasMore] = useState(false);
  const [totalWords, setTotalWords] = useState(0);
  const [enrichStatus, setEnrichStatus] = useState<"idle" | "loading" | "found" | "not-found">("idle");
  const [lookupData, setLookupData] = useState<DictionaryLookup | null>(null);

  const requestSeqRef = useRef(0);
  const replacingRef = useRef(false);
  const loadingMoreRef = useRef(false);
  const hasMoreRef = useRef(false);
  const loadedCountRef = useRef(0);
  const { toast } = useToast();

  useEffect(() => {
    const timer = setTimeout(() => {
      setQuerySearch(search.trim());
    }, 500);
    return () => clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    if (!search.trim()) {
      setSuggestedWords([]);
      setShowSuggestions(false);
      return;
    }
    let ignore = false;
    const t = setTimeout(() => {
      api.wordsPage({ search: search.trim(), size: 5 })
        .then((res) => {
          if (!ignore) {
            const normalized = normalizeWordPage(res, 0, 5);
            setSuggestedWords(normalized.content);
            setShowSuggestions(true);
          }
        })
        .catch(() => {});
    }, 300);
    return () => {
      ignore = true;
      clearTimeout(t);
    };
  }, [search]);

  useEffect(() => {
    Promise.all([
      api.categories().catch(() => [] as string[]),
      api.topics().catch(() => [] as Topic[])
    ]).then(([cats, topicList]) => {
      setCategories(cats);
      setTopics(topicList);
    });
  }, []);

  const loadWords = useCallback(async (offset: number, size: number, mode: "replace" | "append") => {
    const requestSeq = ++requestSeqRef.current;
    if (mode === "append") {
      loadingMoreRef.current = true;
    } else {
      replacingRef.current = true;
      loadingMoreRef.current = false;
    }

    try {
      const wordPage = normalizeWordPage(await api.wordsPage({
        offset,
        size,
        search: querySearch,
        category,
        difficulty,
        topicId,
        cefrLevel: cefr
      }), offset, size);

      if (requestSeq !== requestSeqRef.current) return;

      setWords((current) => {
        const nextWords = mode === "append" ? [...current, ...wordPage.content] : wordPage.content;
        loadedCountRef.current = nextWords.length;
        return nextWords;
      });
      setHasMore(!wordPage.last);
      hasMoreRef.current = !wordPage.last;
      setTotalWords(wordPage.totalElements);
    } catch {
      if (requestSeq === requestSeqRef.current) {
        toast("Không thể tải dữ liệu từ backend. Đang hiển thị danh sách hiện có.", "warning");
      }
    } finally {
      if (requestSeq === requestSeqRef.current) {
        setInitialLoading(false);
        replacingRef.current = false;
        loadingMoreRef.current = false;
      }
    }
  }, [category, cefr, difficulty, querySearch, toast, topicId]);

  const loadNextBatch = useCallback(() => {
    if (replacingRef.current || loadingMoreRef.current || !hasMoreRef.current) return;
    if (loadedCountRef.current >= MAX_VISIBLE_WORDS) return;
    void loadWords(loadedCountRef.current, SCROLL_WORD_BATCH_SIZE, "append");
  }, [loadWords]);

  useEffect(() => {
    setWords([]);
    loadedCountRef.current = 0;
    setHasMore(false);
    hasMoreRef.current = false;
    setTotalWords(0);
    void loadWords(0, INITIAL_WORD_COUNT, "replace");
  }, [loadWords]);

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

  // Auto-lookup dictionary when typing a new word (debounced 800ms).
  // Only runs for new words (no id) and fills empty pronunciation/example fields.
  useEffect(() => {
    const isNewWord = !!editing && !editing.id;
    const w = editing?.word?.trim() ?? "";
    if (!isNewWord || w.length < 2) {
      if (!isNewWord) { setEnrichStatus("idle"); setLookupData(null); }
      return;
    }

    setEnrichStatus("loading");
    let cancelled = false;

    const t = setTimeout(() => {
      api.lookupDictionary(w)
        .then((data) => {
          if (cancelled) return;
          setLookupData(data);
          setEditing((prev) => {
            if (!prev || prev.word?.trim() !== w) return prev;
            return {
              ...prev,
              pronunciation: prev.pronunciation?.trim() ? prev.pronunciation : (data.bestPhonetic ?? ""),
              example: prev.example?.trim() ? prev.example : (data.firstExample ?? ""),
            };
          });
          setEnrichStatus("found");
        })
        .catch(() => {
          if (!cancelled) { setLookupData(null); setEnrichStatus("not-found"); }
        });
    }, 800);

    return () => { cancelled = true; clearTimeout(t); };
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [editing?.word, editing?.id]);

  async function saveWord(event: FormEvent) {
    event.preventDefault();
    if (!editing?.word) return;
    try {
      if (editing.id) {
        await api.updateWord(editing.id, editing);
        toast("Đã cập nhật từ vựng.", "success");
      } else {
        const created = await api.createWord(editing);
        toast("Đã thêm từ mới. Đang enrich dữ liệu...", "success");
        api.enrichWord(created.id).catch(() => {});
      }
      setEditing(null);
      setEnrichStatus("idle");
      setLookupData(null);
      await loadWords(0, INITIAL_WORD_COUNT, "replace");
    } catch {
      toast("Không thể lưu từ vựng. Vui lòng thử lại.", "error");
    }
  }

  async function deleteWord() {
    if (!deleting) return;
    try {
      await api.deleteWord(deleting.id);
      setDeleting(null);
      toast("Đã xóa từ vựng.", "success");
      await loadWords(0, INITIAL_WORD_COUNT, "replace");
    } catch {
      toast("Không thể xóa từ vựng. Vui lòng thử lại.", "error");
    }
  }

  async function enrich(id: number) {
    try {
      await api.enrichWord(id);
      toast("Enrichment queued.", "success");
      await loadWords(0, INITIAL_WORD_COUNT, "replace");
    } catch {
      toast("Không thể enrich từ này.", "error");
    }
  }

  if (initialLoading) return <AppShellLoading label="Loading vocabulary..." />;

  return (
    <>
      {/* Hero Section */}
      <div className="-mx-4 -mt-6 lg:-mt-8 xl:-mx-12 bg-primary px-4 py-6 xl:px-12">
        <div className="flex flex-col gap-4 md:flex-row md:items-center">
          <div className="flex-1">
            <h2 className="font-display text-[32px] font-black text-primary-foreground">My Vocabulary</h2>
            <p className="mt-1 font-body text-[17px] text-accent">
              {`${totalWords} từ`}
            </p>
          </div>
          <div className="flex gap-3">
            <div className="relative flex-1 md:w-72">
              <IconSearch className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-muted-foreground" />
              <input
                type="search"
                placeholder="Search words..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                onBlur={() => setTimeout(() => setShowSuggestions(false), 200)}
                onFocus={() => {
                  if (suggestedWords.length > 0) setShowSuggestions(true);
                }}
                className="h-11 w-full rounded-full border-2 border-transparent bg-white pl-10 pr-4 font-body text-[17px] font-medium text-foreground outline-none placeholder:text-muted-foreground/60 focus:border-primary"
              />
              {showSuggestions && suggestedWords.length > 0 && (
                <div className="absolute left-0 mt-2 w-full overflow-hidden rounded-xl border border-border bg-white shadow-xl z-50">
                  <div className="max-h-60 overflow-y-auto py-2">
                    {suggestedWords.map((word) => (
                      <button
                        key={word.id}
                        type="button"
                        className="flex w-full flex-col px-4 py-2 text-left hover:bg-muted focus:bg-muted focus:outline-none"
                        onClick={() => {
                          setSearch(word.word);
                          setQuerySearch(word.word);
                          setShowSuggestions(false);
                        }}
                      >
                        <span className="font-display font-bold text-foreground">{word.word}</span>
                        {word.translation && (
                          <span className="truncate font-body text-[13px] text-muted-foreground">
                            {word.translation}
                          </span>
                        )}
                      </button>
                    ))}
                  </div>
                </div>
              )}
            </div>
            <button
              type="button"
              onClick={() => { setEditing(emptyWord); setEnrichStatus("idle"); setLookupData(null); }}
              className="btn-press flex items-center gap-2 whitespace-nowrap rounded-full border-2 border-white bg-white px-5 font-display text-[15px] font-bold uppercase tracking-[0.05em] text-primary"
            >
              <IconPlus className="h-5 w-5" /> Add Word
            </button>
          </div>
        </div>
      </div>

      {/* Filter Bar */}
      <div className="-mx-4 mb-6 flex flex-nowrap gap-3 overflow-x-auto border-b-2 border-border bg-white px-4 py-4 xl:-mx-12 xl:px-12">
        <Select
          value={category}
          onChange={(e) => setCategory(e.target.value)}
          aria-label="Filter by category"
          className="w-44"
        >
          <option value="">All Categories</option>
          {categories.map((cat) => (
            <option key={cat} value={cat}>{cat}</option>
          ))}
        </Select>
        <Select
          value={difficulty}
          onChange={(e) => setDifficulty(e.target.value)}
          aria-label="Filter by level"
          className="w-44"
        >
          <option value="">All Levels</option>
          <option value="BEGINNER">Beginner</option>
          <option value="INTERMEDIATE">Intermediate</option>
          <option value="ADVANCED">Advanced</option>
        </Select>
        <Select
          value={topicId}
          onChange={(e) => setTopicId(e.target.value)}
          aria-label="Filter by topic"
          className="w-44"
        >
          <option value="">All Topics</option>
          {topics.map((t) => (
            <option key={t.id} value={t.id}>{t.name}</option>
          ))}
        </Select>
        <Select
          value={cefr}
          onChange={(e) => setCefr(e.target.value)}
          aria-label="Filter by CEFR level"
          className="w-40"
        >
          <option value="">All CEFR</option>
          {["A1", "A2", "B1", "B2", "C1", "C2"].map((level) => (
            <option key={level} value={level}>{level}</option>
          ))}
        </Select>
      </div>

      {/* Empty State */}
      {!words.length ? (
        <div className="flex flex-col items-center justify-center gap-5 py-16 text-muted-foreground">
          <IconBooks className="h-16 w-16" strokeWidth={1.5} />
          <p className="font-display text-2xl font-bold text-foreground">No words found</p>
          <p className="font-body text-[17px]">Try adjusting your filters or add new words.</p>
          <button
            type="button"
            onClick={() => { setEditing(emptyWord); setEnrichStatus("idle"); setLookupData(null); }}
            className="btn-press flex items-center gap-2 rounded-xl bg-primary px-6 py-3 font-display text-[15px] font-bold uppercase tracking-[0.02em] text-primary-foreground"
          >
            <IconPlus className="h-5 w-5" /> Add First Word
          </button>
        </div>
      ) : null}

      {/* Word Grid */}
      {words.length ? (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {words.map((word) => (
            <div
              key={word.id}
              onClick={() => setViewingWord(word)}
              className="flex cursor-pointer flex-col gap-2 rounded-xl border-2 border-border bg-white p-5 transition-[border-color,box-shadow,transform] hover:-translate-y-1 hover:border-primary hover:shadow-[0_8px_20px_rgba(0,101,144,0.12)]"
            >
              <WordImage word={word} />

              <div className="flex items-start justify-between gap-2">
                <div className="min-w-0 flex-1">
                  <h3 className="truncate font-display text-[1.1rem] font-bold text-foreground">
                    {word.word}
                  </h3>
                  {word.pronunciation ? (
                    <p className="font-mono text-[0.78rem] text-primary">{word.pronunciation}</p>
                  ) : null}
                </div>
                <DifficultyPill difficulty={word.difficulty} />
              </div>

              <p className="font-body text-[0.9rem] font-semibold text-muted-foreground">
                {word.translation}
              </p>

              {word.example ? (
                <p className="line-clamp-2 font-body text-[0.8rem] italic text-muted-foreground/70">
                  &ldquo;{word.example}&rdquo;
                </p>
              ) : null}

              <div className="flex flex-wrap gap-1">
                {word.category ? (
                  <span className="rounded-full border border-border bg-muted px-2 py-0.5 font-display text-[0.68rem] font-semibold text-muted-foreground">
                    {word.category}
                  </span>
                ) : null}
                {word.topic ? (
                  <span className="rounded-full bg-accent px-2 py-0.5 font-display text-[0.68rem] font-semibold text-accent-foreground">
                    {word.topic.name}
                  </span>
                ) : null}
                {word.cefrLevel ? (
                  <span className="rounded-full bg-secondary/40 px-2 py-0.5 font-display text-[0.68rem] font-semibold text-secondary-foreground">
                    {word.cefrLevel}
                  </span>
                ) : null}
                {word.partOfSpeech ? (
                  <span className="rounded-full bg-muted px-2 py-0.5 font-display text-[0.68rem] text-muted-foreground/70">
                    {word.partOfSpeech}
                  </span>
                ) : null}
                <EnrichmentPill status={word.enrichmentStatus} />
              </div>

              <div className="mt-auto flex gap-2 border-t border-border pt-3">
                <button
                  type="button"
                  onClick={(e) => { e.stopPropagation(); setEditing(word); setEnrichStatus("idle"); setLookupData(null); }}
                  className="flex flex-1 items-center justify-center gap-1.5 rounded-lg border-2 border-border bg-white px-3 py-2 font-display text-[0.75rem] font-bold uppercase tracking-[0.05em] text-primary transition hover:bg-accent"
                >
                  <IconEdit className="h-[15px] w-[15px]" /> Edit
                </button>
                {!word.imageUrl ||
                !word.enrichmentStatus ||
                word.enrichmentStatus === "NOT_REQUESTED" ||
                word.enrichmentStatus === "PARTIAL" ||
                word.enrichmentStatus === "FAILED" ? (
                  <button
                    type="button"
                    onClick={(e) => { e.stopPropagation(); enrich(word.id); }}
                    className="flex flex-1 items-center justify-center gap-1.5 rounded-lg border-2 border-border bg-white px-3 py-2 font-display text-[0.75rem] font-bold uppercase tracking-[0.05em] text-secondary-foreground transition hover:bg-secondary/30"
                  >
                    <IconSparkles className="h-[15px] w-[15px]" /> Enrich
                  </button>
                ) : null}
                <button
                  type="button"
                  onClick={(e) => { e.stopPropagation(); setDeleting(word); }}
                  className="flex flex-1 items-center justify-center gap-1.5 rounded-lg border-2 border-border bg-white px-3 py-2 font-display text-[0.75rem] font-bold uppercase tracking-[0.05em] text-destructive transition hover:bg-destructive/10"
                >
                  <IconTrash className="h-[15px] w-[15px]" /> Delete
                </button>
              </div>
            </div>
          ))}
        </div>
      ) : null}

      {words.length ? (
        <div className="flex h-16 items-center justify-center font-display text-[0.78rem] font-bold uppercase tracking-[0.04em] text-muted-foreground text-center">
          {words.length >= MAX_VISIBLE_WORDS && hasMore
            ? `Showing ${words.length} / ${totalWords} — use search or filters to narrow results`
            : hasMore
              ? `${words.length} / ${totalWords} loaded`
              : "All words loaded"}
        </div>
      ) : null}

      {/* Add / Edit Modal */}
      <Dialog
        open={!!editing}
        title={editing?.id ? "Edit Word" : "Add New Word"}
        onClose={() => { setEditing(null); setEnrichStatus("idle"); setLookupData(null); }}
        className="max-w-lg"
      >
        <form className="flex flex-col gap-5" onSubmit={saveWord}>

          {/* Row 1: Word + Translation */}
          <div className="grid grid-cols-2 gap-4">
            <div className="flex flex-col gap-1.5">
              <label className="font-display text-[13px] font-bold uppercase tracking-widest text-muted-foreground">
                Word *
              </label>
              <div className="relative">
                <input
                  required
                  placeholder="apple"
                  value={editing?.word || ""}
                  onChange={(e) => setEditing({ ...editing, word: e.target.value })}
                  className="w-full rounded-xl border-2 border-border bg-muted py-2.5 pl-3 pr-9 font-body text-[17px] text-foreground outline-none focus:border-primary"
                />
                <span className="absolute right-3 top-1/2 -translate-y-1/2">
                  {enrichStatus === "loading" && (
                    <IconLoader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                  )}
                  {enrichStatus === "found" && (
                    <IconCheck className="h-4 w-4 text-emerald-500" />
                  )}
                  {enrichStatus === "not-found" && (
                    <IconX className="h-4 w-4 text-muted-foreground/50" />
                  )}
                </span>
              </div>
              {enrichStatus === "found" && lookupData?.firstDefinition && (
                <p className="truncate font-body text-[0.7rem] italic text-muted-foreground">
                  {lookupData.firstDefinition}
                </p>
              )}
            </div>
            <div className="flex flex-col gap-1.5">
              <label className="font-display text-[13px] font-bold uppercase tracking-widest text-muted-foreground">
                Translation
              </label>
              <input
                placeholder="Auto-translated after save"
                value={editing?.translation || ""}
                onChange={(e) => setEditing({ ...editing, translation: e.target.value })}
                className="rounded-xl border-2 border-border bg-muted px-3 py-2.5 font-body text-[17px] text-foreground outline-none focus:border-primary"
              />
            </div>
          </div>

          {/* Row 2: Pronunciation + Category */}
          <div className="grid grid-cols-2 gap-4">
            <div className="flex flex-col gap-1.5">
              <div className="flex items-center gap-1.5">
                <label className="font-display text-[13px] font-bold uppercase tracking-widest text-muted-foreground">
                  Pronunciation
                </label>
                {enrichStatus === "found" && lookupData?.bestPhonetic && !editing?.id && (
                  <span className="rounded-full bg-emerald-100 px-1.5 py-0.5 font-display text-[0.6rem] font-bold text-emerald-700">
                    Auto
                  </span>
                )}
              </div>
              <input
                placeholder="/ˈæp.əl/"
                value={editing?.pronunciation || ""}
                onChange={(e) => setEditing({ ...editing, pronunciation: e.target.value })}
                className="rounded-xl border-2 border-border bg-muted px-3 py-2.5 font-body text-[17px] text-foreground outline-none focus:border-primary"
              />
            </div>
            <div className="flex flex-col gap-1.5">
              <label className="font-display text-[13px] font-bold uppercase tracking-widest text-muted-foreground">
                Category
              </label>
              <input
                placeholder="Food"
                value={editing?.category || ""}
                onChange={(e) => setEditing({ ...editing, category: e.target.value })}
                className="rounded-xl border-2 border-border bg-muted px-3 py-2.5 font-body text-[17px] text-foreground outline-none focus:border-primary"
              />
            </div>
          </div>

          {/* Row 3: Difficulty */}
          <div className="flex flex-col gap-1.5">
            <label className="font-display text-[13px] font-bold uppercase tracking-widest text-muted-foreground">
              Difficulty
            </label>
            <select
              value={editing?.difficulty || "INTERMEDIATE"}
              onChange={(e) => setEditing({ ...editing, difficulty: e.target.value as DifficultyLevel })}
              className="cursor-pointer rounded-xl border-2 border-border bg-muted px-3 py-2.5 font-body text-[17px] text-foreground outline-none focus:border-primary"
            >
              <option value="BEGINNER">Beginner</option>
              <option value="INTERMEDIATE">Intermediate</option>
              <option value="ADVANCED">Advanced</option>
            </select>
          </div>

          {/* Row 4: Image URL */}
          <div className="flex flex-col gap-1.5">
            <label className="font-display text-[13px] font-bold uppercase tracking-widest text-muted-foreground">
              Image URL
            </label>
            <input
              type="url"
              placeholder="https://... (để trống — tự fetch sau khi save)"
              value={editing?.imageUrl || ""}
              onChange={(e) => setEditing({ ...editing, imageUrl: e.target.value })}
              className="rounded-xl border-2 border-border bg-muted px-3 py-2.5 font-body text-[15px] text-foreground outline-none focus:border-primary"
            />
            {editing?.imageUrl ? (
              <img
                src={editing.imageUrl}
                alt="preview"
                className="h-28 w-full rounded-lg border border-border object-cover"
                onError={(e) => { (e.currentTarget as HTMLImageElement).style.display = "none"; }}
              />
            ) : (
              <p className="font-body text-[0.7rem] text-muted-foreground">
                Để trống — ảnh sẽ được tự động tìm kiếm khi enrich sau khi lưu.
              </p>
            )}
          </div>

          {/* Row 5: Example Sentence */}
          <div className="flex flex-col gap-1.5">
            <div className="flex items-center gap-1.5">
              <label className="font-display text-[13px] font-bold uppercase tracking-widest text-muted-foreground">
                Example Sentence
              </label>
              {enrichStatus === "found" && lookupData?.firstExample && !editing?.id && (
                <span className="rounded-full bg-emerald-100 px-1.5 py-0.5 font-display text-[0.6rem] font-bold text-emerald-700">
                  Auto
                </span>
              )}
            </div>
            <input
              placeholder="I eat an apple every day."
              value={editing?.example || ""}
              onChange={(e) => setEditing({ ...editing, example: e.target.value })}
              className="rounded-xl border-2 border-border bg-muted px-3 py-2.5 font-body text-[17px] text-foreground outline-none focus:border-primary"
            />
          </div>

          {/* Auto-enrich hint */}
          {!editing?.id && (
            <div className="flex items-center gap-2 rounded-xl bg-secondary/20 px-3 py-2.5">
              <IconSparkles className="h-4 w-4 shrink-0 text-secondary-foreground/70" />
              <p className="font-body text-[0.75rem] text-muted-foreground">
                Sau khi lưu, từ sẽ được tự động enrich: dịch nghĩa, IPA, ảnh, từ loại.
              </p>
            </div>
          )}

          <div className="flex justify-end gap-3 pt-1">
            <button
              type="button"
              onClick={() => { setEditing(null); setEnrichStatus("idle"); setLookupData(null); }}
              className="rounded-xl border-2 border-border px-6 py-2.5 font-display text-[15px] font-bold uppercase tracking-[0.02em] text-muted-foreground transition hover:bg-muted"
            >
              Cancel
            </button>
            <button
              type="submit"
              className="btn-press rounded-xl bg-primary px-6 py-2.5 font-display text-[15px] font-bold uppercase tracking-[0.02em] text-primary-foreground"
            >
              Save Word
            </button>
          </div>
        </form>
      </Dialog>

      {/* Delete Modal */}
      <Dialog
        open={!!deleting}
        title="Delete Word?"
        onClose={() => setDeleting(null)}
        className="max-w-sm"
      >
        <div className="flex flex-col gap-5">
          <div className="flex items-center gap-4">
            <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-destructive/15">
              <IconTrash className="h-5 w-5 text-destructive" />
            </div>
            <p className="font-body text-[17px] text-muted-foreground">
              Delete <strong className="text-foreground">{deleting?.word}</strong>? This action
              cannot be undone.
            </p>
          </div>
          <div className="flex justify-end gap-3">
            <button
              type="button"
              onClick={() => setDeleting(null)}
              className="rounded-xl border-2 border-border px-6 py-2.5 font-display text-[15px] font-bold uppercase tracking-[0.02em] text-muted-foreground transition hover:bg-muted"
            >
              Cancel
            </button>
            <button
              type="button"
              onClick={deleteWord}
              className="btn-press-error rounded-xl bg-destructive px-6 py-2.5 font-display text-[15px] font-bold uppercase tracking-[0.02em] text-white"
            >
              Delete
            </button>
          </div>
        </div>
      </Dialog>

      <WordDetail3DModal word={viewingWord} onClose={() => setViewingWord(null)} />
    </>
  );
}
