"use client";

import Link from "next/link";
import { IconPlayerPlayFilled, IconPresentation, IconTrophy } from "@tabler/icons-react";
import { useCallback, useEffect, useState } from "react";
import { AppFlameIcon, navigationIcons } from "@/components/icons/app-icons";
import { AppShellLoading } from "@/components/layout/app-shell";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ApiError, fetchDashboard, fetchVocabulary, type DashboardResource, type VocabularyResource } from "@/lib/api";
import { useAuth } from "@/features/auth/auth-context";
import { formatDateTime } from "@/lib/utils";

export function DashboardPage() {
  const { status } = useAuth();
  const [dashboard, setDashboard] = useState<DashboardResource | null>(null);
  const [word, setWord] = useState<VocabularyResource | null>(null);
  const [wordCount, setWordCount] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [vocabulary, progress] = await Promise.all([
        fetchVocabulary({ per_page: 1 }),
        status === "authenticated"
          ? fetchDashboard().catch((reason) => {
              if (reason instanceof ApiError && reason.status === 401) return null;
              throw reason;
            })
          : null,
      ]);
      setWord(vocabulary.data[0] ?? null);
      setWordCount(vocabulary.meta.total ?? vocabulary.data.length);
      setDashboard(progress);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : "Failed to load dashboard");
    } finally {
      setLoading(false);
    }
  }, [status]);

  useEffect(() => {
    void load();
  }, [load]);

  if (loading) return <AppShellLoading label="Loading dashboard..." />;
  if (error) return <ErrorState message={error} retry={load} />;

  const overview = dashboard?.overview;

  return (
    <div className="space-y-6">
      <section className="relative min-h-[260px] overflow-hidden rounded-xl bg-primary p-6 text-primary-foreground md:p-8">
        <div className="relative z-10 max-w-2xl">
          <p className="font-display text-sm font-bold uppercase tracking-widest text-sky-100">Good day, learner!</p>
          <h2 className="mt-2 font-display text-3xl font-bold">Ready to learn?</h2>
          <p className="mt-3 text-lg font-bold text-sky-100">Explore {wordCount} vocabulary words.</p>
          <Link href="/vocabulary" className="mt-10 inline-flex h-12 items-center gap-2 rounded-xl bg-white px-8 font-display text-sm font-bold uppercase text-primary">
            <IconPlayerPlayFilled className="h-5 w-5" /> Start learning
          </Link>
        </div>
        <div className="absolute right-14 top-12 hidden h-40 w-40 items-center justify-center rounded-full bg-sky-200/20 text-white sm:flex">
          <IconPresentation className="h-20 w-20" stroke={1.6} />
        </div>
      </section>

      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Metric href="/vocabulary" icon={<navigationIcons.words />} label="Words" value={wordCount} />
        <Metric href="/progress" icon={<AppFlameIcon />} label="Lessons Done" value={overview?.completed_lessons ?? 0} />
        <Metric href="/quiz" icon={<navigationIcons.quiz />} label="Quiz Attempts" value={overview?.quiz_attempts ?? 0} />
        <Metric href="/progress" icon={<IconTrophy />} label="Average Score" value={`${overview?.average_score ?? 0}%`} />
      </section>

      <section className="grid gap-5 lg:grid-cols-3">
        <Card>
          <CardHeader><CardTitle>Word of the Day</CardTitle></CardHeader>
          <CardContent>
            {word ? (
              <div className="space-y-2">
                <h3 className="font-display text-2xl font-bold text-primary">{word.word}</h3>
                {word.pronunciation ? <p className="font-mono text-sm text-muted-foreground">{word.pronunciation}</p> : null}
                <p className="font-bold">{word.meaning ?? word.definition}</p>
              </div>
            ) : <p className="text-sm text-muted-foreground">No word available yet.</p>}
          </CardContent>
        </Card>

        <ActivityCard title="Recent Lessons" empty="No completed lessons yet.">
          {(dashboard?.recent_activity ?? []).map((item) => (
            <Activity key={item.id} title={item.lesson?.title ?? `Lesson #${item.lesson_id ?? item.id}`} detail={formatDateTime(item.completed_at)} />
          ))}
        </ActivityCard>

        <ActivityCard title="Recent Quizzes" empty="No quiz attempts yet.">
          {(dashboard?.recent_attempts ?? []).map((attempt) => (
            <Activity key={attempt.id} title={`Quiz #${attempt.quiz_id ?? "-"}`} detail={`${attempt.score}% · ${formatDateTime(attempt.completed_at)}`} />
          ))}
        </ActivityCard>
      </section>
    </div>
  );
}

function Metric({ href, icon, label, value }: { href: string; icon: React.ReactNode; label: string; value: number | string }) {
  return <Link href={href} className="rounded-xl border-2 border-border bg-white p-5 transition hover:border-primary"><span className="text-primary">{icon}</span><p className="mt-3 font-display text-3xl font-black">{value}</p><p className="text-sm font-bold uppercase text-muted-foreground">{label}</p></Link>;
}

function ActivityCard({ title, empty, children }: { title: string; empty: string; children: React.ReactNode[] }) {
  return <Card><CardHeader><CardTitle>{title}</CardTitle></CardHeader><CardContent className="space-y-3">{children.length ? children : <p className="text-sm text-muted-foreground">{empty}</p>}</CardContent></Card>;
}

function Activity({ title, detail }: { title: string; detail: string }) {
  return <div className="rounded-xl bg-muted p-3"><p className="font-bold">{title}</p><p className="text-xs text-muted-foreground">{detail}</p></div>;
}

function ErrorState({ message, retry }: { message: string; retry: () => Promise<void> }) {
  return <div className="py-20 text-center"><p className="font-semibold text-destructive" role="alert">{message}</p><button type="button" className="mt-4 rounded-xl bg-primary px-6 py-2.5 font-bold text-primary-foreground" onClick={() => void retry()}>Retry</button></div>;
}
