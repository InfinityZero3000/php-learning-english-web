'use client';

import { useEffect, useState } from 'react';
import AdminLayout from '@/components/AdminLayout';
import { words, quiz, fsrs, progress } from '@/lib/api';

interface PlatformStats {
  wordCount: number;
  quizSessions: number;
  avgScore: number;
  accuracy: number;
  dueCount: number;
  retentionRate: number;
  avgStability: number;
  mastered: number;
}

// 7-day retention bar heights (percent)
const RETENTION_BARS = [42, 58, 47, 88, 63, 77, 95];
const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

type Period = '7D' | '30D' | '90D';

export default function AnalyticsPage() {
  const [stats, setStats] = useState<PlatformStats>({
    wordCount: 0, quizSessions: 0, avgScore: 0, accuracy: 0,
    dueCount: 0, retentionRate: 0, avgStability: 0, mastered: 0,
  });
  const [loading, setLoading] = useState(true);
  const [period, setPeriod] = useState<Period>('7D');
  const [toastVisible, setToastVisible] = useState(false);

  useEffect(() => {
    Promise.allSettled([
      words.count(),
      quiz.stats(),
      fsrs.stats(),
      progress.stats(),
    ]).then(([wc, qs, fs, ps]) => {
      const q = qs.status === 'fulfilled' ? (qs.value as Record<string, number>) : {};
      const f = fs.status === 'fulfilled' ? (fs.value as Record<string, number>) : {};
      const p = ps.status === 'fulfilled' ? (ps.value as Record<string, number>) : {};
      setStats({
        wordCount: wc.status === 'fulfilled' ? (wc.value as number) : 0,
        quizSessions: q.totalSessions ?? q.completedSessions ?? 0,
        avgScore: q.averageScore ?? 0,
        accuracy: q.accuracy ?? p.accuracy ?? 0,
        dueCount: f.dueCount ?? 0,
        retentionRate: f.retentionRate ?? 0,
        avgStability: f.averageStability ?? 0,
        mastered: p.mastered ?? f.masteredCount ?? 0,
      });
    }).finally(() => setLoading(false));
  }, []);

  const showToast = () => {
    setToastVisible(true);
    setTimeout(() => setToastVisible(false), 3000);
  };

  const v = (val: number | string) => (loading ? '…' : val);

  const retentionPct = stats.retentionRate ? Math.round(stats.retentionRate) : 0;
  const quizAccuracy = stats.accuracy ? stats.accuracy.toFixed(1) : 0;

  return (
    <AdminLayout title="Analytics">
      <div className="space-y-8">
        {/* Page Header */}
        <div className="flex justify-between items-end">
          <div>
            <h2 className="text-3xl font-black" style={{ color: '#1b1c1c', letterSpacing: '-0.02em' }}>
              AI Engine &amp; Performance
            </h2>
            <p className="font-medium mt-1" style={{ color: '#3e4850' }}>
              Monitoring FSRS scheduling engine and learner engagement metrics.
            </p>
          </div>
          <div className="flex gap-4">
            <button onClick={showToast}
              className="btn-tactile flex items-center gap-2 px-6 py-3 rounded-2xl font-bold text-sm"
              style={{ backgroundColor: '#ffdf92', color: '#594400', borderBottom: '4px solid #b89100' }}>
              <span className="material-symbols-outlined text-lg">refresh</span>
              Retrain Models
            </button>
            <button onClick={showToast}
              className="btn-tactile flex items-center gap-2 px-6 py-3 rounded-2xl font-bold text-sm"
              style={{ backgroundColor: '#006590', color: '#ffffff', borderBottom: '4px solid #004c6e' }}>
              <span className="material-symbols-outlined text-lg">cloud_download</span>
              Export Report
            </button>
          </div>
        </div>

        {/* Bento grid */}
        <div className="grid grid-cols-12 gap-6">
          {/* Main Retention Chart — col 8 */}
          <div className="col-span-12 lg:col-span-8 rounded-3xl p-8 flex flex-col gap-8"
            style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
            <div className="flex justify-between items-center">
              <h3 className="font-bold text-xl flex items-center gap-2" style={{ color: '#1b1c1c' }}>
                <span className="material-symbols-outlined"
                  style={{ color: '#1cb0f6', fontVariationSettings: "'FILL' 1" }}>trending_up</span>
                Overall Learner Retention
              </h3>
              <div className="flex gap-1 p-1 rounded-xl" style={{ backgroundColor: '#f5f3f3' }}>
                {(['7D', '30D', '90D'] as Period[]).map(p => (
                  <button key={p} onClick={() => setPeriod(p)}
                    className="px-4 py-1.5 rounded-lg font-bold text-xs transition-all"
                    style={period === p
                      ? { backgroundColor: '#ffffff', color: '#1cb0f6', boxShadow: '0 1px 4px rgba(0,0,0,0.1)' }
                      : { color: '#6e7881' }}>
                    {p}
                  </button>
                ))}
              </div>
            </div>

            {/* Bar chart */}
            <div className="h-64 w-full flex items-end justify-between gap-4 px-4">
              {RETENTION_BARS.map((h, i) => (
                <div key={i} className="flex-1 flex flex-col items-center gap-2">
                  <div className="w-full rounded-t-2xl transition-all"
                    style={{
                      height: `${h * 2.56}px`,
                      backgroundColor: i === 3 ? '#1cb0f6' : '#e8f4ff',
                      border: i === 3 ? '2px solid #0096e0' : '2px solid #bdc8d2',
                      borderBottomWidth: i === 3 ? '0' : '0',
                      borderTopWidth: i === 3 ? '4px' : '2px',
                    }} />
                  <span className="text-[10px] font-bold" style={{ color: '#6e7881' }}>{DAYS[i]}</span>
                </div>
              ))}
            </div>

            {/* Summary stats below chart */}
            <div className="grid grid-cols-3 gap-8 pt-6" style={{ borderTop: '2px solid #f5f3f3' }}>
              <div>
                <p className="text-[10px] font-black uppercase tracking-widest mb-1" style={{ color: '#6e7881' }}>Avg. Weekly Growth</p>
                <p className="text-2xl font-black" style={{ color: '#1cb0f6' }}>+12.4%</p>
              </div>
              <div>
                <p className="text-[10px] font-black uppercase tracking-widest mb-1" style={{ color: '#6e7881' }}>Daily Active Users</p>
                <p className="text-2xl font-black" style={{ color: '#1b1c1c' }}>
                  {v(stats.quizSessions > 0 ? `${Math.round(stats.quizSessions / 30)}` : '—')}
                </p>
              </div>
              <div>
                <p className="text-[10px] font-black uppercase tracking-widest mb-1" style={{ color: '#6e7881' }}>Retention Rate</p>
                <p className="text-2xl font-black" style={{ color: retentionPct >= 85 ? '#1b5e20' : '#ba1a1a' }}>
                  {v(retentionPct ? `${retentionPct}%` : 'N/A')}
                </p>
              </div>
            </div>
          </div>

          {/* Right column — col 4 */}
          <div className="col-span-12 lg:col-span-4 flex flex-col gap-6">
            {/* FSRS Accuracy card */}
            <div className="rounded-3xl p-6 flex flex-col justify-between relative overflow-hidden"
              style={{ backgroundColor: '#1cb0f6', border: '2px solid #0096e0', borderBottom: '6px solid #0096e0', minHeight: '180px' }}>
              <div className="absolute top-0 right-0 w-32 h-32 rounded-full -mr-16 -mt-16"
                style={{ backgroundColor: 'rgba(255,255,255,0.1)' }} />
              <div className="flex justify-between items-start relative z-10">
                <span className="material-symbols-outlined text-4xl text-white"
                  style={{ fontVariationSettings: "'FILL' 1" }}>psychology</span>
                <span className="px-3 py-1 rounded-full text-[10px] font-black tracking-widest uppercase text-white"
                  style={{ backgroundColor: 'rgba(255,255,255,0.2)', border: '1px solid rgba(255,255,255,0.3)' }}>LIVE</span>
              </div>
              <div className="relative z-10 mt-4">
                <h4 className="text-2xl font-black text-white mb-1">
                  {v(stats.retentionRate ? `${retentionPct}% Retention` : 'FSRS Engine')}
                </h4>
                <p className="font-bold text-sm opacity-80 text-white">Spaced Repetition System</p>
                <div className="mt-4 w-full h-3 rounded-full overflow-hidden"
                  style={{ backgroundColor: 'rgba(0,0,0,0.15)', border: '1px solid rgba(255,255,255,0.2)' }}>
                  <div className="bg-white h-full rounded-full"
                    style={{ width: `${retentionPct || 85}%`, transition: 'width 0.8s ease' }} />
                </div>
              </div>
            </div>

            {/* Quiz score card */}
            <div className="rounded-3xl p-6 flex flex-col justify-between relative overflow-hidden"
              style={{ backgroundColor: '#ffdf92', border: '2px solid #f4bf00', borderBottom: '6px solid #b89100', minHeight: '180px' }}>
              <div className="absolute top-0 right-0 w-32 h-32 rounded-full -mr-16 -mt-16"
                style={{ backgroundColor: 'rgba(0,0,0,0.05)' }} />
              <div className="flex justify-between items-start relative z-10">
                <span className="material-symbols-outlined text-4xl" style={{ color: '#594400', fontVariationSettings: "'FILL' 1" }}>quiz</span>
                <span className="px-3 py-1 rounded-full text-[10px] font-black tracking-widest uppercase"
                  style={{ backgroundColor: 'rgba(0,0,0,0.05)', color: '#594400', border: '1px solid rgba(0,0,0,0.1)' }}>v2.4</span>
              </div>
              <div className="relative z-10 mt-4">
                <h4 className="text-2xl font-black mb-1" style={{ color: '#594400' }}>Quiz Performance</h4>
                <p className="font-bold text-sm" style={{ color: '#755b00', opacity: 0.8 }}>Avg. Score: {v(stats.avgScore ? `${Math.round(stats.avgScore)}%` : 'N/A')}</p>
                <div className="mt-4 flex items-center justify-between">
                  <div className="flex gap-0.5">
                    {[1, 2, 3, 4, 5].map(s => (
                      <span key={s} className="material-symbols-outlined text-2xl"
                        style={{ color: '#594400', fontVariationSettings: `'FILL' ${stats.avgScore >= s * 20 ? 1 : 0.3}` }}>star</span>
                    ))}
                  </div>
                  <span className="font-black text-xl" style={{ color: '#594400' }}>
                    {v(quizAccuracy ? `${quizAccuracy}%` : 'N/A')}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Controls Section */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {/* FSRS Config */}
          <div className="rounded-3xl p-8"
            style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
            <h3 className="text-xl font-bold flex items-center gap-3 mb-8" style={{ color: '#1b1c1c' }}>
              <span className="material-symbols-outlined" style={{ color: '#1cb0f6', fontVariationSettings: "'FILL' 1" }}>
                record_voice_over
              </span>
              FSRS Settings
            </h3>
            <div className="space-y-6">
              <div>
                <label className="text-[10px] font-black uppercase tracking-widest block mb-3" style={{ color: '#6e7881' }}>
                  Scheduler Engine
                </label>
                <div className="p-1.5 rounded-2xl flex gap-2" style={{ backgroundColor: '#f5f3f3', border: '2px solid #efeded' }}>
                  <button className="flex-1 py-2.5 rounded-xl font-bold text-sm shadow-sm"
                    style={{ backgroundColor: '#ffffff', color: '#1cb0f6', border: '2px solid #bdc8d2' }}>
                    FSRS v5
                  </button>
                  <button className="flex-1 py-2.5 font-bold text-sm rounded-xl"
                    style={{ color: '#6e7881' }}>
                    SM-2
                  </button>
                </div>
              </div>
              <div>
                <div className="flex justify-between mb-3">
                  <label className="text-[10px] font-black uppercase tracking-widest" style={{ color: '#6e7881' }}>
                    Retention Target
                  </label>
                  <span className="font-black text-sm" style={{ color: '#1cb0f6' }}>
                    {retentionPct || 90}%
                  </span>
                </div>
                <div className="h-2 rounded-full overflow-hidden" style={{ backgroundColor: '#efeded' }}>
                  <div className="h-full rounded-full" style={{ width: `${retentionPct || 90}%`, backgroundColor: '#1cb0f6' }} />
                </div>
              </div>
            </div>
          </div>

          {/* Scheduling config */}
          <div className="rounded-3xl p-8"
            style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
            <h3 className="text-xl font-bold flex items-center gap-3 mb-8" style={{ color: '#1b1c1c' }}>
              <span className="material-symbols-outlined" style={{ color: '#1cb0f6', fontVariationSettings: "'FILL' 1" }}>tune</span>
              Scheduling Rules
            </h3>
            <div className="space-y-3">
              {[
                { label: 'Adaptive Difficulty', sub: 'Auto-adjust per learner', on: true },
                { label: 'Workload Smoothing', sub: 'Spread review spikes', on: true },
                { label: 'Cold-Start Heuristics', sub: 'New user boosting', on: false },
              ].map(item => (
                <div key={item.label} className="flex items-center justify-between p-4 rounded-2xl transition-all"
                  style={{ backgroundColor: '#f5f3f3', border: '2px solid #efeded' }}>
                  <div>
                    <p className="font-bold text-sm" style={{ color: '#1b1c1c' }}>{item.label}</p>
                    <p className="text-[10px] font-medium" style={{ color: '#6e7881' }}>{item.sub}</p>
                  </div>
                  <div className="w-11 h-6 rounded-full flex items-center px-0.5 cursor-pointer transition-colors"
                    style={{ backgroundColor: item.on ? '#1cb0f6' : '#bdc8d2' }}>
                    <div className="w-5 h-5 rounded-full bg-white shadow-sm transition-all"
                      style={{ marginLeft: item.on ? 'auto' : '0' }} />
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* System health */}
          <div className="rounded-3xl p-8 flex flex-col"
            style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
            <h3 className="text-xl font-bold flex items-center gap-3 mb-8" style={{ color: '#1b1c1c' }}>
              <span className="material-symbols-outlined" style={{ color: '#1cb0f6', fontVariationSettings: "'FILL' 1" }}>analytics</span>
              System Health
            </h3>
            <div className="space-y-3 flex-1 flex flex-col">
              {stats.dueCount > 100 && (
                <div className="p-4 rounded-2xl flex items-start gap-3"
                  style={{ backgroundColor: '#fff0f0', border: '2px solid #ffb4ab' }}>
                  <span className="material-symbols-outlined text-xl mt-0.5" style={{ color: '#ba1a1a' }}>warning</span>
                  <div>
                    <p className="font-bold text-xs" style={{ color: '#ba1a1a' }}>High Due Count</p>
                    <p className="text-[10px] font-medium mt-1" style={{ color: '#3e4850' }}>
                      {stats.dueCount} cards overdue — consider sending reminders.
                    </p>
                  </div>
                </div>
              )}
              <div className="rounded-2xl p-5 flex-1"
                style={{ backgroundColor: '#f5f3f3', border: '2px solid #efeded' }}>
                <p className="text-[10px] font-black uppercase tracking-widest mb-4" style={{ color: '#6e7881' }}>Services</p>
                <div className="space-y-3.5">
                  {[
                    { name: 'FSRS Engine', val: '142ms' },
                    { name: 'Uptime', val: '99.98%' },
                    { name: 'Throughput', val: '4.2k req/s' },
                  ].map(s => (
                    <div key={s.name} className="flex justify-between items-center text-sm">
                      <span className="font-medium" style={{ color: '#3e4850' }}>{s.name}</span>
                      <span className="font-black" style={{ color: '#1cb0f6' }}>{s.val}</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Content Relevance Table */}
        <div className="rounded-3xl"
          style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
          <div className="p-8 flex justify-between items-center" style={{ borderBottom: '2px solid #f5f3f3' }}>
            <h3 className="text-xl font-bold" style={{ color: '#1b1c1c' }}>FSRS Module Performance</h3>
            <div className="relative">
              <select className="rounded-xl px-5 py-2.5 font-bold text-sm outline-none appearance-none pr-12"
                style={{ backgroundColor: '#f5f3f3', border: '2px solid #efeded', color: '#1b1c1c' }}>
                <option>All Topics</option>
                <option>Business</option>
                <option>Travel</option>
                <option>Technology</option>
              </select>
              <span className="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none" style={{ color: '#6e7881' }}>expand_more</span>
            </div>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-left">
              <thead style={{ backgroundColor: '#f5f3f3', borderBottom: '2px solid #efeded' }}>
                <tr>
                  {['Module', 'Avg. Word', 'Match Rate', 'Status', 'Action'].map(h => (
                    <th key={h} className="px-8 py-5 text-[10px] font-black uppercase tracking-widest" style={{ color: '#6e7881' }}>{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {[
                  { module: 'Beginner Vocab #1', word: 'hello', rate: 98, status: 'Perfect', good: true },
                  { module: 'Business English #12', word: 'negotiate', rate: 82, status: 'Stable', good: true },
                  { module: 'Travel Phrases #04', word: 'reservation', rate: 65, status: 'Warning', good: false },
                  { module: 'Tech Vocabulary #8', word: 'algorithm', rate: 91, status: 'Perfect', good: true },
                ].map(row => (
                  <tr key={row.module}
                    style={{ borderTop: '2px solid #f5f3f3' }}
                    onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#f9f8f8')}
                    onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
                    <td className="px-8 py-6 font-bold text-sm" style={{ color: '#1b1c1c' }}>{row.module}</td>
                    <td className="px-8 py-6">
                      <span className="px-4 py-1.5 rounded-full font-black text-[10px] uppercase tracking-wider"
                        style={{ backgroundColor: '#e8f4ff', color: '#1cb0f6', border: '1px solid #bdc8d2' }}>
                        {row.word}
                      </span>
                    </td>
                    <td className="px-8 py-6">
                      <div className="flex items-center gap-4">
                        <div className="flex-1 h-2.5 rounded-full overflow-hidden max-w-32"
                          style={{ backgroundColor: '#efeded' }}>
                          <div className="h-full rounded-full"
                            style={{ width: `${row.rate}%`, backgroundColor: row.rate >= 80 ? '#1cb0f6' : '#ba1a1a' }} />
                        </div>
                        <span className="font-black text-sm" style={{ color: '#1b1c1c' }}>{row.rate}%</span>
                      </div>
                    </td>
                    <td className="px-8 py-6">
                      <span className="flex items-center gap-2 font-bold text-sm"
                        style={{ color: row.good ? '#1b5e20' : '#ba1a1a' }}>
                        <span className="w-2.5 h-2.5 rounded-full"
                          style={{ backgroundColor: row.good ? '#22c55e' : '#ba1a1a', boxShadow: `0 0 8px ${row.good ? 'rgba(34,197,94,0.4)' : 'rgba(186,26,26,0.4)'}`, animation: !row.good ? 'pulse 2s infinite' : 'none' }} />
                        {row.status}
                      </span>
                    </td>
                    <td className="px-8 py-6 text-right">
                      <button className="font-black text-[10px] uppercase tracking-wide hover:underline"
                        style={{ color: '#1cb0f6' }}>
                        Edit Config
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* Toast */}
      <div className="fixed bottom-8 right-8 z-50 flex items-center gap-4 px-8 py-5 rounded-3xl shadow-2xl transition-all duration-500"
        style={{
          backgroundColor: '#303031',
          color: '#ffffff',
          border: '2px solid rgba(255,255,255,0.1)',
          transform: toastVisible ? 'translateY(0)' : 'translateY(8rem)',
          opacity: toastVisible ? 1 : 0,
        }}>
        <div className="w-10 h-10 rounded-full flex items-center justify-center"
          style={{ backgroundColor: 'rgba(34,197,94,0.2)', border: '2px solid rgba(34,197,94,0.3)' }}>
          <span className="material-symbols-outlined font-bold" style={{ color: '#22c55e' }}>check_circle</span>
        </div>
        <div>
          <p className="font-bold text-sm">Action Triggered</p>
          <p className="text-[10px] opacity-70 font-medium uppercase tracking-widest">Configuration synchronized!</p>
        </div>
      </div>
    </AdminLayout>
  );
}
