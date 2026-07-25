"use client";

import { IconArrowLeft, IconCircleCheck, IconCircleX, IconPlayerPlay, IconPuzzle } from "@tabler/icons-react";
import { useEffect, useMemo, useRef, useState } from "react";
import { AppShellLoading } from "@/components/layout/app-shell";
import { Dialog } from "@/components/ui/dialog";
import { Select } from "@/components/ui/select";
import { useToast } from "@/components/ui/toast";
import { api } from "@/lib/api";
import type { Topic, Word } from "@/types/api";

const AUTO_ADVANCE_MS = 1600;
const LETTERS = ["A", "B", "C", "D"] as const;

type Screen = "setup" | "playing" | "done";
type QuizType = "en-vi" | "vi-en";

function shuffled<T>(arr: T[]): T[] {
  const out = [...arr];
  for (let i = out.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [out[i], out[j]] = [out[j], out[i]];
  }
  return out;
}

function QuizHero() {
  return (
    <div className="mb-6 flex items-center gap-6 rounded-xl bg-primary p-6">
      <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-accent">
        <IconPuzzle className="h-9 w-9 text-primary" />
      </div>
      <div>
        <h2 className="font-display text-[24px] font-bold text-primary-foreground">Vocabulary Quiz</h2>
        <p className="font-body text-[17px] text-accent">Test your knowledge with multiple choice</p>
      </div>
    </div>
  );
}

