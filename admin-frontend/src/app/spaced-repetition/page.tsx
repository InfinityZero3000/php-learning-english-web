'use client';

import { useEffect, useState } from 'react';
import AdminLayout from '@/components/AdminLayout';
import { fsrs } from '@/lib/api';

interface FsrsStats {
  dueCount?: number;
  totalReviewed?: number;
  averageStability?: number;
  averageDifficulty?: number;
  retentionRate?: number;
  newCount?: number;
  learningCount?: number;
  reviewCount?: number;
  masteredCount?: number;
}

const FSRS_PARAMS = [
  { key: 'w0', label: 'Initial Stability (Again)', value: '0.4072', desc: 'Memory stability after first "Again" rating' },
  { key: 'w1', label: 'Initial Stability (Hard)', value: '1.1829', desc: 'Memory stability after first "Hard" rating' },
  { key: 'w2', label: 'Initial Stability (Good)', value: '3.1262', desc: 'Memory stability after first "Good" rating' },
  { key: 'w3', label: 'Initial Stability (Easy)', value: '15.4722', desc: 'Memory stability after first "Easy" rating' },
  { key: 'w4', label: 'Initial Difficulty', value: '7.2102', desc: 'Starting difficulty for new cards' },
  { key: 'w5', label: 'Difficulty Decay (Hard)', value: '0.5316', desc: 'How much difficulty decreases on Hard' },
  { key: 'w6', label: 'Difficulty Decay (Easy)', value: '1.0651', desc: 'How much difficulty decreases on Easy' },
  { key: 'w7', label: 'Stability Increase', value: '0.0589', desc: 'Stability growth per successful review' },
  { key: 'w8', label: 'Stability Multiplier', value: '1.5330', desc: 'Multiplier for stability after success' },
  { key: 'w9', label: 'New Stability (Lapse)', value: '0.1544', desc: 'Stability after a lapse (Again on review)' },
  { key: 'RETENTION', label: 'Target Retention', value: '90%', desc: 'Desired memory retention at review time' },
  { key: 'MAX_INTERVAL', label: 'Max Interval', value: '36500 days', desc: 'Upper bound for review interval (100 years)' },
];

