"use client";

import { IconArrowLeft, IconCircleCheck, IconCircleX } from "@tabler/icons-react";
import { Dialog } from "@/components/ui/dialog";
import type { QuizAnswerFeedback, QuizQuestion } from "@/lib/api";
import type { QuizType } from "./quiz-setup-screen";

const LETTERS = ["A", "B", "C", "D"] as const;

interface QuizPlayingScreenProps {
  current: QuizQuestion;
  index: number;
  totalQuestions: number;
  selected: string | null;
  feedback: QuizAnswerFeedback | null;
  correctCount: number;
  incorrectCount: number;
  quizType: QuizType;
  autoAdvanceMs: number;
  showExitConfirm: boolean;
  onAnswer: (choice: string) => void;
  onAdvance: () => void;
  onRequestExit: () => void;
  onCancelExit: () => void;
  onConfirmExit: () => void;
}

export function QuizPlayingScreen({
  current, index, totalQuestions, selected, feedback, correctCount, incorrectCount,
  quizType, autoAdvanceMs, showExitConfirm, onAnswer, onAdvance, onRequestExit, onCancelExit, onConfirmExit,
}: QuizPlayingScreenProps) {
  const isAnswered = feedback !== null;
  const isCorrectAnswer = feedback?.isCorrect ?? false;
  const instruction =
    quizType === "en-vi"
      ? "Choose the correct Vietnamese translation"
      : "Choose the correct English word";
  const progressPct = (index / totalQuestions) * 100;

  return (
    <>
    <div className="mx-auto mt-6 max-w-xl space-y-4">
        {/* Progress Row */}
        <div className="flex items-center gap-3">
          <button
            type="button"
            onClick={onRequestExit}
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
            {index + 1} / {totalQuestions}
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
            {current.prompt}
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
          {current.choices.map((choice, i) => {
            const isThisCorrect = feedback?.correctAnswer === choice;
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
                onClick={() => onAnswer(choice)}
                disabled={selected !== null}
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
        {feedback ? (
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
                {isCorrectAnswer ? "Correct!" : `Incorrect. Answer: ${feedback?.correctAnswer ?? "—"}`}
              </p>
            </div>
            <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-black/10">
              <div
                key={selected}
                className={`h-full rounded-full ${isCorrectAnswer ? "bg-primary" : "bg-destructive"}`}
                style={{ animation: `drainBar ${autoAdvanceMs}ms linear forwards` }}
              />
            </div>
          </div>
        ) : null}

        {/* Next Button */}
        {feedback ? (
          <button
            type="button"
            onClick={onAdvance}
            className="btn-press w-full rounded-xl bg-primary py-3 font-display text-[17px] font-bold uppercase tracking-[0.02em] text-primary-foreground"
          >
            {index + 1 >= totalQuestions ? "See Results" : "Next Question"}
          </button>
        ) : null}
    </div>

      <Dialog
        open={showExitConfirm}
        title="Thoát quiz?"
        onClose={onCancelExit}
        className="max-w-sm"
      >
        <p className="font-body text-[17px] text-muted-foreground">
          Tiến trình hiện tại sẽ bị mất. Bạn có chắc muốn thoát không?
        </p>
        <div className="mt-5 flex gap-3">
          <button
            type="button"
            onClick={onCancelExit}
            className="flex-1 rounded-xl border-2 border-border bg-white py-2.5 font-display text-[15px] font-bold uppercase tracking-widest text-foreground transition hover:bg-muted"
          >
            Tiếp tục
          </button>
          <button
            type="button"
            onClick={onConfirmExit}
            className="flex-1 rounded-xl bg-destructive py-2.5 font-display text-[15px] font-bold uppercase tracking-widest text-white transition hover:opacity-90 btn-press-error"
          >
            Thoát
          </button>
        </div>
      </Dialog>
    </>
  );
}
