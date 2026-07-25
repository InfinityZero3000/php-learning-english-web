'use client';

import { useEffect, useState } from 'react';
import AdminLayout from '@/components/AdminLayout';
import { quiz } from '@/lib/api';

interface QuizStats {
  totalSessions?: number;
  completedSessions?: number;
  averageScore?: number;
  accuracy?: number;
  correctAnswers?: number;
  incorrectAnswers?: number;
}

interface QuizSession {
  id: number;
  totalQuestions?: number;
  correctAnswers?: number;
  incorrectAnswers?: number;
  score?: number;
  startedAt?: string;
  completedAt?: string;
  status?: string;
  difficulty?: string;
  category?: string;
}

function scoreBadge(score: number) {
  if (score >= 80) return { bg: '#d8f3dc', color: '#1b5e20' };
  if (score >= 60) return { bg: '#ffdf92', color: '#594400' };
  return { bg: '#ffdad6', color: '#93000a' };
}

function fmt(dt?: string) {
  if (!dt) return '—';
  try { return new Intl.DateTimeFormat('en', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(dt)); }
  catch { return dt; }
}

export default function QuizzesPage() {
  const [stats, setStats] = useState<QuizStats | null>(null);
  const [sessions, setSessions] = useState<QuizSession[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    Promise.allSettled([quiz.stats(), quiz.recentSessions()]).then(([s, r]) => {
      if (s.status === 'fulfilled') setStats(s.value as QuizStats);
      if (r.status === 'fulfilled') setSessions((Array.isArray(r.value) ? r.value : []) as QuizSession[]);
    }).finally(() => setLoading(false));
  }, []);

  const accuracy = stats?.accuracy ?? (stats?.correctAnswers && stats?.incorrectAnswers
    ? Math.round((stats.correctAnswers / (stats.correctAnswers + stats.incorrectAnswers)) * 100)
    : 0);

  return (
    <AdminLayout title="Quizzes">
      <div className="space-y-6">
        {/* Header */}
        <div>
          <h2 className="text-2xl font-extrabold" style={{ color: '#1b1c1c', letterSpacing: '-0.01em' }}>Quizzes</h2>
          <p className="text-sm font-medium mt-0.5" style={{ color: '#3e4850' }}>Platform-wide quiz performance and session history</p>
        </div>

        {/* Stats */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-5">
          {[
            { icon: 'quiz', label: 'Total Sessions', value: loading ? '…' : (stats?.totalSessions ?? sessions.length).toLocaleString(), color: '#006590', bg: '#e8f4ff' },
            { icon: 'check_circle', label: 'Completed', value: loading ? '…' : (stats?.completedSessions ?? sessions.length).toLocaleString(), color: '#1b5e20', bg: '#d8f3dc' },
            { icon: 'target', label: 'Avg Score', value: loading ? '…' : `${Math.round(stats?.averageScore ?? 0)}%`, color: '#755b00', bg: '#ffdf92' },
            { icon: 'percent', label: 'Accuracy', value: loading ? '…' : `${accuracy}%`, color: '#843ab4', bg: '#f4d9ff' },
          ].map(s => (
            <div key={s.label} className="rounded-2xl p-5" style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
              <div className="flex items-center gap-3 mb-3">
                <div className="w-10 h-10 rounded-xl flex items-center justify-center" style={{ backgroundColor: s.bg }}>
                  <span className="material-symbols-outlined" style={{ color: s.color, fontSize: '20px', fontVariationSettings: "'FILL' 1" }}>{s.icon}</span>
                </div>
              </div>
              <p className="text-2xl font-extrabold" style={{ color: s.color }}>{s.value}</p>
              <p className="text-xs font-bold uppercase tracking-widest mt-1" style={{ color: '#6e7881' }}>{s.label}</p>
            </div>
          ))}
        </div>

        {/* Sessions table */}
        <div className="rounded-3xl" style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
          <div className="p-5 flex justify-between items-center rounded-t-[22px]" style={{ borderBottom: '2px solid #bdc8d2', backgroundColor: '#f5f3f3' }}>
            <h3 className="text-base font-extrabold" style={{ color: '#1b1c1c' }}>Recent Sessions</h3>
            <span className="text-xs font-bold" style={{ color: '#6e7881' }}>Latest {sessions.length}</span>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-left">
              <thead>
                <tr style={{ borderBottom: '2px solid #efeded' }}>
                  {['Session', 'Questions', 'Correct', 'Score', 'Category', 'Difficulty', 'Date'].map(h => (
                    <th key={h} className="px-5 py-4 text-xs font-bold uppercase tracking-widest" style={{ color: '#3e4850' }}>{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr><td colSpan={7} className="p-10 text-center text-sm" style={{ color: '#3e4850' }}>Loading sessions...</td></tr>
                ) : sessions.length === 0 ? (
                  <tr><td colSpan={7} className="p-10 text-center">
                    <span className="material-symbols-outlined text-4xl block mb-2" style={{ color: '#bdc8d2' }}>quiz</span>
                    <p className="text-sm font-medium" style={{ color: '#3e4850' }}>No quiz sessions found.</p>
                  </td></tr>
                ) : sessions.map((s) => {
                  const score = s.score ?? (s.totalQuestions ? Math.round(((s.correctAnswers ?? 0) / s.totalQuestions) * 100) : 0);
                  const sb = scoreBadge(score);
                  return (
                    <tr key={s.id} style={{ borderTop: '1px solid #efeded' }} onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#f5f3f3')} onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
                      <td className="px-5 py-4 font-bold text-sm" style={{ color: '#006590' }}>#{s.id}</td>
                      <td className="px-5 py-4 font-bold text-sm" style={{ color: '#1b1c1c' }}>{s.totalQuestions ?? '—'}</td>
                      <td className="px-5 py-4 font-bold text-sm" style={{ color: '#1b5e20' }}>{s.correctAnswers ?? '—'}</td>
                      <td className="px-5 py-4">
                        <span className="px-3 py-1 rounded-full text-xs font-extrabold" style={{ backgroundColor: sb.bg, color: sb.color }}>{score}%</span>
                      </td>
                      <td className="px-5 py-4 text-sm" style={{ color: '#3e4850' }}>{s.category ?? '—'}</td>
                      <td className="px-5 py-4 text-sm" style={{ color: '#3e4850' }}>{s.difficulty ?? '—'}</td>
                      <td className="px-5 py-4 text-sm" style={{ color: '#3e4850' }}>{fmt(s.completedAt ?? s.startedAt)}</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}