export default function SpacedRepetitionPage() {
  const [stats, setStats] = useState<FsrsStats | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fsrs.stats()
      .then(res => setStats(res as FsrsStats))
      .catch(() => setStats(null))
      .finally(() => setLoading(false));
  }, []);

  const statCards = [
    { label: 'Due Today', value: loading ? '…' : (stats?.dueCount ?? 0), color: '#ba1a1a', bg: '#ffdad6', icon: 'notification_important' },
    { label: 'Total Reviewed', value: loading ? '…' : (stats?.totalReviewed ?? 0).toLocaleString(), color: '#006590', bg: '#e8f4ff', icon: 'replay' },
    { label: 'Avg. Stability', value: loading ? '…' : (stats?.averageStability != null ? stats.averageStability.toFixed(1) + ' d' : 'N/A'), color: '#1b5e20', bg: '#d8f3dc', icon: 'trending_up' },
    { label: 'Avg. Difficulty', value: loading ? '…' : (stats?.averageDifficulty != null ? stats.averageDifficulty.toFixed(2) : 'N/A'), color: '#843ab4', bg: '#f4d9ff', icon: 'psychology' },
    { label: 'Retention Rate', value: loading ? '…' : (stats?.retentionRate != null ? `${Math.round(stats.retentionRate)}%` : 'N/A'), color: '#755b00', bg: '#ffdf92', icon: 'memory' },
    { label: 'Mastered', value: loading ? '…' : (stats?.masteredCount ?? 0).toLocaleString(), color: '#1b5e20', bg: '#d8f3dc', icon: 'workspace_premium' },
  ];

  return (
    <AdminLayout title="Spaced Repetition">
      <div className="space-y-8">
        {/* Header */}
        <div>
          <h2 className="text-2xl font-extrabold" style={{ color: '#1b1c1c', letterSpacing: '-0.01em' }}>Spaced Repetition (FSRS)</h2>
          <p className="text-sm font-medium mt-0.5" style={{ color: '#3e4850' }}>
            Free Spaced Repetition Scheduler — algorithm state and parameter configuration
          </p>
        </div>

        {/* Stats */}
        <div className="grid grid-cols-2 lg:grid-cols-3 gap-5">
          {statCards.map(s => (
            <div key={s.label} className="rounded-2xl p-5" style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
              <div className="flex items-center justify-between mb-3">
                <div className="w-10 h-10 rounded-xl flex items-center justify-center" style={{ backgroundColor: s.bg }}>
                  <span className="material-symbols-outlined" style={{ color: s.color, fontSize: '20px', fontVariationSettings: "'FILL' 1" }}>{s.icon}</span>
                </div>
              </div>
              <p className="text-2xl font-extrabold" style={{ color: s.color }}>{s.value}</p>
              <p className="text-xs font-bold uppercase tracking-widest mt-1" style={{ color: '#6e7881' }}>{s.label}</p>
            </div>
          ))}
        </div>

        {/* FSRS info banner */}
        <div className="rounded-2xl p-6 flex items-start gap-4" style={{ backgroundColor: '#e8f4ff', border: '2px solid #88ceff' }}>
          <span className="material-symbols-outlined mt-0.5" style={{ color: '#006590', fontSize: '22px' }}>info</span>
          <div>
            <p className="text-sm font-bold" style={{ color: '#00405d' }}>FSRS v4 — Free Spaced Repetition Scheduler</p>
            <p className="text-sm font-medium mt-1" style={{ color: '#004c6e' }}>
              This system uses the FSRS algorithm to schedule reviews based on predicted memory stability and difficulty.
              Each card tracks: <strong>stability</strong> (days until 90% retention), <strong>difficulty</strong> (inherent hardness),
              <strong> retrievability</strong> (current memory strength), and <strong>lapses</strong>.
              Review intervals are recalculated after every rating (Again / Hard / Good / Easy).
            </p>
          </div>
        </div>

        {/* Parameters table */}
        <div className="rounded-3xl" style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
          <div className="p-5 flex justify-between items-center rounded-t-[22px]" style={{ borderBottom: '2px solid #bdc8d2', backgroundColor: '#f5f3f3' }}>
            <div>
              <h3 className="text-base font-extrabold" style={{ color: '#1b1c1c' }}>FSRS Parameters (w0–w11)</h3>
              <p className="text-xs font-medium mt-0.5" style={{ color: '#6e7881' }}>Default FSRS v4 weights — configurable via optimizer</p>
            </div>
            <button className="btn-tactile flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm" style={{ backgroundColor: '#efeded', color: '#1b1c1c', borderBottom: '3px solid #bdc8d2' }} onClick={() => alert('Parameter editing — connect optimizer module')}>
              <span className="material-symbols-outlined text-base">tune</span>
              Tune Parameters
            </button>
          </div>
          <div className="divide-y" style={{ borderTop: '0', borderColor: '#efeded' }}>
            {FSRS_PARAMS.map((p, i) => (
              <div key={p.key} className="flex items-center justify-between px-5 py-4" style={{ backgroundColor: i % 2 === 0 ? '#ffffff' : '#fafafa' }} onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#f5f3f3')} onMouseLeave={e => (e.currentTarget.style.backgroundColor = i % 2 === 0 ? '#ffffff' : '#fafafa')}>
                <div className="flex-1">
                  <p className="text-sm font-bold" style={{ color: '#1b1c1c' }}>{p.label}</p>
                  <p className="text-xs font-medium mt-0.5" style={{ color: '#6e7881' }}>{p.desc}</p>
                </div>
                <div className="flex items-center gap-3 shrink-0">
                  <span className="font-mono text-sm font-extrabold px-3 py-1 rounded-lg" style={{ backgroundColor: '#e8f4ff', color: '#006590' }}>{p.value}</span>
                  <span className="text-xs font-mono" style={{ color: '#bdc8d2' }}>{p.key}</span>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Rating guide */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          {[
            { rating: 'Again', icon: 'replay', bg: '#ffdad6', color: '#93000a', desc: 'Complete blackout — resets stability to minimum' },
            { rating: 'Hard', icon: 'sentiment_dissatisfied', bg: '#ffdf92', color: '#594400', desc: 'Correct but difficult — small stability increase' },
            { rating: 'Good', icon: 'sentiment_satisfied', bg: '#c8e6ff', color: '#004c6e', desc: 'Correct with effort — standard stability growth' },
            { rating: 'Easy', icon: 'sentiment_very_satisfied', bg: '#d8f3dc', color: '#1b5e20', desc: 'Effortless recall — large stability boost' },
          ].map(r => (
            <div key={r.rating} className="rounded-2xl p-5 text-center" style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2' }}>
              <div className="w-12 h-12 rounded-xl flex items-center justify-center mx-auto mb-3" style={{ backgroundColor: r.bg }}>
                <span className="material-symbols-outlined" style={{ color: r.color, fontSize: '24px', fontVariationSettings: "'FILL' 1" }}>{r.icon}</span>
              </div>
              <p className="font-extrabold text-sm mb-1" style={{ color: r.color }}>{r.rating}</p>
              <p className="text-xs font-medium" style={{ color: '#6e7881' }}>{r.desc}</p>
            </div>
          ))}
        </div>
      </div>
    </AdminLayout>
  );
}
