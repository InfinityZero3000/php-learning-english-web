'use client';

import { useState } from 'react';
import AdminLayout from '@/components/AdminLayout';

const REPORT_TYPES = [
  {
    id: 'vocabulary',
    title: 'Vocabulary Report',
    desc: 'All flashcards with difficulty, category, part of speech, and usage statistics.',
    icon: 'style',
    bg: '#e8f4ff',
    iconColor: '#006590',
    fields: ['Word', 'Translation', 'Category', 'Difficulty', 'Times Reviewed', 'Accuracy'],
    formats: ['CSV', 'JSON'],
  },
  {
    id: 'progress',
    title: 'User Progress Report',
    desc: 'Per-word mastery levels, FSRS stability, difficulty, and next review dates.',
    icon: 'bar_chart',
    bg: '#d8f3dc',
    iconColor: '#1b5e20',
    fields: ['Word', 'Mastery', 'Stability', 'Difficulty', 'Last Reviewed', 'Next Due'],
    formats: ['CSV', 'JSON'],
  },
  {
    id: 'quiz',
    title: 'Quiz Performance Report',
    desc: 'All quiz sessions with scores, accuracy rates, and completion times.',
    icon: 'quiz',
    bg: '#f4d9ff',
    iconColor: '#843ab4',
    fields: ['Session ID', 'Questions', 'Correct', 'Score', 'Date', 'Duration'],
    formats: ['CSV', 'JSON'],
  },
  {
    id: 'fsrs',
    title: 'FSRS Analytics Report',
    desc: 'Spaced repetition metrics: stability distribution, retention rates, and interval analysis.',
    icon: 'repeat',
    bg: '#ffdf92',
    iconColor: '#755b00',
    fields: ['Card', 'Stability', 'Difficulty', 'Retrievability', 'Reps', 'Lapses'],
    formats: ['CSV', 'JSON'],
  },
  {
    id: 'users',
    title: 'User Activity Report',
    desc: 'User registration dates, last login, streak data, and activity summary.',
    icon: 'group',
    bg: '#ffdad6',
    iconColor: '#93000a',
    fields: ['Name', 'Email', 'Role', 'Created', 'Last Login', 'Streak', 'Status'],
    formats: ['CSV', 'JSON'],
  },
  {
    id: 'review_events',
    title: 'Review Events Report',
    desc: 'Full immutable log of all review submissions with ratings and response times.',
    icon: 'history',
    bg: '#c8e6ff',
    iconColor: '#004c6e',
    fields: ['User', 'Word', 'Rating', 'Correct', 'Response Ms', 'Reviewed At'],
    formats: ['CSV', 'JSON'],
  },
];

export default function ReportsPage() {
  const [generating, setGenerating] = useState<string | null>(null);

  const handleGenerate = (reportId: string, format: string) => {
    setGenerating(reportId);
    setTimeout(() => {
      setGenerating(null);
      alert(`${format} export for "${reportId}" report — connect to /api/admin/reports/${reportId}/export?format=${format.toLowerCase()} when backend is ready.`);
    }, 1200);
  };

  return (
    <AdminLayout title="Reports">
      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
          <div>
            <h2 className="text-2xl font-extrabold" style={{ color: '#1b1c1c', letterSpacing: '-0.01em' }}>Reports</h2>
            <p className="text-sm font-medium mt-0.5" style={{ color: '#3e4850' }}>Generate and export data for analysis or backup</p>
          </div>
          <div className="flex items-center gap-2 px-4 py-2 rounded-xl" style={{ backgroundColor: '#ffdf92', border: '2px solid #f4bf00' }}>
            <span className="material-symbols-outlined" style={{ fontSize: '16px', color: '#594400' }}>info</span>
            <span className="text-xs font-bold" style={{ color: '#594400' }}>Export endpoints — backend in development</span>
          </div>
        </div>

        {/* Report cards */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {REPORT_TYPES.map(report => (
            <div key={report.id} className="rounded-3xl" style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
              <div className="p-6">
                <div className="flex items-start gap-4 mb-4">
                  <div className="w-12 h-12 rounded-xl flex items-center justify-center shrink-0" style={{ backgroundColor: report.bg }}>
                    <span className="material-symbols-outlined" style={{ color: report.iconColor, fontSize: '22px', fontVariationSettings: "'FILL' 1" }}>{report.icon}</span>
                  </div>
                  <div>
                    <h3 className="font-extrabold text-base" style={{ color: '#1b1c1c' }}>{report.title}</h3>
                    <p className="text-sm font-medium mt-0.5" style={{ color: '#3e4850' }}>{report.desc}</p>
                  </div>
                </div>

                <div className="mb-4">
                  <p className="text-[10px] font-black uppercase tracking-widest mb-2" style={{ color: '#6e7881' }}>Included Fields</p>
                  <div className="flex flex-wrap gap-1.5">
                    {report.fields.map(f => (
                      <span key={f} className="px-2 py-0.5 rounded text-[11px] font-bold" style={{ backgroundColor: '#f5f3f3', color: '#3e4850' }}>{f}</span>
                    ))}
                  </div>
                </div>

                <div className="flex gap-3 pt-4" style={{ borderTop: '1px solid #efeded' }}>
                  {report.formats.map(fmt => (
                    <button
                      key={fmt}
                      onClick={() => handleGenerate(report.id, fmt)}
                      disabled={generating === report.id}
                      className="btn-tactile flex items-center gap-1.5 px-4 py-2.5 rounded-xl font-bold text-sm"
                      style={{
                        backgroundColor: generating === report.id ? '#efeded' : (fmt === 'CSV' ? '#006590' : '#ffffff'),
                        color: generating === report.id ? '#bdc8d2' : (fmt === 'CSV' ? '#ffffff' : '#006590'),
                        border: fmt === 'JSON' ? '2px solid #006590' : 'none',
                        borderBottom: generating === report.id ? '3px solid #bdc8d2' : '3px solid #004c6e',
                      }}
                    >
                      <span className="material-symbols-outlined text-base">
                        {generating === report.id ? 'hourglass_empty' : 'download'}
                      </span>
                      {generating === report.id ? 'Generating...' : `Export ${fmt}`}
                    </button>
                  ))}
                </div>
              </div>
            </div>
          ))}
        </div>

        {/* Scheduled reports placeholder */}
        <div className="rounded-3xl p-6" style={{ backgroundColor: '#f5f3f3', border: '2px solid #bdc8d2' }}>
          <div className="flex items-center gap-3 mb-3">
            <span className="material-symbols-outlined" style={{ color: '#006590', fontSize: '22px' }}>schedule</span>
            <h3 className="font-extrabold text-base" style={{ color: '#1b1c1c' }}>Scheduled Reports</h3>
            <span className="px-2.5 py-0.5 rounded-full text-xs font-bold" style={{ backgroundColor: '#ffdf92', color: '#594400' }}>Coming Soon</span>
          </div>
          <p className="text-sm font-medium" style={{ color: '#3e4850' }}>
            Automatically generate and email weekly or monthly reports to administrators. Configure report schedules and recipients after the backend export module is implemented.
          </p>
        </div>
      </div>
    </AdminLayout>
  );
}
