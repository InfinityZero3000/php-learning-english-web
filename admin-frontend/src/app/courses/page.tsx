'use client';

import AdminLayout from '@/components/AdminLayout';

const COURSE_STUBS = [
  { id: 1, title: 'English Foundations', level: 'BEGINNER', units: 8, lessons: 32, status: 'PUBLISHED', learners: 0 },
  { id: 2, title: 'Business English', level: 'INTERMEDIATE', units: 6, lessons: 24, status: 'DRAFT', learners: 0 },
  { id: 3, title: 'Academic Vocabulary', level: 'ADVANCED', units: 5, lessons: 20, status: 'DRAFT', learners: 0 },
  { id: 4, title: 'Travel & Culture', level: 'BEGINNER', units: 4, lessons: 16, status: 'DRAFT', learners: 0 },
];

const levelStyle: Record<string, { bg: string; color: string }> = {
  BEGINNER: { bg: '#c8e6ff', color: '#004c6e' },
  INTERMEDIATE: { bg: '#ffdf92', color: '#594400' },
  ADVANCED: { bg: '#ffdad6', color: '#93000a' },
  EXPERT: { bg: '#f4d9ff', color: '#5d068e' },
};

const statusStyle: Record<string, { bg: string; color: string }> = {
  PUBLISHED: { bg: '#d8f3dc', color: '#1b5e20' },
  DRAFT: { bg: '#efeded', color: '#3e4850' },
  ARCHIVED: { bg: '#ffdad6', color: '#93000a' },
};

export default function CoursesPage() {
  return (
    <AdminLayout title="Courses">
      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
          <div>
            <h2 className="text-2xl font-extrabold" style={{ color: '#1b1c1c', letterSpacing: '-0.01em' }}>Courses</h2>
            <p className="text-sm font-medium mt-0.5" style={{ color: '#3e4850' }}>
              Structured learning paths with units, lessons, and FSRS-scheduled reviews.
            </p>
          </div>
          <button className="btn-tactile flex items-center gap-2 px-6 py-3 rounded-xl font-bold text-sm uppercase tracking-wide" style={{ backgroundColor: '#006590', color: '#ffffff', borderBottom: '4px solid #004c6e' }} onClick={() => alert('Course builder coming soon')}>
            <span className="material-symbols-outlined text-base">add</span>
            New Course
          </button>
        </div>

        {/* Roadmap banner */}
        <div className="rounded-3xl p-8 flex flex-col md:flex-row items-center gap-8" style={{ backgroundColor: '#e8f4ff', border: '2px solid #88ceff' }}>
          <div className="flex-1">
            <div className="flex items-center gap-2 mb-3">
              <span className="px-3 py-1 rounded-full text-xs font-black uppercase tracking-wide" style={{ backgroundColor: '#ffdf92', color: '#594400' }}>Coming Soon</span>
            </div>
            <h3 className="text-xl font-extrabold mb-2" style={{ color: '#00405d' }}>Course Builder</h3>
            <p className="text-sm font-medium mb-4" style={{ color: '#004c6e' }}>
              Design structured curriculum with sequential units and lessons. Each lesson maps to a deck of flashcards and is automatically scheduled with FSRS after completion.
            </p>
            <ul className="space-y-1.5">
              {['Sequential unit & lesson structure', 'Deck attachment per lesson', 'Automatic FSRS review scheduling', 'Learner enrollment & progress tracking', 'Prerequisite gating between lessons'].map(f => (
                <li key={f} className="flex items-center gap-2 text-sm font-medium" style={{ color: '#004c6e' }}>
                  <span className="material-symbols-outlined" style={{ fontSize: '16px', color: '#1cb0f6' }}>check_circle</span>
                  {f}
                </li>
              ))}
            </ul>
          </div>
          <div className="w-40 h-40 rounded-full flex items-center justify-center shrink-0" style={{ backgroundColor: '#006590', border: '6px solid #004c6e' }}>
            <span className="material-symbols-outlined" style={{ color: '#ffffff', fontSize: '64px', fontVariationSettings: "'FILL' 1" }}>auto_stories</span>
          </div>
        </div>

        {/* Course list placeholder */}
        <div className="rounded-3xl" style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
          <div className="p-5 flex justify-between items-center rounded-t-[22px]" style={{ borderBottom: '2px solid #bdc8d2', backgroundColor: '#f5f3f3' }}>
            <h3 className="text-base font-extrabold" style={{ color: '#1b1c1c' }}>Course Library</h3>
            <span className="text-xs font-bold" style={{ color: '#6e7881' }}>{COURSE_STUBS.length} courses</span>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-left">
              <thead>
                <tr style={{ borderBottom: '2px solid #efeded' }}>
                  {['Course', 'Level', 'Units', 'Lessons', 'Learners', 'Status', 'Actions'].map(h => (
                    <th key={h} className="px-5 py-4 text-xs font-bold uppercase tracking-widest" style={{ color: '#3e4850' }}>{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {COURSE_STUBS.map(course => {
                  const ls = levelStyle[course.level] ?? { bg: '#efeded', color: '#3e4850' };
                  const ss = statusStyle[course.status] ?? { bg: '#efeded', color: '#3e4850' };
                  return (
                    <tr key={course.id} style={{ borderTop: '1px solid #efeded' }} onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#f5f3f3')} onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
                      <td className="px-5 py-4">
                        <div className="flex items-center gap-3">
                          <div className="w-10 h-10 rounded-xl flex items-center justify-center" style={{ backgroundColor: ls.bg }}>
                            <span className="material-symbols-outlined" style={{ color: ls.color, fontSize: '18px', fontVariationSettings: "'FILL' 1" }}>auto_stories</span>
                          </div>
                          <span className="font-bold text-sm" style={{ color: '#1b1c1c' }}>{course.title}</span>
                        </div>
                      </td>
                      <td className="px-5 py-4">
                        <span className="px-3 py-1 rounded-full text-xs font-bold" style={{ backgroundColor: ls.bg, color: ls.color }}>{course.level}</span>
                      </td>
                      <td className="px-5 py-4 font-bold text-sm" style={{ color: '#1b1c1c' }}>{course.units}</td>
                      <td className="px-5 py-4 font-bold text-sm" style={{ color: '#1b1c1c' }}>{course.lessons}</td>
                      <td className="px-5 py-4 font-bold text-sm" style={{ color: '#006590' }}>{course.learners}</td>
                      <td className="px-5 py-4">
                        <span className="px-3 py-1 rounded-full text-xs font-bold" style={{ backgroundColor: ss.bg, color: ss.color }}>{course.status}</span>
                      </td>
                      <td className="px-5 py-4">
                        <button className="p-1.5 rounded-lg" style={{ color: '#006590' }} onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#e8f4ff')} onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
                          <span className="material-symbols-outlined text-base">edit</span>
                        </button>
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
