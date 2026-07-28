"use client";

import Link from "next/link";
import { IconChevronRight, IconPlayerPlayFilled, IconPresentation, IconTrophy } from "@tabler/icons-react";
import { useCallback, useEffect, useState } from "react";
import { AppFlameIcon, navigationIcons } from "@/components/icons/app-icons";
import { AppShellLoading } from "@/components/layout/app-shell";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { fetchDashboard, fetchVocabulary } from "@/lib/api";
import type { DashboardResource, VocabularyResource } from "@/lib/api";
import { ApiError } from "@/lib/api";
import { useAuth } from "@/features/auth/auth-context";

export function DashboardPage() {
  const { status } = useAuth();
  const [dashboard, setDashboard] = useState<DashboardResource | null>(null);
  const [vocabWords, setVocabWords] = useState<VocabularyResource[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadData = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const authenticated = status === "authenticated";
      const [vocabResult, dash] = await Promise.all([
        fetchVocabulary({ per_page: 100 }).catch(() => ({ data: [] as VocabularyResource[], meta: {} })),
        authenticated
          ? fetchDashboard().catch((err) => {
              if (err instanceof ApiError && err.status === 401) return null;
              throw err;
            })
          : null,
      ]);
      setVocabWords(vocabResult.data);
      setDashboard(dash);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to load dashboard");
    } finally {
      setLoading(false);
    }
  }, [status]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const totalWords = dashboard?.total_words ?? vocabWords.length;
  const mastered = dashboard?.completed_lessons ?? 0;
  const learning = Math.max((dashboard?.total_lessons ?? 0) - mastered, 0);
  const newWords = Math.max(totalWords - mastered - learning, 0);
  const masteryTotal = Math.max(totalWords, 1);

  if (loading) return <AppShellLoading label="Loading dashboard..." />;

  if (error) {
    return (
      <div className="flex flex-col items-center justify-center py-20">
        <p className="text-sm font-semibold text-destructive">{error}</p>
        <button
          type="button"
          className="mt-4 rounded-xl bg-primary px-6 py-2.5 text-sm font-bold text-primary-foreground"
          onClick={loadData}
        >
          Retry
        </button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <section className="relative min-h-[260px] overflow-hidden rounded-xl bg-primary p-6 text-primary-foreground md:p-8">
        <div className="relative z-10 max-w-2xl">
          <p className="font-display text-sm font-bold uppercase tracking-widest text-sky-100">Good day, learner!</p>
          <h2 className="mt-2 font-display text-[28px] font-bold leading-tight">Ready to learn?</h2>
          <p className="mt-3 text-[17px] font-bold text-sky-100">
            You have <strong className="text-white">{totalWords}</strong> words to explore.
          </p>
          <Link
            href="/courses"
            className="mt-10 inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-white px-8 font-display text-sm font-bold uppercase tracking-[0.05em] text-primary btn-press transition hover:bg-sky-50"
          >
            <IconPlayerPlayFilled className="h-5 w-5" />
            Explore Courses
          </Link>
        </div>
        <div className="absolute -right-12 -top-20 h-56 w-56 rounded-full bg-sky-300/20" />
        <div className="absolute right-14 top-12 flex h-40 w-40 items-center justify-center rounded-full bg-sky-200/20 text-white">
          <IconPresentation className="h-20 w-20" stroke={1.6} />
        </div>
        <div className="absolute bottom-[-70px] right-20 h-36 w-36 rounded-full bg-sky-400/20" />
      </section>

      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Metric
          href="/courses"
          icon={<navigationIcons.flashcards />}
          color="text-[#1cb0f6]"
          label="Courses"
          value={dashboard?.total_lessons ?? "-"}
          sub="Total lessons available"
        />
        <Metric
          href="/progress"
          icon={<AppFlameIcon />}
          color="text-[#f4bf00]"
          label="Completed"
          value={mastered}
          sub="Lessons completed"
        />
        <Metric
          href="/progress"
          icon={<IconTrophy />}
          color="text-primary"
          label="Bookmarks"
          value={dashboard?.total_bookmarks ?? "-"}
          sub="Saved for later"
        />
        <Metric
          href="/vocabulary"
          icon={<navigationIcons.words />}
          color="text-primary"
          label="Words"
          value={totalWords}
          sub="Total vocabulary"
        />
      </section>

      <section className="grid gap-5 xl:grid-cols-[1fr_0.8fr]">
        <Card>
          <CardHeader>
            <CardTitle>Quick Actions</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-3 sm:grid-cols-2">
            <Action
              href="/courses"
              icon={<navigationIcons.flashcards />}
              iconBox="bg-accent text-primary"
              title="Browse Courses"
              sub="Explore lessons by topic"
            />
            <Action
              href="/quiz"
              icon={<navigationIcons.quiz />}
              iconBox="bg-secondary/40 text-secondary-foreground"
              title="Multiple Choice Quiz"
              sub="Test your knowledge"
            />
            <Action
              href="/vocabulary"
              icon={<navigationIcons.words />}
              iconBox="bg-muted text-foreground"
              title="Browse Words"
              sub="Manage your vocabulary list"
            />
            <Action
              href="/bookmarks"
              icon={<navigationIcons.words />}
              iconBox="bg-red-100 text-red-700"
              title="Bookmarks"
              sub="Your saved lessons & words"
            />
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Progress Overview</CardTitle>
            <CardDescription>
              {mastered} completed, {learning} remaining
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-5">
            <div>
              <div className="mb-2 flex justify-between font-display text-sm font-bold uppercase tracking-[0.05em]">
                <span>Completed</span>
                <span className="text-primary">{mastered}</span>
              </div>
              <Progress value={(mastered / masteryTotal) * 100} />
            </div>
            <div>
              <div className="mb-2 flex justify-between font-display text-sm font-bold uppercase tracking-[0.05em]">
                <span>Remaining</span>
                <span className="text-secondary">{learning}</span>
              </div>
              <Progress value={(learning / masteryTotal) * 100} />
            </div>
            <div>
              <div className="mb-2 flex justify-between font-display text-sm font-bold uppercase tracking-[0.05em]">
                <span>New</span>
                <span className="text-muted-foreground">{newWords}</span>
              </div>
              <Progress value={(newWords / masteryTotal) * 100} />
            </div>
          </CardContent>
        </Card>
      </section>

      <section className="grid gap-5 xl:grid-cols-3">
        <Card>
          <CardHeader>
            <CardTitle>Word of the Day</CardTitle>
          </CardHeader>
          <CardContent>
            {vocabWords[0] ? (
              <div className="space-y-2">
                <h3 className="font-display text-2xl font-bold text-primary">{vocabWords[0].word}</h3>
                {vocabWords[0].pronunciation ? (
                  <p className="font-mono text-sm text-muted-foreground">{vocabWords[0].pronunciation}</p>
                ) : null}
                <p className="font-bold">{vocabWords[0].meaning ?? vocabWords[0].definition}</p>
              </div>
            ) : (
              <p className="text-sm font-semibold text-muted-foreground">No word available yet.</p>
            )}
          </CardContent>
        </Card>

        <Card className="flex flex-col">
          <CardHeader className="shrink-0">
            <CardTitle>Recent Attempts</CardTitle>
          </CardHeader>
          <CardContent className="min-h-0 flex-1 p-0 pb-4">
            {dashboard?.recent_attempts && dashboard.recent_attempts.length > 0 ? (
              <div className="overflow-y-auto px-6" style={{ maxHeight: "calc(3 * 64px + 2 * 12px)" }}>
                <div className="space-y-3">
                  {dashboard.recent_attempts.slice(0, 5).map((attempt) => (
                    <div key={attempt.id} className="flex items-center justify-between gap-3 rounded-xl bg-muted p-3">
                      <div>
                        <p className="font-display font-bold">Score: {attempt.score}</p>
                        <p className="text-sm font-semibold text-muted-foreground">
                          {attempt.correct_answers}/{attempt.total_questions} correct
                        </p>
                      </div>
                      <Badge>{attempt.is_completed ? "Done" : "In Progress"}</Badge>
                    </div>
                  ))}
                </div>
              </div>
            ) : (
              <p className="px-6 text-sm font-semibold text-muted-foreground">No quiz attempts yet.</p>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Course Progress</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-wrap gap-2">
            {dashboard?.course_progress && dashboard.course_progress.length > 0 ? (
              dashboard.course_progress.map((cp) => (
                <Link key={cp.id} href={`/courses/${cp.course?.id ?? cp.id}`}>
                  <Badge variant="muted">
                    {cp.course?.title ?? `Course ${cp.id}`}: {cp.progress_percent ?? 0}%
                  </Badge>
                </Link>
              ))
            ) : (
              <p className="text-sm font-semibold text-muted-foreground">No course progress yet.</p>
            )}
          </CardContent>
        </Card>
      </section>
    </div>
  );
}

function Metric({
  href,
  icon,
  color,
  label,
  value,
  sub,
}: {
  href: string;
  icon: React.ReactNode;
  color: string;
  label: string;
  value: string | number;
  sub: string;
}) {
  return (
    <Link href={href} className="learning-card block p-6">
      <div className={`mb-7 flex items-center gap-3 ${color}`}>
        <span className="[&>svg]:h-6 [&>svg]:w-6 [&>svg]:stroke-[2]">{icon}</span>
        <span className="font-display text-xs font-bold uppercase tracking-[0.08em] text-muted-foreground">{label}</span>
      </div>
      <p className="font-display text-[28px] font-bold leading-tight">{value}</p>
      <p className="text-sm font-medium text-muted-foreground">{sub}</p>
    </Link>
  );
}

function Action({
  href,
  icon,
  iconBox,
  title,
  sub,
}: {
  href: string;
  icon: React.ReactNode;
  iconBox: string;
  title: string;
  sub: string;
}) {
  return (
    <Link
      href={href}
      className="flex min-h-[132px] flex-col justify-between rounded-xl border-2 border-transparent bg-muted/40 p-4 transition hover:border-primary hover:bg-muted"
    >
      <span className="flex items-start justify-between gap-3">
        <span
          className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl [&>svg]:h-6 [&>svg]:w-6 [&>svg]:stroke-[2] ${iconBox}`}
        >
          {icon}
        </span>
        <IconChevronRight className="h-5 w-5 text-muted-foreground" />
      </span>
      <span className="mt-4 block">
        <span className="block font-display text-[0.95rem] font-bold uppercase leading-tight">{title}</span>
        <span className="mt-1 block text-sm font-medium leading-snug text-muted-foreground">{sub}</span>
      </span>
    </Link>
  );
}