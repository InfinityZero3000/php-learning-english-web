"use client";

import { IconArrowLeft, IconCircleCheck, IconPlayerPlay, IconPuzzle } from "@tabler/icons-react";
import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { AppShellLoading } from "@/components/layout/app-shell";
import { useToast } from "@/components/ui/toast";
import { ApiError, fetchQuiz, fetchQuizzes, submitQuiz } from "@/lib/api";
import type { QuizResource, AttemptResource } from "@/lib/api";
import { useAuth } from "@/features/auth/auth-context";
import { loginHref } from "@/features/auth/route-policy";

const LETTERS = ["A", "B", "C", "D"] as const;

type Screen = "setup" | "playing" | "done";

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

function ResultHero({ attempt }: { attempt: AttemptResource }) {
  const score = attempt.score;
  return (
    <div className="mb-6 rounded-xl bg-primary p-6 text-primary-foreground">
      <h2 className="font-display text-[28px] font-bold">Quiz Complete!</h2>
      <div className="mt-4 flex gap-6">
        <div className="text-center">
          <p className="font-display text-[32px] font-bold text-accent">{score}</p>
          <p className="text-sm font-semibold text-sky-100">Score</p>
        </div>
      </div>
    </div>
  );
}

export function QuizPage() {
  const router = useRouter();
  const { status } = useAuth();
  const { toast } = useToast();

  const [screen, setScreen] = useState<Screen>("setup");
  const [quiz, setQuiz] = useState<QuizResource | null>(null);
  const [questionIndex, setQuestionIndex] = useState(0);
  const [selectedAnswerId, setSelectedAnswerId] = useState<number | null>(null);
  const [answers, setAnswers] = useState<Array<{ question_id: number; answer_id: number | null }>>([]);
  const [attempt, setAttempt] = useState<AttemptResource | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [loadingQuiz, setLoadingQuiz] = useState(false);
  const [selectedQuizId, setSelectedQuizId] = useState<number | null>(null);
  const [availableQuizzes, setAvailableQuizzes] = useState<QuizResource[]>([]);

  useEffect(() => {
    if (status !== "authenticated") return;
    fetchQuizzes()
      .then(setAvailableQuizzes)
      .catch((reason) => {
        if (reason instanceof ApiError && reason.status === 401) router.push(loginHref("/quiz"));
        else toast("Could not load available quizzes.", "error");
      });
  }, [router, status, toast]);

  async function startQuiz() {
    if (status !== "authenticated") {
      router.push(loginHref("/quiz"));
      return;
    }
    if (!selectedQuizId || submitting) return;
    setLoadingQuiz(true);
    setSubmitting(true);
    try {
      const quizData = await fetchQuiz(selectedQuizId);
      if (!quizData.questions || quizData.questions.length === 0) {
        toast("This quiz has no questions.", "warning");
        return;
      }
      setQuiz(quizData);
      setQuestionIndex(0);
      setSelectedAnswerId(null);
      setAnswers([]);
      setAttempt(null);
      setScreen("playing");
    } catch (err) {
      if (err instanceof ApiError) {
        if (err.status === 401) {
          router.push(loginHref("/quiz"));
          return;
        }
        if (err.status === 404) toast("Quiz not found.", "error");
        else toast(err.message || "Failed to load quiz.", "error");
      } else {
        toast("Network error. Please try again.", "error");
      }
    } finally {
      setSubmitting(false);
      setLoadingQuiz(false);
    }
  }

  const currentQuestion = quiz?.questions?.[questionIndex] ?? null;
  const totalQuestions = quiz?.questions?.length ?? 0;

  function handleAnswer(answerId: number | null) {
    if (selectedAnswerId !== null || !currentQuestion) return;
    setSelectedAnswerId(answerId);
    const newAnswers = [...answers, { question_id: currentQuestion.id, answer_id: answerId }];
    setAnswers(newAnswers);
  }

  async function advanceOrFinish() {
    const nextIndex = questionIndex + 1;
    if (nextIndex >= totalQuestions) {
      setSubmitting(true);
      try {
        const result = await submitQuiz(quiz!.id, { answers });
        setAttempt(result);
        setScreen("done");
        toast("Quiz submitted!", "success");
      } catch (err) {
        if (err instanceof ApiError && err.status === 401) {
          router.push(loginHref("/quiz"));
          return;
        }
        toast("Failed to submit quiz. Check your answers.", "error");
      } finally {
        setSubmitting(false);
      }
    } else {
      setQuestionIndex(nextIndex);
      setSelectedAnswerId(null);
    }
  }

  // ──── LOADING STATE ────
  if (loadingQuiz) return <AppShellLoading label="Loading quiz..." />;

  // ──── SETUP SCREEN ────
  if (screen === "setup") {
    return (
      <div className="mx-auto max-w-lg">
        <QuizHero />
        <div className="rounded-xl border-2 border-border bg-white p-6">
          <h3 className="mb-5 font-display text-xl font-bold text-foreground">Quiz Setup</h3>
          <div className="flex flex-col gap-4">
            <div className="flex flex-col gap-1.5">
              <label htmlFor="quiz-select" className="font-display text-[13px] font-bold uppercase tracking-widest text-muted-foreground">
                Choose a quiz
              </label>
              <select
                id="quiz-select"
                className="rounded-xl border-2 border-border bg-muted/30 px-4 py-3 font-bold text-foreground focus:border-primary focus:outline-none"
                value={selectedQuizId ?? ""}
                onChange={(e) => setSelectedQuizId(e.target.value ? Number(e.target.value) : null)}
              >
                <option value="">Select an available quiz</option>
                {availableQuizzes.map((item) => (
                  <option key={item.id} value={item.id}>{item.title ?? `Quiz #${item.id}`} · {item.questions_count ?? 0} questions</option>
                ))}
              </select>
              {availableQuizzes.length === 0 ? <p className="text-xs font-semibold text-muted-foreground">No published quizzes are available.</p> : null}
            </div>

            <button
              type="button"
              disabled={!selectedQuizId || submitting}
              onClick={startQuiz}
              className="mt-2 flex h-14 w-full items-center justify-center gap-2.5 rounded-xl bg-primary font-display text-[15px] font-bold uppercase tracking-[0.05em] text-primary-foreground transition hover:brightness-110 disabled:opacity-40"
            >
              <IconPlayerPlay className="h-5 w-5" />
              {submitting ? "Loading..." : "Start Quiz"}
            </button>
          </div>
        </div>
      </div>
    );
  }

  // ──── DONE SCREEN ────
  if (screen === "done" && attempt) {
    return (
      <div className="mx-auto max-w-lg">
        <ResultHero attempt={attempt} />
        <div className="flex gap-3">
          <button
            type="button"
            onClick={() => setScreen("setup")}
            className="flex h-12 items-center justify-center gap-2 rounded-xl border-2 border-border px-6 font-display text-sm font-bold uppercase"
          >
            <IconArrowLeft className="h-5 w-5" />
            Back
          </button>
          <button
            type="button"
            onClick={() => {
              setScreen("setup");
              setSelectedQuizId(null);
              setQuiz(null);
              setAttempt(null);
            }}
            className="flex h-12 flex-1 items-center justify-center gap-2 rounded-xl bg-primary font-display text-sm font-bold uppercase text-primary-foreground"
          >
            <IconPlayerPlay className="h-5 w-5" />
            New Quiz
          </button>
        </div>
      </div>
    );
  }

  // ──── PLAYING SCREEN (empty/invalid state) ────
  if (!quiz || !currentQuestion) {
    return (
      <div className="mx-auto max-w-lg py-20 text-center">
        <p className="font-semibold text-muted-foreground">Quiz not found. Go back and select a quiz.</p>
        <button
          type="button"
          onClick={() => {
            setScreen("setup");
            setQuiz(null);
          }}
          className="mt-4 rounded-xl bg-primary px-6 py-2.5 text-sm font-bold text-primary-foreground"
        >
          Go Back
        </button>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-lg">
      {/* Progress bar */}
      <div className="mb-4 flex items-center justify-between">
        <span className="font-display text-sm font-bold uppercase text-muted-foreground">
          Question {questionIndex + 1} of {totalQuestions}
        </span>
        <button
          type="button"
          onClick={() => setScreen("setup")}
          className="rounded-xl border border-border px-3 py-1 font-display text-xs font-bold uppercase text-muted-foreground"
        >
          Exit
        </button>
      </div>
      <div className="mb-5 h-2 rounded-full bg-muted">
        <div
          className="h-full rounded-full bg-primary transition-all duration-300"
          style={{ width: `${((questionIndex + (selectedAnswerId !== null ? 1 : 0)) / totalQuestions) * 100}%` }}
        />
      </div>

      {/* Question card */}
      <div className="rounded-xl border-2 border-border bg-white p-6">
        <p className="mb-6 font-display text-[18px] font-bold leading-relaxed text-foreground">
          {currentQuestion.content}
        </p>

        <div className="flex flex-col gap-3">
          {(currentQuestion.answers ?? []).map((answer, i) => {
            const letter = LETTERS[i] ?? String(i);
            let borderClass = "border-2 border-muted hover:border-primary hover:bg-muted";
            if (selectedAnswerId !== null) {
              borderClass = answer.id === selectedAnswerId
                ? "border-2 border-primary bg-accent"
                : "border-2 border-muted opacity-60";
            }
            return (
              <button
                key={answer.id}
                type="button"
                disabled={selectedAnswerId !== null}
                onClick={() => handleAnswer(answer.id)}
                className={`flex items-center gap-3 rounded-xl p-4 text-left transition ${borderClass}`}
              >
                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted font-display text-sm font-bold text-muted-foreground">
                  {letter}
                </span>
                <span className="font-bold text-foreground">{answer.content}</span>
              </button>
            );
          })}
        </div>

        {/* Feedback */}
        {selectedAnswerId !== null && (
          <div className="mt-5 rounded-xl bg-muted/50 p-4">
            <div className="flex items-center gap-2 text-primary">
              <IconCircleCheck className="h-5 w-5" />
              <span className="font-display font-bold uppercase">Answer recorded</span>
              </div>
          </div>
        )}

        {/* Next / Submit button */}
        {selectedAnswerId !== null && (
          <button
            type="button"
            onClick={advanceOrFinish}
            disabled={submitting}
            className="mt-5 flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary font-display text-[14px] font-bold uppercase tracking-[0.05em] text-primary-foreground transition hover:brightness-110 disabled:opacity-40"
          >
            {submitting ? "Submitting..." : questionIndex + 1 >= totalQuestions ? "Submit Quiz" : "Next Question"}
          </button>
        )}
      </div>
    </div>
  );
}