export function QuizPage() {
  const [categories, setCategories] = useState<string[]>([]);
  const [topics, setTopics] = useState<Topic[]>([]);
  const [category, setCategory] = useState("");
  const [difficulty, setDifficulty] = useState("");
  const [topicId, setTopicId] = useState<number | null>(null);
  const [cefr, setCefr] = useState("");
  const [count, setCount] = useState(10);
  const [quizType, setQuizType] = useState<QuizType>("en-vi");
  const [screen, setScreen] = useState<Screen>("setup");
  const [sessionId, setSessionId] = useState<number | null>(null);
  const [words, setWords] = useState<Word[]>([]);
  const [distractors, setDistractors] = useState<Word[]>([]);
  const [index, setIndex] = useState(0);
  const [selected, setSelected] = useState<string | null>(null);
  const [correctCount, setCorrectCount] = useState(0);
  const [incorrectCount, setIncorrectCount] = useState(0);
  const [loading, setLoading] = useState(true);
  const [starting, setStarting] = useState(false);
  const [showExitConfirm, setShowExitConfirm] = useState(false);
  const { toast } = useToast();
  const timerRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

  useEffect(() => {
    Promise.all([
      api.categories().catch(() => [] as string[]),
      api.topics().catch(() => [] as Topic[]),
    ]).then(([cats, tops]) => {
      setCategories(cats);
      setTopics(tops);
      setLoading(false);
    }).catch(() => setLoading(false));
    return () => clearTimeout(timerRef.current);
  }, []);

  const current = words[index];

  const correctAnswer = useMemo(() => {
    if (!current) return "";
    return quizType === "en-vi" ? current.translation || "" : current.word;
  }, [current, quizType]);

  const choices = useMemo(() => {
    if (!current) return [];
    const extract = (w: Word) => (quizType === "en-vi" ? w.translation : w.word);
    const sessionPool = words
      .filter((w) => w.id !== current.id)
      .map(extract)
      .filter((v): v is string => Boolean(v));
    const distractorPool = distractors
      .filter((w) => w.id !== current.id)
      .map(extract)
      .filter((v): v is string => Boolean(v));
    const unique = [...new Set([...sessionPool, ...distractorPool])].filter(
      (v) => v !== correctAnswer
    );
    return shuffled([correctAnswer, ...shuffled(unique).slice(0, 3)]);
  }, [current, words, distractors, quizType, correctAnswer]);

  async function start() {
    if (starting) return;
    setStarting(true);
    try {
      const data = await api.startQuiz({
        count,
        category,
        difficulty,
        topicId: topicId ?? undefined,
        cefrLevel: cefr || undefined,
        type: quizType
      });
      // The backend already filtered for valid words matching the type
      const validWords = data.words;

      if (!validWords.length) {
        toast("Không có từ phù hợp với bộ lọc quiz này.", "warning");
        return;
      }

      setSessionId(data.sessionId);
      setWords(validWords);
      setDistractors(data.distractors ?? []);
      setIndex(0);
      setSelected(null);
      setCorrectCount(0);
      setIncorrectCount(0);
      setScreen("playing");
    } catch {
      toast("Không thể bắt đầu quiz. Vui lòng thử lại.", "error");
    } finally {
      setStarting(false);
    }
  }

  async function answer(choice: string) {
    if (!current || !sessionId || selected !== null) return;
    const isCorrect = choice === correctAnswer;
    setSelected(choice);
    if (isCorrect) setCorrectCount((v) => v + 1);
    else setIncorrectCount((v) => v + 1);
    await api.submitQuizAnswer(sessionId, current.id, isCorrect);
    // Removed duplicate api.reviewWord call as backend does it implicitly.

    const nextIndex = index + 1;
    timerRef.current = setTimeout(async () => {
      if (nextIndex >= words.length) {
        await api.completeQuiz(sessionId).catch(() => undefined);
        api.checkInStreak().catch(() => undefined);
        setScreen("done");
      } else {
        setIndex(nextIndex);
        setSelected(null);
      }
    }, AUTO_ADVANCE_MS);
  }

  async function advance() {
    clearTimeout(timerRef.current);
    const nextIndex = index + 1;
    if (nextIndex >= words.length) {
      if (sessionId) await api.completeQuiz(sessionId).catch(() => undefined);
      api.checkInStreak().catch(() => undefined);
      setScreen("done");
      return;
    }
    setIndex(nextIndex);
    setSelected(null);
  }

  // ──── SETUP SCREEN ────
  if (screen === "setup") {
    return (
      <div className="mx-auto max-w-lg">
          <QuizHero />
          <div className="rounded-xl border-2 border-border bg-white p-6">
            <h3 className="mb-5 font-display text-xl font-bold text-foreground">Quiz Setup</h3>
            <div className="flex flex-col gap-4">
              <div className="flex flex-col gap-1.5">
                <label className="font-display text-[13px] font-bold uppercase tracking-widest text-muted-foreground">
                  Category
                </label>
                <Select
                  value={category}
                  onChange={(e) => setCategory(e.target.value)}
                >
                  <option value="">All Categories</option>
                  {categories.map((cat) => (
                    <option key={cat} value={cat}>{cat}</option>
                  ))}
                </Select>
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="font-display text-[13px] font-bold uppercase tracking-widest text-muted-foreground">
                  Difficulty
                </label>
                <Select
                  value={difficulty}
                  onChange={(e) => setDifficulty(e.target.value)}
                >
                  <option value="">All Levels</option>
                  <option value="BEGINNER">Beginner</option>
                  <option value="INTERMEDIATE">Intermediate</option>
                  <option value="ADVANCED">Advanced</option>
                </Select>
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="font-display text-[13px] font-bold uppercase tracking-widest text-muted-foreground">
                  Topic
                </label>
                <Select
                  value={topicId ?? ""}
                  onChange={(e) => setTopicId(e.target.value ? Number(e.target.value) : null)}
                >
                  <option value="">All Topics</option>
                  {topics.map((t) => (
                    <option key={t.id} value={t.id}>{t.name}</option>
                  ))}
                </Select>
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="font-display text-[13px] font-bold uppercase tracking-widest text-muted-foreground">
                  CEFR Level
                </label>
                <Select
                  value={cefr}
                  onChange={(e) => setCefr(e.target.value)}
                >
                  <option value="">All CEFR</option>
                  {["A1", "A2", "B1", "B2", "C1", "C2"].map((lvl) => (
                    <option key={lvl} value={lvl}>{lvl}</option>
                  ))}
                </Select>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="flex flex-col gap-1.5">
                  <label className="font-display text-[13px] font-bold uppercase tracking-widest text-muted-foreground">
                    Questions
                  </label>
                  <input
                    type="number"
                    min={5}
                    max={50}
                    value={count}
                    onChange={(e) =>
                      setCount(Math.max(5, Math.min(50, Number(e.target.value))))
                    }
                    className="rounded-xl border-2 border-border bg-muted px-3 py-2.5 font-body text-[17px] text-foreground outline-none focus:border-primary"
                  />
                </div>
                <div className="flex flex-col gap-1.5">
                  <label className="font-display text-[13px] font-bold uppercase tracking-widest text-muted-foreground">
                    Type
                  </label>
                  <Select
                    value={quizType}
                    onChange={(e) => setQuizType(e.target.value as QuizType)}
                  >
                    <option value="en-vi">EN to VI</option>
                    <option value="vi-en">VI to EN</option>
                  </Select>
                </div>
              </div>
              <button
                type="button"
                onClick={start}
                disabled={starting}
                className="btn-press mt-2 flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-3 font-display text-[17px] font-bold uppercase tracking-[0.02em] text-primary-foreground"
              >
                <IconPlayerPlay className="h-5 w-5" />
                {starting ? "Starting..." : "Start Quiz"}
              </button>
            </div>
          </div>
      </div>
    );
  }

  // ──── DONE SCREEN ────
  if (screen === "done") {
    const total = correctCount + incorrectCount;
    const pct = total > 0 ? Math.round((correctCount / total) * 100) : 0;
    const scoreCls =
      pct >= 80
        ? "border-primary bg-accent text-primary"
        : pct >= 60
          ? "border-secondary bg-secondary/20 text-secondary-foreground"
          : "border-destructive bg-destructive/10 text-destructive";
    return (
      <div className="mx-auto mt-8 max-w-lg">
          <div className="flex flex-col items-center gap-6 rounded-xl border-2 border-border bg-white p-8">
            <div
              className={`flex h-36 w-36 flex-col items-center justify-center rounded-full border-4 ${scoreCls}`}
            >
              <span className="font-display text-[42px] font-black leading-none">{pct}%</span>
              <span className="font-display text-[13px] font-bold uppercase tracking-widest">Score</span>
            </div>
            <h3 className="font-display text-2xl font-bold text-foreground">Quiz Complete!</h3>
            <div className="grid w-full grid-cols-3 gap-3 text-center">
              <div className="flex flex-col gap-1 rounded-xl bg-muted p-4">
                <span className="font-display text-[28px] font-black text-foreground">{total}</span>
                <span className="font-display text-[12px] font-bold uppercase tracking-widest text-muted-foreground">Total</span>
              </div>
              <div className="flex flex-col gap-1 rounded-xl bg-accent p-4">
                <span className="font-display text-[28px] font-black text-primary">{correctCount}</span>
                <span className="font-display text-[12px] font-bold uppercase tracking-widest text-accent-foreground">Correct</span>
              </div>
              <div className="flex flex-col gap-1 rounded-xl bg-destructive/10 p-4">
                <span className="font-display text-[28px] font-black text-destructive">{incorrectCount}</span>
                <span className="font-display text-[12px] font-bold uppercase tracking-widest text-destructive/80">Wrong</span>
              </div>
            </div>
            <div className="flex w-full flex-col gap-3">
              <button
                type="button"
                onClick={start}
                className="btn-press w-full rounded-xl bg-primary py-3 font-display text-[17px] font-bold uppercase tracking-[0.02em] text-primary-foreground"
              >
                Play Again
              </button>
              <button
                type="button"
                onClick={() => setScreen("setup")}
                className="w-full rounded-xl border-2 border-border bg-white py-3 font-display text-[17px] font-bold uppercase tracking-[0.02em] text-primary transition hover:bg-accent"
              >
                Settings
              </button>
            </div>
          </div>
      </div>
    );
  }

  // ──── PLAYING SCREEN ────
  if (!current) return null;

  const isAnswered = selected !== null;
  const isCorrectAnswer = selected === correctAnswer;
  const questionText = quizType === "en-vi" ? current.word : current.translation || "—";
  const instruction =
    quizType === "en-vi"
      ? "Choose the correct Vietnamese translation"
      : "Choose the correct English word";
  const progressPct = (index / words.length) * 100;

  if (loading) return <AppShellLoading label="Loading quiz..." />;

  return (
    <>
    <div className="mx-auto mt-6 max-w-xl space-y-4">
        {/* Progress Row */}
        <div className="flex items-center gap-3">
          <button
            type="button"
            onClick={() => setShowExitConfirm(true)}
            className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border-2 border-border bg-white text-muted-foreground transition hover:bg-muted"
            aria-label="Back to settings"
          >
            <IconArrowLeft className="h-5 w-5" />
          </button>
          <div className="flex-1">
            <div className="h-3 overflow-hidden rounded-full bg-muted">
              <div
                className="h-full rounded-full bg-primary transition-[width] duration-300"
                style={{ width: `${progressPct}%` }}
              />
            </div>
          </div>
          <span className="font-display text-[15px] font-bold text-muted-foreground">
            {index + 1} / {words.length}
          </span>
        </div>

        {/* Score Counters */}
        <div className="grid grid-cols-2 gap-3">
          <div className="flex items-center justify-center gap-2 rounded-xl bg-destructive/10 p-3">
            <IconCircleX className="h-5 w-5 text-destructive" />
            <span className="font-display text-[17px] font-black text-destructive">{incorrectCount}</span>
            <span className="font-display text-[13px] font-bold uppercase tracking-widest text-destructive/80">
              Wrong
            </span>
          </div>
          <div className="flex items-center justify-center gap-2 rounded-xl bg-accent p-3">
            <IconCircleCheck className="h-5 w-5 text-primary" />
            <span className="font-display text-[17px] font-black text-primary">{correctCount}</span>
            <span className="font-display text-[13px] font-bold uppercase tracking-widest text-accent-foreground">
              Correct
            </span>
          </div>
        </div>

        {/* Question Card */}
        <div className="flex flex-col items-center gap-2 rounded-xl border-2 border-border bg-white p-6 text-center">
          <p className="font-display text-[13px] font-bold uppercase tracking-widest text-muted-foreground">
            {instruction}
          </p>
          <p className="font-display text-[32px] font-black leading-tight text-foreground">
            {questionText}
          </p>
          {quizType === "en-vi" && current.pronunciation ? (
            <p className="font-mono text-[17px] text-primary">{current.pronunciation}</p>
          ) : null}
          {current.category ? (
            <span className="rounded-full bg-muted px-3 py-1 font-display text-[12px] font-bold uppercase tracking-widest text-muted-foreground">
              {current.category}
            </span>
          ) : null}
        </div>

        {/* Options Grid */}
        <div className="grid grid-cols-2 gap-3">
          {choices.map((choice, i) => {
            const isThisCorrect = choice === correctAnswer;
            const isThisSelected = choice === selected;
            const btnCls = isAnswered
              ? isThisCorrect
                ? "border-primary bg-accent text-accent-foreground cursor-default"
                : isThisSelected
                  ? "border-destructive bg-destructive/10 text-destructive cursor-default"
                  : "border-border bg-white opacity-60 cursor-default"
              : "border-border bg-white hover:border-primary hover:bg-muted cursor-pointer";
            const letterCls = isAnswered
              ? isThisCorrect
                ? "bg-primary text-primary-foreground"
                : isThisSelected
                  ? "bg-destructive text-white"
                  : "bg-muted text-muted-foreground"
              : "bg-muted text-muted-foreground";
            return (
              <button
                key={`${choice}-${i}`}
                type="button"
                onClick={() => answer(choice)}
                disabled={isAnswered}
                className={`flex items-center gap-3 rounded-xl border-2 p-4 text-left font-body text-[17px] font-medium transition ${btnCls}`}
              >
                <span
                  className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-lg font-display text-[13px] font-bold ${letterCls}`}
                >
                  {LETTERS[i]}
                </span>
                <span className="line-clamp-3 break-words text-left">
                  {choice}
                </span>
              </button>
            );
          })}
        </div>

        {/* Feedback */}
        {isAnswered ? (
          <div
            className={`rounded-xl border-2 p-4 ${
              isCorrectAnswer ? "border-primary bg-accent" : "border-destructive bg-destructive/10"
            }`}
          >
            <div className="flex items-center gap-2">
              {isCorrectAnswer ? (
                <IconCircleCheck className="h-5 w-5 shrink-0 text-primary" />
              ) : (
                <IconCircleX className="h-5 w-5 shrink-0 text-destructive" />
              )}
              <p
                className={`font-display font-bold ${
                  isCorrectAnswer ? "text-accent-foreground" : "text-destructive"
                }`}
              >
                {isCorrectAnswer ? "Correct!" : `Incorrect. Answer: ${correctAnswer}`}
              </p>
            </div>
            <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-black/10">
              <div
                key={selected}
                className={`h-full rounded-full ${isCorrectAnswer ? "bg-primary" : "bg-destructive"}`}
                style={{ animation: `drainBar ${AUTO_ADVANCE_MS}ms linear forwards` }}
              />
            </div>
          </div>
        ) : null}

        {/* Next Button */}
        {isAnswered ? (
          <button
            type="button"
            onClick={advance}
            className="btn-press w-full rounded-xl bg-primary py-3 font-display text-[17px] font-bold uppercase tracking-[0.02em] text-primary-foreground"
          >
            {index + 1 >= words.length ? "See Results" : "Next Question"}
          </button>
        ) : null}
    </div>

      <Dialog
        open={showExitConfirm}
        title="Thoát quiz?"
        onClose={() => setShowExitConfirm(false)}
        className="max-w-sm"
      >
        <p className="font-body text-[17px] text-muted-foreground">
          Tiến trình hiện tại sẽ bị mất. Bạn có chắc muốn thoát không?
        </p>
        <div className="mt-5 flex gap-3">
          <button
            type="button"
            onClick={() => setShowExitConfirm(false)}
            className="flex-1 rounded-xl border-2 border-border bg-white py-2.5 font-display text-[15px] font-bold uppercase tracking-widest text-foreground transition hover:bg-muted"
          >
            Tiếp tục
          </button>
          <button
            type="button"
            onClick={() => { clearTimeout(timerRef.current); setShowExitConfirm(false); setScreen("setup"); }}
            className="flex-1 rounded-xl bg-destructive py-2.5 font-display text-[15px] font-bold uppercase tracking-widest text-white transition hover:opacity-90 btn-press-error"
          >
            Thoát
          </button>
        </div>
      </Dialog>
    </>
  );
}
