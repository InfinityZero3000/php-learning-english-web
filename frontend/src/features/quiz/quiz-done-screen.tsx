"use client";

interface QuizDoneScreenProps {
  correctCount: number;
  incorrectCount: number;
  resultScore: number | null;
  onPlayAgain: () => void;
  onSettings: () => void;
}

export function QuizDoneScreen({ correctCount, incorrectCount, resultScore, onPlayAgain, onSettings }: QuizDoneScreenProps) {
  const total = correctCount + incorrectCount;
  const pct = resultScore ?? (total > 0 ? Math.round((correctCount / total) * 100) : 0);
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
              onClick={onPlayAgain}
              className="btn-press w-full rounded-xl bg-primary py-3 font-display text-[17px] font-bold uppercase tracking-[0.02em] text-primary-foreground"
            >
              Play Again
            </button>
            <button
              type="button"
              onClick={onSettings}
              className="w-full rounded-xl border-2 border-border bg-white py-3 font-display text-[17px] font-bold uppercase tracking-[0.02em] text-primary transition hover:bg-accent"
            >
              Settings
            </button>
          </div>
        </div>
    </div>
  );
}
