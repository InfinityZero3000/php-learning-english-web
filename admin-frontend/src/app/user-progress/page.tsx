'use client';

import { useEffect, useState } from 'react';
import AdminLayout from '@/components/AdminLayout';
import { progress } from '@/lib/api';

interface ProgressStats {
  totalWords?: number;
  mastered?: number;
  learning?: number;
  reviewing?: number;
  accuracy?: number;
  totalCorrect?: number;
  totalIncorrect?: number;
}

interface ProgressItem {
  id: number;
  mastery?: string;
  correctCount?: number;
  incorrectCount?: number;
  fsrsStability?: number;
  fsrsDifficulty?: number;
  lastStudied?: string;
  nextReview?: string;
  word?: { id: number; word: string; translation?: string; meaning?: string };
}

const masteryStyle: Record<string, { bg: string; color: string }> = {
  NEW: { bg: '#efeded', color: '#3e4850' },
  LEARNING: { bg: '#ffdf92', color: '#594400' },
  REVIEWING: { bg: '#c8e6ff', color: '#004c6e' },
  MASTERED: { bg: '#d8f3dc', color: '#1b5e20' },
};

function fmt(dt?: string) {
  if (!dt) return '—';
  try { return new Intl.DateTimeFormat('en', { dateStyle: 'medium' }).format(new Date(dt)); }
  catch { return dt; }
}

