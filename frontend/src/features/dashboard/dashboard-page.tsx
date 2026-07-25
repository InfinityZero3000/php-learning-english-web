"use client";

import Link from "next/link";
import { IconChevronRight, IconPlayerPlayFilled, IconPresentation, IconTrophy } from "@tabler/icons-react";
import { useEffect, useState } from "react";
import { AppFlameIcon, navigationIcons } from "@/components/icons/app-icons";
import { AppShellLoading } from "@/components/layout/app-shell";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { api } from "@/lib/api";
import { formatPercent, masteryLabel } from "@/lib/utils";
import type { NotificationItem, UserProgress, Word } from "@/types/api";

export function DashboardPage() {
  const [stats, setStats] = useState({ totalWords: 0, mastered: 0, learning: 0, dueNow: 0, retention: 0, streak: 0 });
  const [wordOfDay, setWordOfDay] = useState<Word | null>(null);
  const [review, setReview] = useState<UserProgress[]>([]);
  const [categories, setCategories] = useState<string[]>([]);
  const [notifications, setNotifications] = useState<NotificationItem[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function load() {
      const emptyStats: Record<string, number> = {};
      const [count, progressStats, fsrsStats, streak, reviewQueue, cats, notes] = await Promise.all([
        api.wordCount().catch(() => ({ count: 0 })),
        api.progressStats().catch(() => emptyStats),
        api.fsrsStats().catch(() => emptyStats),
        api.streak().catch(() => emptyStats),
        api.reviewQueue().catch(() => []),
        api.categories().catch(() => []),
        api.notifications().catch(() => [])
      ]);
      const random = await api.randomWords(1).catch(() => []);
      setStats({
        totalWords: count.count ?? 0,
        mastered: progressStats.mastered ?? 0,
        learning: progressStats.learning ?? 0,
        dueNow: fsrsStats.dueNow ?? progressStats.dueNow ?? 0,
        retention: fsrsStats.retentionEstimate ?? 0,
        streak: streak.currentStreak ?? 0
      });
      setReview(reviewQueue.slice(0, 5));
      setCategories(cats);
      setNotifications(notes.slice(0, 4));
      setWordOfDay(random[0] ?? null);
      setLoading(false);
    }
    load().catch(() => setLoading(false));
  }, []);

  const newWords = Math.max(stats.totalWords - stats.mastered - stats.learning, 0);
  const masteryTotal = Math.max(stats.totalWords, 1);

  if (loading) return <AppShellLoading label="Loading dashboard..." />;

  return (
    <div className="space-y-6">
        <section className="relative min-h-[260px] overflow-hidden rounded-xl bg-primary p-6 text-primary-foreground md:p-8">
          <div className="relative z-10 max-w-2xl">
            <p className="font-display text-sm font-bold uppercase tracking-widest text-sky-100">Good day, learner!</p>
            <h2 className="mt-2 font-display text-[28px] font-bold leading-tight">Ready to review?</h2>
            <p className="mt-3 text-[17px] font-bold text-sky-100">You have <strong className="text-white">{stats.dueNow}</strong> cards due today.</p>
            <Link
              href="/flashcards"
              className="mt-10 inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-white px-8 font-display text-sm font-bold uppercase tracking-[0.05em] text-primary btn-press transition hover:bg-sky-50"
            >
              <IconPlayerPlayFilled className="h-5 w-5" />
              Start Review
            </Link>
          </div>
          <div className="absolute -right-12 -top-20 h-56 w-56 rounded-full bg-sky-300/20" />
          <div className="absolute right-14 top-12 flex h-40 w-40 items-center justify-center rounded-full bg-sky-200/20 text-white">
            <IconPresentation className="h-20 w-20" stroke={1.6} />
          </div>
          <div className="absolute bottom-[-70px] right-20 h-36 w-36 rounded-full bg-sky-400/20" />
        </section>

        <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <Metric href="/flashcards" icon={<navigationIcons.flashcards />} color="text-[#1cb0f6]" label="Due Today" value={stats.dueNow} sub="Cards to review" />
          <Metric href="/progress" icon={<AppFlameIcon />} color="text-[#f4bf00]" label="Streak" value={stats.streak} sub="Days in a row" />
          <Metric href="/progress" icon={<IconTrophy />} color="text-primary" label="Retention" value={formatPercent(stats.retention)} sub="Estimated recall" />
          <Metric href="/vocabulary" icon={<navigationIcons.words />} color="text-primary" label="Words" value={stats.totalWords} sub="Total vocabulary" />
        </section>

        <section className="grid gap-5 xl:grid-cols-[1fr_0.8fr]">
          <Card>
            <CardHeader>
              <CardTitle>Quick Actions</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-3 sm:grid-cols-2">
              <Action href="/flashcards" icon={<navigationIcons.flashcards />} iconBox="bg-accent text-primary" title="Flashcard Review" sub="FSRS-powered spaced repetition" />
              <Action href="/quiz" icon={<navigationIcons.quiz />} iconBox="bg-secondary/40 text-secondary-foreground" title="Multiple Choice Quiz" sub="Test your knowledge" />
              <Action href="/vocabulary" icon={<navigationIcons.words />} iconBox="bg-muted text-foreground" title="Browse Words" sub="Manage your vocabulary list" />
              <Action href="/import" icon={<navigationIcons.import />} iconBox="bg-red-100 text-red-700" title="Import Words" sub="Add new vocabulary to study" />
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Mastery Progress</CardTitle>
              <CardDescription>{stats.mastered} mastered, {stats.learning} learning</CardDescription>
            </CardHeader>
            <CardContent className="space-y-5">
              <div>
                <div className="mb-2 flex justify-between font-display text-sm font-bold uppercase tracking-[0.05em]"><span>Mastered</span><span className="text-primary">{stats.mastered}</span></div>
                <Progress value={(stats.mastered / masteryTotal) * 100} />
              </div>
              <div>
                <div className="mb-2 flex justify-between font-display text-sm font-bold uppercase tracking-[0.05em]"><span>Learning</span><span className="text-secondary">{stats.learning}</span></div>
                <Progress value={(stats.learning / masteryTotal) * 100} />
              </div>
              <div>
                <div className="mb-2 flex justify-between font-display text-sm font-bold uppercase tracking-[0.05em]"><span>New</span><span className="text-muted-foreground">{newWords}</span></div>
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
              {wordOfDay ? (
                <div className="space-y-2">
                  <h3 className="font-display text-2xl font-bold text-primary">{wordOfDay.word}</h3>
                  <p className="font-mono text-sm text-muted-foreground">{wordOfDay.pronunciation}</p>
                  <p className="font-bold">{wordOfDay.translation}</p>
                  {wordOfDay.example ? <p className="text-sm italic text-muted-foreground">&quot;{wordOfDay.example}&quot;</p> : null}
                </div>
              ) : <p className="text-sm font-semibold text-muted-foreground">No word available yet.</p>}
            </CardContent>
          </Card>
          <Card className="flex flex-col">
            <CardHeader className="shrink-0">
              <CardTitle>Review Queue</CardTitle>
            </CardHeader>
            <CardContent className="min-h-0 flex-1 p-0 pb-4">
              {review.length ? (
                <div className="overflow-y-auto px-6" style={{ maxHeight: "calc(3 * 64px + 2 * 12px)" }}>
                  <div className="space-y-3">
                    {review.map((item) => (
                      <div key={item.id} className="flex items-center justify-between gap-3 rounded-xl bg-muted p-3">
                        <div>
                          <p className="font-display font-bold">{item.word?.word}</p>
                          <p className="text-sm font-semibold text-muted-foreground">{item.word?.translation}</p>
                        </div>
                        <Badge>{masteryLabel(item.mastery)}</Badge>
                      </div>
                    ))}
                  </div>
                </div>
              ) : (
                <p className="px-6 text-sm font-semibold text-muted-foreground">No review due.</p>
              )}
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle>Categories</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-wrap gap-2">
              {categories.length ? categories.map((category) => (
                <Link key={category} href={`/vocabulary?category=${encodeURIComponent(category)}`}>
                  <Badge variant="muted">{category}</Badge>
                </Link>
              )) : <p className="text-sm font-semibold text-muted-foreground">No categories yet.</p>}
              {notifications.length ? <div className="mt-4 w-full space-y-2">{notifications.map((n) => <p key={n.id} className="rounded-xl bg-accent p-3 text-sm font-bold text-primary">{n.title}</p>)}</div> : null}
            </CardContent>
          </Card>
        </section>
    </div>
  );
}

function Metric({ href, icon, color, label, value, sub }: { href: string; icon: React.ReactNode; color: string; label: string; value: string | number; sub: string }) {
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

function Action({ href, icon, iconBox, title, sub }: { href: string; icon: React.ReactNode; iconBox: string; title: string; sub: string }) {
  return (
    <Link href={href} className="flex min-h-[132px] flex-col justify-between rounded-xl border-2 border-transparent bg-muted/40 p-4 transition hover:border-primary hover:bg-muted">
      <span className="flex items-start justify-between gap-3">
        <span className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl [&>svg]:h-6 [&>svg]:w-6 [&>svg]:stroke-[2] ${iconBox}`}>{icon}</span>
        <IconChevronRight className="h-5 w-5 text-muted-foreground" />
      </span>
      <span className="mt-4 block">
        <span className="block font-display text-[0.95rem] font-bold uppercase leading-tight">{title}</span>
        <span className="mt-1 block text-sm font-medium leading-snug text-muted-foreground">{sub}</span>
      </span>
    </Link>
  );
}
