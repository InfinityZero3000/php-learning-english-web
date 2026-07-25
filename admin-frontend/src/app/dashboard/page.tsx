'use client';

import { useEffect, useState } from 'react';
import AdminLayout from '@/components/AdminLayout';
import StatsCard from '@/components/StatsCard';
import { fsrs, words, quiz, streak } from '@/lib/api';
import Link from 'next/link';

interface FsrsStats {
  dueNow?: number;
  mastered?: number;
  learning?: number;
  retentionEstimate?: number;
  averageStability?: number;
  averageDifficulty?: number;
  [key: string]: unknown;
}

interface QuizStats {
  completedSessions?: number;
  averageScore?: number;
  [key: string]: unknown;
}

interface StreakData {
  currentStreak?: number;
  longestStreak?: number;
  [key: string]: unknown;
}

export default function DashboardPage() {
  const [fsrsStats, setFsrsStats] = useState<FsrsStats | null>(null);
  const [wordCount, setWordCount] = useState<number>(0);
  const [quizStats, setQuizStats] = useState<QuizStats | null>(null);
  const [streakData, setStreakData] = useState<StreakData | null>(null);
  const [recentSessions, setRecentSessions] = useState<unknown[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const load = async () => {
      try {
        const [f, w, q, s, rs] = await Promise.allSettled([
          fsrs.stats(),
          words.count(),
          quiz.stats(),
          streak.get(),
          quiz.recentSessions(),
        ]);
        if (f.status === 'fulfilled') setFsrsStats(f.value as FsrsStats);
        if (w.status === 'fulfilled') {
          const wVal = w.value as { count?: number } | number;
          setWordCount(typeof wVal === 'number' ? wVal : ((wVal as { count?: number }).count ?? 0));
        }
        if (q.status === 'fulfilled') setQuizStats(q.value as QuizStats);
        if (s.status === 'fulfilled') setStreakData(s.value as StreakData);
        if (rs.status === 'fulfilled') setRecentSessions(rs.value as unknown[]);
      } finally {
        setLoading(false);
      }
    };
    load();
  }, []);

  return (
    <AdminLayout title="Dashboard">
      <div className="space-y-8">
        {/* Hero */}
        <section className="rounded-3xl p-10 flex flex-col md:flex-row items-center justify-between overflow-hidden"
          style={{ backgroundColor: '#e8f4ff', border: '2px solid #1cb0f6' }}>
          <div className="space-y-6">
            <h2 className="text-4xl font-extrabold max-w-md" style={{ color: '#00405d', letterSpacing: '-0.02em' }}>
              teach smarter.<br />learn faster.
            </h2>
            <p className="text-sm font-medium" style={{ color: '#3e4850' }}>
              FSRS-powered vocabulary system — adaptive spaced repetition
            </p>
            <div className="flex flex-wrap gap-4">
              <Link href="/vocabulary"
                className="btn-tactile px-8 py-3 rounded-2xl font-bold text-sm uppercase tracking-wider"
                style={{ backgroundColor: '#006590', color: '#ffffff', borderBottom: '4px solid #004c6e' }}>
                + Add Word
              </Link>
              <Link href="/spaced-repetition"
                className="btn-tactile px-8 py-3 rounded-2xl font-bold text-sm uppercase tracking-wider"
                style={{ backgroundColor: '#ffffff', color: '#006590', border: '2px solid #006590', borderBottom: '4px solid #006590' }}>
                View FSRS Stats
              </Link>
            </div>
          </div>
          <div className="mt-8 md:mt-0 w-48 h-48 rounded-full flex items-center justify-center"
            style={{ backgroundColor: '#006590', border: '8px solid #004c6e' }}>
            <span className="material-symbols-outlined"
              style={{ color: '#ffffff', fontSize: '80px', fontVariationSettings: "'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 48" }}>
              auto_stories
            </span>
          </div>
        </section>

        {/* Stats Grid */}
        <section className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <StatsCard icon="menu_book" label="Total Words" value={loading ? '...' : wordCount.toLocaleString()} />
          <StatsCard icon="replay" label="Due for Review" value={loading ? '...' : (fsrsStats?.dueNow ?? 0)} color="#843ab4" />
          <StatsCard icon="target" label="Quiz Accuracy" value={loading ? '...' : quizStats?.averageScore != null ? `${Math.round(quizStats.averageScore as number)}%` : 'N/A'} color="#755b00" />
          <StatsCard icon="local_fire_department" label="Current Streak" value={loading ? '...' : (streakData?.currentStreak ?? 0) + ' days'} color="#ba1a1a" />
        </section>

        {/* FSRS Detail Cards */}
        {fsrsStats && (
          <section className="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div className="p-6 rounded-2xl" style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
              <p className="text-xs font-bold uppercase tracking-widest mb-1" style={{ color: '#3e4850' }}>Avg. Stability</p>
              <p className="text-3xl font-extrabold" style={{ color: '#006590' }}>
                {fsrsStats.averageStability != null ? (fsrsStats.averageStability as number).toFixed(1) : 'N/A'}
              </p>
              <p className="text-xs mt-1" style={{ color: '#3e4850' }}>days average memory</p>
            </div>
            <div className="p-6 rounded-2xl" style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
              <p className="text-xs font-bold uppercase tracking-widest mb-1" style={{ color: '#3e4850' }}>Avg. Difficulty</p>
              <p className="text-3xl font-extrabold" style={{ color: '#843ab4' }}>
                {fsrsStats.averageDifficulty != null ? (fsrsStats.averageDifficulty as number).toFixed(2) : 'N/A'}
              </p>
              <p className="text-xs mt-1" style={{ color: '#3e4850' }}>FSRS difficulty score</p>
            </div>
            <div className="p-6 rounded-2xl" style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
              <p className="text-xs font-bold uppercase tracking-widest mb-1" style={{ color: '#3e4850' }}>Retention Rate</p>
              <p className="text-3xl font-extrabold" style={{ color: '#755b00' }}>
                {fsrsStats.retentionEstimate != null ? `${Math.round(fsrsStats.retentionEstimate as number)}%` : 'N/A'}
              </p>
              <p className="text-xs mt-1" style={{ color: '#3e4850' }}>target ≥ 90%</p>
            </div>
          </section>
        )}

        {/* Recent Quiz Sessions */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <div className="lg:col-span-2 rounded-3xl"
            style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
            <div className="p-5 flex justify-between items-center rounded-t-[22px]"
              style={{ borderBottom: '2px solid #bdc8d2', backgroundColor: '#f5f3f3' }}>
              <h3 className="text-lg font-extrabold" style={{ color: '#1b1c1c' }}>Recent Quiz Sessions</h3>
              <Link href="/quizzes" className="text-sm font-bold" style={{ color: '#006590' }}>View All →</Link>
            </div>
            {loading ? (
              <div className="p-8 text-center text-sm" style={{ color: '#3e4850' }}>Loading...</div>
            ) : recentSessions.length === 0 ? (
              <div className="p-8 text-center text-sm" style={{ color: '#3e4850' }}>No quiz sessions yet.</div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-left">
                  <thead>
                    <tr style={{ backgroundColor: '#f5f3f3' }}>
                      {['Session ID', 'Score', 'Status'].map(h => (
                        <th key={h} className="p-4 text-xs font-bold uppercase tracking-widest"
                          style={{ color: '#3e4850' }}>{h}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {(recentSessions as Record<string, unknown>[]).slice(0, 5).map((s, i) => (
                      <tr key={i} style={{ borderTop: '1px solid #efeded' }}
                        onMouseEnter={(e) => (e.currentTarget.style.backgroundColor = '#f5f3f3')}
                        onMouseLeave={(e) => (e.currentTarget.style.backgroundColor = '')}>
                        <td className="p-4 font-bold text-sm" style={{ color: '#1b1c1c' }}>
                          #{String(s.id ?? i + 1)}
                        </td>
                        <td className="p-4 text-sm font-bold" style={{ color: '#006590' }}>
                          {s.score != null ? `${s.score}` : 'N/A'}
                        </td>
                        <td className="p-4">
                          <span className="px-3 py-1 rounded-full text-xs font-bold"
                            style={{ backgroundColor: '#c8e6ff', color: '#004c6e' }}>
                            {String(s.status ?? 'completed')}
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>

          {/* AI Feature Card */}
          <div className="rounded-3xl p-6 space-y-6 relative overflow-hidden"
            style={{ backgroundColor: '#e8f4ff', border: '2px solid #006590', borderBottom: '8px solid #006590' }}>
            <div className="flex items-center gap-3">
              <div className="w-12 h-12 rounded-2xl flex items-center justify-center"
                style={{ backgroundColor: '#006590', borderBottom: '4px solid #004c6e' }}>
                <span className="material-symbols-outlined" style={{ color: '#ffffff' }}>smart_toy</span>
              </div>
              <h3 className="text-xl font-extrabold" style={{ color: '#00405d' }}>AI Engine</h3>
            </div>
            {[
              { label: 'FSRS Scheduling', on: true },
              { label: 'Adaptive Difficulty', on: true },
              { label: 'Review Reminders', on: true },
            ].map((feat) => (
              <div key={feat.label} className="flex justify-between items-center p-3 rounded-xl"
                style={{ backgroundColor: 'rgba(255,255,255,0.6)', border: '2px solid #bdc8d2' }}>
                <span className="font-bold text-sm" style={{ color: '#1b1c1c' }}>{feat.label}</span>
                <div className="w-11 h-6 rounded-full flex items-center px-0.5 cursor-pointer"
                  style={{ backgroundColor: feat.on ? '#006590' : '#bdc8d2' }}>
                  <div className="w-5 h-5 rounded-full bg-white transition-all"
                    style={{ marginLeft: feat.on ? 'auto' : '0' }} />
                </div>
              </div>
            ))}
            <Link href="/settings"
              className="btn-tactile w-full flex items-center justify-center py-3 rounded-xl font-bold text-sm uppercase tracking-wide"
              style={{ backgroundColor: '#006590', color: '#ffffff', borderBottom: '4px solid #004c6e' }}>
              Configure Engine
            </Link>
          </div>
        </div>

        {/* Quick Nav */}
        <section className="grid grid-cols-2 md:grid-cols-4 gap-4 pb-8">
          {[
            { href: '/flashcards', icon: 'style', label: 'Flashcards', color: '#006590' },
            { href: '/decks', icon: 'collections_bookmark', label: 'Decks', color: '#843ab4' },
            { href: '/users', icon: 'group', label: 'Users', color: '#755b00' },
            { href: '/analytics', icon: 'analytics', label: 'Analytics', color: '#006590' },
          ].map((item) => (
            <Link key={item.href} href={item.href}
              className="btn-tactile p-5 rounded-2xl flex items-center gap-3 group"
              style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}
              onMouseEnter={(e) => {
                (e.currentTarget as HTMLElement).style.borderColor = item.color;
              }}
              onMouseLeave={(e) => {
                (e.currentTarget as HTMLElement).style.borderColor = '#bdc8d2';
                (e.currentTarget as HTMLElement).style.borderBottomColor = '#bdc8d2';
              }}
            >
              <span className="material-symbols-outlined text-2xl" style={{ color: item.color }}>{item.icon}</span>
              <span className="font-bold text-sm" style={{ color: '#1b1c1c' }}>{item.label}</span>
            </Link>
          ))}
        </section>
      </div>
    </AdminLayout>
  );
}