export default function UserProgressPage() {
  const [stats, setStats] = useState<ProgressStats | null>(null);
  const [items, setItems] = useState<ProgressItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [masteryFilter, setMasteryFilter] = useState('');
  const [search, setSearch] = useState('');

  useEffect(() => {
    Promise.allSettled([progress.stats(), progress.all()]).then(([s, p]) => {
      if (s.status === 'fulfilled') setStats(s.value as ProgressStats);
      if (p.status === 'fulfilled') setItems((Array.isArray(p.value) ? p.value : []) as ProgressItem[]);
    }).finally(() => setLoading(false));
  }, []);

  const mastered = stats?.mastered ?? items.filter(i => i.mastery === 'MASTERED').length;
  const learning = stats?.learning ?? items.filter(i => i.mastery === 'LEARNING').length;
  const reviewing = stats?.reviewing ?? items.filter(i => i.mastery === 'REVIEWING').length;
  const acc = stats?.accuracy ?? 0;

  const filtered = items.filter(item => {
    const matchMastery = !masteryFilter || (item.mastery ?? 'NEW') === masteryFilter;
    const matchSearch = !search || item.word?.word?.toLowerCase().includes(search.toLowerCase());
    return matchMastery && matchSearch;
  });

  return (
    <AdminLayout title="User Progress">
      <div className="space-y-6">
        {/* Header */}
        <div>
          <h2 className="text-2xl font-extrabold" style={{ color: '#1b1c1c', letterSpacing: '-0.01em' }}>User Progress</h2>
          <p className="text-sm font-medium mt-0.5" style={{ color: '#3e4850' }}>Mastery levels and FSRS state per flashcard</p>
        </div>

        {/* Stats row */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-5">
          {[
            { label: 'Total Words', value: loading ? '…' : (stats?.totalWords ?? items.length).toLocaleString(), color: '#006590', bg: '#e8f4ff', icon: 'style' },
            { label: 'Mastered', value: loading ? '…' : mastered, color: '#1b5e20', bg: '#d8f3dc', icon: 'workspace_premium' },
            { label: 'Learning', value: loading ? '…' : learning, color: '#594400', bg: '#ffdf92', icon: 'school' },
            { label: 'Accuracy', value: loading ? '…' : `${acc.toFixed ? acc.toFixed(1) : acc}%`, color: '#843ab4', bg: '#f4d9ff', icon: 'target' },
          ].map(s => (
            <div key={s.label} className="rounded-2xl p-5" style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
              <div className="w-10 h-10 rounded-xl flex items-center justify-center mb-3" style={{ backgroundColor: s.bg }}>
                <span className="material-symbols-outlined" style={{ color: s.color, fontSize: '20px', fontVariationSettings: "'FILL' 1" }}>{s.icon}</span>
              </div>
              <p className="text-2xl font-extrabold" style={{ color: s.color }}>{s.value}</p>
              <p className="text-xs font-bold uppercase tracking-widest mt-1" style={{ color: '#6e7881' }}>{s.label}</p>
            </div>
          ))}
        </div>

        {/* Filters */}
        <div className="flex flex-wrap gap-3">
          <div className="relative">
            <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-lg" style={{ color: '#3e4850' }}>search</span>
            <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search by word..." className="pl-10 pr-4 py-2.5 rounded-xl text-sm font-medium outline-none" style={{ border: '2px solid #bdc8d2', backgroundColor: '#ffffff', color: '#1b1c1c', width: '220px' }} onFocus={e => (e.target.style.borderColor = '#006590')} onBlur={e => (e.target.style.borderColor = '#bdc8d2')} />
          </div>
          {(['', 'NEW', 'LEARNING', 'REVIEWING', 'MASTERED'] as const).map(m => (
            <button key={m} onClick={() => setMasteryFilter(m)} className="px-4 py-2.5 rounded-xl text-sm font-bold transition-all" style={masteryFilter === m ? { backgroundColor: '#006590', color: '#ffffff', border: '2px solid #006590' } : { backgroundColor: '#ffffff', color: '#3e4850', border: '2px solid #bdc8d2' }}>
              {m || 'All'}
            </button>
          ))}
        </div>

        {/* Table */}
        <div className="rounded-3xl" style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
          <div className="p-5 flex justify-between items-center rounded-t-[22px]" style={{ borderBottom: '2px solid #bdc8d2', backgroundColor: '#f5f3f3' }}>
            <h3 className="text-base font-extrabold" style={{ color: '#1b1c1c' }}>Word Progress ({filtered.length})</h3>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-left">
              <thead>
                <tr style={{ borderBottom: '2px solid #efeded' }}>
                  {['Word', 'Mastery', 'Correct', 'Wrong', 'Stability', 'Difficulty', 'Last Reviewed', 'Next Due'].map(h => (
                    <th key={h} className="px-5 py-4 text-xs font-bold uppercase tracking-widest" style={{ color: '#3e4850' }}>{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr><td colSpan={8} className="p-10 text-center text-sm" style={{ color: '#3e4850' }}>Loading progress data...</td></tr>
                ) : filtered.length === 0 ? (
                  <tr><td colSpan={8} className="p-10 text-center">
                    <span className="material-symbols-outlined text-4xl block mb-2" style={{ color: '#bdc8d2' }}>bar_chart</span>
                    <p className="text-sm font-medium" style={{ color: '#3e4850' }}>No progress records found. Complete quizzes to generate data.</p>
                  </td></tr>
                ) : filtered.map(item => {
                  const mastery = item.mastery ?? 'NEW';
                  const ms = masteryStyle[mastery] ?? masteryStyle.NEW;
                  const correct = item.correctCount ?? 0;
                  const wrong = item.incorrectCount ?? 0;
                  const total = correct + wrong;
                  return (
                    <tr key={item.id} style={{ borderTop: '1px solid #efeded' }} onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#f5f3f3')} onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
                      <td className="px-5 py-4">
                        <p className="font-bold text-sm" style={{ color: '#1b1c1c' }}>{item.word?.word ?? `#${item.id}`}</p>
                        {item.word?.translation && <p className="text-xs mt-0.5" style={{ color: '#3e4850' }}>{item.word.translation}</p>}
                      </td>
                      <td className="px-5 py-4">
                        <span className="px-3 py-1 rounded-full text-xs font-bold" style={{ backgroundColor: ms.bg, color: ms.color }}>{mastery}</span>
                      </td>
                      <td className="px-5 py-4 font-bold text-sm" style={{ color: '#1b5e20' }}>{correct}</td>
                      <td className="px-5 py-4 font-bold text-sm" style={{ color: '#ba1a1a' }}>{wrong}</td>
                      <td className="px-5 py-4 font-bold text-sm" style={{ color: '#006590' }}>
                        {item.fsrsStability != null ? item.fsrsStability.toFixed(2) : '—'}
                      </td>
                      <td className="px-5 py-4 font-bold text-sm" style={{ color: '#843ab4' }}>
                        {item.fsrsDifficulty != null ? item.fsrsDifficulty.toFixed(2) : '—'}
                      </td>
                      <td className="px-5 py-4 text-sm" style={{ color: '#3e4850' }}>{fmt(item.lastStudied)}</td>
                      <td className="px-5 py-4 text-sm" style={{ color: total > 0 ? '#755b00' : '#bdc8d2' }}>
                        {fmt(item.nextReview)}
                      </td>
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
