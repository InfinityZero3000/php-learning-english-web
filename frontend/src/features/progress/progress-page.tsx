"use client";

import { IconAward, IconBooks, IconBrain, IconCircleCheck, IconCircleX, IconSchool, IconTarget, IconTrophy } from "@tabler/icons-react";
import { useEffect, useMemo, useState } from "react";
import { AppShellLoading } from "@/components/layout/app-shell";
import { api } from "@/lib/api";
import { formatDateTime, masteryLabel } from "@/lib/utils";
import type { QuizSession, UserProgress } from "@/types/api";

const MASTERY_TYPES = ["NEW", "LEARNING", "REVIEWING", "MASTERED"] as const;
const ROWS_PER_PAGE = 100;

const MASTERY_BADGE: Record<string, string> = {
  NEW: "bg-[#e3e2e2] text-[#6e7881] border border-[#bdc8d2]",
  LEARNING: "bg-[#ffdf92] text-[#6e5400] border border-[#f4bf00]",
  REVIEWING: "bg-[#c8e6ff] text-[#006590] border border-[#88ceff]",
  MASTERED: "bg-[#d8f3dc] text-[#1b5e20] border border-[#95d5b2]",
};

const MASTERY_BAR: Record<string, string> = {
  NEW: "bg-[#bdc8d2]",
  LEARNING: "bg-[#fec700]",
  REVIEWING: "bg-[#1cb0f6]",
  MASTERED: "bg-primary",
};

function accuracyColor(pct: number) {
  if (pct >= 80) return "text-primary font-bold";
  if (pct >= 60) return "text-secondary-foreground font-bold";
  return "text-destructive font-bold";
}

function MasteryPill({ mastery }: { mastery?: string | null }) {
  const key = mastery || "NEW";
  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-0.5 font-display text-[11px] font-bold uppercase tracking-widest ${MASTERY_BADGE[key] ?? MASTERY_BADGE.NEW}`}
    >
      {masteryLabel(mastery)}
    </span>
  );
}

export function ProgressPage() {
  const [stats, setStats] = useState<Record<string, number>>({});
  const [quizStats, setQuizStats] = useState<Record<string, number>>({});
  const [wordCount, setWordCount] = useState(0);
  const [progress, setProgress] = useState<UserProgress[]>([]);
  const [sessions, setSessions] = useState<QuizSession[]>([]);
  const [masteryFilter, setMasteryFilter] = useState("");
  const [visibleRows, setVisibleRows] = useState(ROWS_PER_PAGE);
  const [loading, setLoading] = useState(true);
  const [progressError, setProgressError] = useState<string | null>(null);

  useEffect(() => {
    const loadAll = async () => {
      const [statsRes, quizStatsRes, countRes, progressRes, sessionsRes] = await Promise.allSettled([
        api.progressStats(),
        api.quizStats(),
        api.wordCount(),
        api.progress(),
        api.recentQuizSessions(),
      ]);

      if (statsRes.status === "fulfilled") setStats(statsRes.value as Record<string, number>);
      if (quizStatsRes.status === "fulfilled") setQuizStats(quizStatsRes.value as Record<string, number>);
      if (countRes.status === "fulfilled") setWordCount((countRes.value as { count: number }).count ?? 0);

      if (progressRes.status === "fulfilled") {
        setProgress(progressRes.value as UserProgress[]);
        setProgressError(null);
      } else {
        setProgressError((progressRes.reason as Error)?.message ?? "Failed to load word progress");
        setProgress([]);
      }

      if (sessionsRes.status === "fulfilled") setSessions(sessionsRes.value as QuizSession[]);

      setLoading(false);
    };
    loadAll();
  }, []);

  const masteryCounts = useMemo(() => {
    const counts = { NEW: 0, LEARNING: 0, REVIEWING: 0, MASTERED: 0 };
    for (const p of progress) {
      const key = (p.mastery || "NEW") as keyof typeof counts;
      if (key in counts) counts[key]++;
    }
    counts.NEW += Math.max(0, wordCount - progress.length);
    return counts;
  }, [progress, wordCount]);

  const totalMastery = useMemo(
    () => MASTERY_TYPES.reduce((s, t) => s + masteryCounts[t], 0),
    [masteryCounts]
  );

  const filteredProgress = useMemo(
    () => masteryFilter ? progress.filter((p) => (p.mastery || "NEW") === masteryFilter) : progress,
    [progress, masteryFilter]
  );

  const visibleProgress = useMemo(
    () => filteredProgress.slice(0, visibleRows),
    [filteredProgress, visibleRows]
  );

  const accuracy = stats.accuracy ?? 0;
  const totalCorrect = stats.totalCorrect ?? 0;
  const totalIncorrect = stats.totalIncorrect ?? 0;
  const mastered = stats.mastered ?? masteryCounts.MASTERED;
  const completedSessions = quizStats.completedSessions ?? 0;
  const averageScore = quizStats.averageScore ?? 0;

  if (loading) return <AppShellLoading label="Loading progress..." />;

  return (
    <div className="space-y-8">
        {/* Overview Stats */}
        <section>
          <h2 className="mb-6 font-display text-[24px] font-bold text-foreground">Overview</h2>
          <div className="grid grid-cols-2 gap-6 md:grid-cols-4">
            <div className="flex flex-col gap-2 rounded-xl border-2 border-border bg-white p-6">
              <IconBooks className="h-6 w-6 text-muted-foreground" />
              <p className="font-display text-[32px] font-black leading-none text-primary">{wordCount}</p>
              <p className="font-display text-[15px] font-bold uppercase tracking-widest text-muted-foreground">Total Words</p>
            </div>
            <div className="flex flex-col gap-2 rounded-xl border-2 border-border bg-white p-6">
              <IconAward className="h-6 w-6 text-primary" />
              <p className="font-display text-[32px] font-black leading-none text-primary">{mastered}</p>
              <p className="font-display text-[15px] font-bold uppercase tracking-widest text-muted-foreground">Mastered</p>
            </div>
            <div className="flex flex-col gap-2 rounded-xl border-2 border-border bg-white p-6">
              <IconSchool className="h-6 w-6 text-[#f4bf00]" />
              <p className="font-display text-[32px] font-black leading-none text-[#f4bf00]">{masteryCounts.LEARNING}</p>
              <p className="font-display text-[15px] font-bold uppercase tracking-widest text-muted-foreground">Learning</p>
            </div>
            <div className="flex flex-col gap-2 rounded-xl border-2 border-border bg-white p-6">
              <IconTarget className="h-6 w-6 text-muted-foreground" />
              <p className={`font-display text-[32px] font-black leading-none ${accuracyColor(accuracy)}`}>{accuracy.toFixed(1)}%</p>
              <p className="font-display text-[15px] font-bold uppercase tracking-widest text-muted-foreground">Accuracy</p>
            </div>
          </div>
        </section>

        {/* Review & Quiz Stats */}
        <section className="grid grid-cols-2 gap-6 md:grid-cols-4">
          <div className="flex flex-col gap-2 rounded-xl bg-accent p-6">
            <IconCircleCheck className="h-6 w-6 text-primary" />
            <p className="font-display text-[32px] font-black leading-none text-primary">{totalCorrect}</p>
            <p className="font-display text-[15px] font-bold uppercase tracking-widest text-accent-foreground">Correct Answers</p>
          </div>
          <div className="flex flex-col gap-2 rounded-xl bg-[#ffdad6] p-6">
            <IconCircleX className="h-6 w-6 text-destructive" />
            <p className="font-display text-[32px] font-black leading-none text-destructive">{totalIncorrect}</p>
            <p className="font-display text-[15px] font-bold uppercase tracking-widest text-[#93000a]">Incorrect</p>
          </div>
          <div className="flex flex-col gap-2 rounded-xl border-2 border-border bg-white p-6">
            <IconBrain className="h-6 w-6 text-muted-foreground" />
            <p className="font-display text-[32px] font-black leading-none text-foreground">{completedSessions}</p>
            <p className="font-display text-[15px] font-bold uppercase tracking-widest text-muted-foreground">Quizzes Done</p>
          </div>
          <div className="flex flex-col gap-2 rounded-xl bg-[#ffdf92] p-6">
            <IconTrophy className="h-6 w-6 text-secondary-foreground" />
            <p className="font-display text-[32px] font-black leading-none text-secondary-foreground">{averageScore.toFixed(0)}%</p>
            <p className="font-display text-[15px] font-bold uppercase tracking-widest text-secondary-foreground">Avg Score</p>
          </div>
        </section>

        {/* Mastery + Sessions */}
        <section className="grid gap-6 md:grid-cols-2">
          {/* Mastery Breakdown */}
          <div className="rounded-xl border-2 border-border bg-white p-5">
            <h3 className="mb-4 font-display text-[17px] font-bold text-foreground">
              Mastery Breakdown
            </h3>
            <div className="flex flex-col gap-3">
              {MASTERY_TYPES.map((type) => {
                const count = masteryCounts[type];
                const pct = totalMastery > 0 ? (count / totalMastery) * 100 : 0;
                return (
                  <div key={type} className="flex flex-col gap-1">
                    <div className="flex items-center justify-between">
                      <MasteryPill mastery={type} />
                      <span className="font-display text-[13px] font-bold text-muted-foreground">
                        {count} ({pct.toFixed(0)}%)
                      </span>
                    </div>
                    <div className="h-2.5 overflow-hidden rounded-full bg-muted">
                      <div
                        className={`h-full rounded-full transition-[width] duration-500 ${MASTERY_BAR[type]}`}
                        style={{ width: `${pct}%` }}
                      />
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

          {/* Recent Sessions */}
          <div className="rounded-xl border-2 border-border bg-white p-5">
            <h3 className="mb-4 font-display text-[17px] font-bold text-foreground">
              Recent Quiz Sessions
            </h3>
            {sessions.length === 0 ? (
              <p className="font-body text-[15px] text-muted-foreground">No quiz sessions yet.</p>
            ) : (
              <div className="flex flex-col gap-2">
                {sessions.map((s) => {
                  const pct =
                    s.score != null
                      ? s.score
                      : s.totalQuestions && s.correctAnswers != null
                        ? Math.round((s.correctAnswers / s.totalQuestions) * 100)
                        : 0;
                  return (
                    <div
                      key={s.id}
                      className="flex items-center justify-between rounded-xl border border-muted bg-white px-4 py-3"
                    >
                      <div className="flex flex-col gap-0.5">
                        <span className="font-display text-[15px] font-bold text-foreground">
                          {s.correctAnswers ?? 0} / {s.totalQuestions ?? 0} correct
                        </span>
                        <span className="font-body text-[13px] text-muted-foreground">
                          {formatDateTime(s.createdAt)}
                        </span>
                      </div>
                      <span
                        className={`font-display text-[20px] font-black ${
                          pct >= 80
                            ? "text-primary"
                            : pct >= 60
                              ? "text-secondary-foreground"
                              : "text-destructive"
                        }`}
                      >
                        {pct}%
                      </span>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </section>
        <div className="rounded-xl border-2 border-border bg-white">
          <div className="flex flex-wrap items-center gap-3 border-b border-border px-5 py-4">
            <h3 className="font-display text-[17px] font-bold text-foreground">
              Word Progress ({filteredProgress.length})
              {filteredProgress.length > visibleRows && (
                <span className="ml-2 font-display text-[12px] font-normal text-muted-foreground">
                  (showing {visibleRows})
                </span>
              )}
            </h3>
            <div className="ml-auto flex flex-wrap gap-2">
              {(["", ...MASTERY_TYPES] as const).map((type) => (
                <button
                  key={type}
                  type="button"
                  onClick={() => { setMasteryFilter(type); setVisibleRows(ROWS_PER_PAGE); }}
                  className={`rounded-full border-2 px-3 py-1 font-display text-[12px] font-bold uppercase tracking-widest transition ${
                    masteryFilter === type
                      ? "border-primary bg-primary text-primary-foreground"
                      : "border-border bg-white text-muted-foreground hover:border-primary hover:text-primary"
                  }`}
                >
                  {type === "" ? "All" : masteryLabel(type)}
                </button>
              ))}
            </div>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-border">
                  {["Word", "Translation", "Mastery", "Correct", "Wrong", "Accuracy"].map((h) => (
                    <th
                      key={h}
                      className="px-5 py-3 text-left font-display text-[12px] font-bold uppercase tracking-widest text-muted-foreground"
                    >
                      {h}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {progressError ? (
                  <tr>
                    <td colSpan={6} className="px-5 py-8 text-center">
                      <p className="font-body text-[14px] text-destructive font-semibold">⚠ {progressError}</p>
                      <p className="font-body text-[12px] text-muted-foreground mt-1">Check that you are logged in and the server is running.</p>
                    </td>
                  </tr>
                ) : filteredProgress.length === 0 ? (
                  <tr>
                    <td
                      colSpan={6}
                      className="px-5 py-8 text-center font-body text-[15px] text-muted-foreground"
                    >
                      No words found. Complete a quiz or review session to track progress.
                    </td>
                  </tr>
                ) : (
                  <>
                    {visibleProgress.map((p) => {
                      const correct = p.correctCount ?? 0;
                      const wrong = p.incorrectCount ?? 0;
                      const total = correct + wrong;
                      const pct = total > 0 ? Math.round((correct / total) * 100) : 0;
                      return (
                        <tr key={p.id} className="border-b border-border last:border-0 hover:bg-muted/40">
                          <td className="px-5 py-3">
                            <div className="flex flex-col">
                              <span className="font-display text-[15px] font-bold text-foreground">
                                {p.word.word}
                              </span>
                              {p.word.pronunciation ? (
                                <span className="font-mono text-[12px] text-primary">
                                  {p.word.pronunciation}
                                </span>
                              ) : null}
                            </div>
                          </td>
                          <td className="px-5 py-3 font-body text-[15px] text-foreground">
                            {p.word.translation}
                          </td>
                          <td className="px-5 py-3">
                            <MasteryPill mastery={p.mastery} />
                          </td>
                          <td className="px-5 py-3 font-display text-[15px] font-bold text-primary">
                            {correct}
                          </td>
                          <td className="px-5 py-3 font-display text-[15px] font-bold text-destructive">
                            {wrong}
                          </td>
                          <td className={`px-5 py-3 font-display text-[15px] ${accuracyColor(pct)}`}>
                            {total > 0 ? `${pct}%` : "—"}
                          </td>
                        </tr>
                      );
                    })}
                    {filteredProgress.length > visibleRows && (
                      <tr>
                        <td colSpan={6} className="px-5 py-4 text-center">
                          <button
                            type="button"
                            onClick={() => setVisibleRows((v) => v + ROWS_PER_PAGE)}
                            className="rounded-xl border-2 border-border px-6 py-2 font-display text-[13px] font-bold uppercase tracking-widest text-muted-foreground transition hover:border-primary hover:text-primary"
                          >
                            Load more ({filteredProgress.length - visibleRows} remaining)
                          </button>
                        </td>
                      </tr>
                    )}
                  </>
                )}
              </tbody>
            </table>
          </div>
        </div>
    </div>
  );
}
