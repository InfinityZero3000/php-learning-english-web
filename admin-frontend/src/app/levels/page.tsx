'use client';

import AdminLayout from '@/components/AdminLayout';

const LEVELS = [
  {
    key: 'BEGINNER',
    label: 'Beginner',
    range: 'A1 – A2',
    description: 'Foundational vocabulary for everyday situations. Simple words and basic phrases.',
    icon: 'child_care',
    bg: '#c8e6ff',
    iconColor: '#004c6e',
    badgeBg: '#c8e6ff',
    badgeColor: '#004c6e',
    borderColor: '#88ceff',
    wordExample: ['hello', 'water', 'family', 'number', 'color'],
  },
  {
    key: 'INTERMEDIATE',
    label: 'Intermediate',
    range: 'B1 – B2',
    description: 'Expanded vocabulary covering work, travel, and social interactions.',
    icon: 'school',
    bg: '#ffdf92',
    iconColor: '#594400',
    badgeBg: '#ffdf92',
    badgeColor: '#594400',
    borderColor: '#f4bf00',
    wordExample: ['negotiate', 'efficient', 'scenario', 'perspective', 'alternative'],
  },
  {
    key: 'ADVANCED',
    label: 'Advanced',
    range: 'C1 – C2',
    description: 'Sophisticated vocabulary for academic, professional, and nuanced communication.',
    icon: 'psychology',
    bg: '#ffdad6',
    iconColor: '#93000a',
    badgeBg: '#ffdad6',
    badgeColor: '#93000a',
    borderColor: '#ff8a80',
    wordExample: ['ephemeral', 'ubiquitous', 'juxtaposition', 'ambiguous', 'rhetoric'],
  },
  {
    key: 'EXPERT',
    label: 'Expert',
    range: 'C2+',
    description: 'Near-native mastery words: rare, literary, or domain-specific terminology.',
    icon: 'workspace_premium',
    bg: '#f4d9ff',
    iconColor: '#5d068e',
    badgeBg: '#f4d9ff',
    badgeColor: '#5d068e',
    borderColor: '#d087ff',
    wordExample: ['sesquipedalian', 'shibboleth', 'schadenfreude', 'synecdoche', 'apocryphal'],
  },
];

const FSRS_INTERVALS: Record<string, string> = {
  BEGINNER: '1 – 7 days',
  INTERMEDIATE: '3 – 21 days',
  ADVANCED: '7 – 60 days',
  EXPERT: '14 – 180 days',
};

export default function LevelsPage() {
  return (
    <AdminLayout title="Levels">
      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
          <div>
            <h2 className="text-2xl font-extrabold" style={{ color: '#1b1c1c', letterSpacing: '-0.01em' }}>Difficulty Levels</h2>
            <p className="text-sm font-medium mt-0.5" style={{ color: '#3e4850' }}>
              Four tiers drive FSRS scheduling — harder levels get longer initial intervals.
            </p>
          </div>
        </div>

        {/* Info banner */}
        <div className="flex items-start gap-4 p-5 rounded-2xl" style={{ backgroundColor: '#e8f4ff', border: '2px solid #88ceff' }}>
          <span className="material-symbols-outlined mt-0.5" style={{ color: '#006590', fontSize: '22px' }}>info</span>
          <div>
            <p className="text-sm font-bold" style={{ color: '#00405d' }}>Levels are system-defined and linked to FSRS scheduling</p>
            <p className="text-sm font-medium mt-0.5" style={{ color: '#004c6e' }}>
              Each flashcard is assigned a difficulty level on creation. The FSRS engine uses this to calibrate the initial stability and difficulty parameters.
            </p>
          </div>
        </div>

        {/* Level cards */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {LEVELS.map(level => (
            <div key={level.key} className="rounded-3xl" style={{ backgroundColor: '#ffffff', border: `2px solid ${level.borderColor}`, borderBottom: `5px solid ${level.borderColor}` }}>
              <div className="p-6">
                <div className="flex items-center gap-4 mb-5">
                  <div className="w-14 h-14 rounded-2xl flex items-center justify-center" style={{ backgroundColor: level.bg }}>
                    <span className="material-symbols-outlined" style={{ color: level.iconColor, fontSize: '26px', fontVariationSettings: "'FILL' 1" }}>{level.icon}</span>
                  </div>
                  <div>
                    <div className="flex items-center gap-2 mb-1">
                      <h3 className="text-xl font-extrabold" style={{ color: '#1b1c1c' }}>{level.label}</h3>
                      <span className="px-2.5 py-0.5 rounded-full text-xs font-bold" style={{ backgroundColor: level.badgeBg, color: level.badgeColor }}>{level.range}</span>
                    </div>
                    <p className="text-sm font-medium" style={{ color: '#3e4850' }}>{level.description}</p>
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-3 mb-5">
                  <div className="p-3.5 rounded-xl" style={{ backgroundColor: '#f5f3f3' }}>
                    <p className="text-[10px] font-black uppercase tracking-widest mb-1" style={{ color: '#6e7881' }}>FSRS Interval Range</p>
                    <p className="text-sm font-extrabold" style={{ color: '#006590' }}>{FSRS_INTERVALS[level.key]}</p>
                  </div>
                  <div className="p-3.5 rounded-xl" style={{ backgroundColor: '#f5f3f3' }}>
                    <p className="text-[10px] font-black uppercase tracking-widest mb-1" style={{ color: '#6e7881' }}>Difficulty Key</p>
                    <p className="text-sm font-mono font-extrabold" style={{ color: '#1b1c1c' }}>{level.key}</p>
                  </div>
                </div>

                <div>
                  <p className="text-[10px] font-black uppercase tracking-widest mb-2" style={{ color: '#6e7881' }}>Example Words</p>
                  <div className="flex flex-wrap gap-2">
                    {level.wordExample.map(w => (
                      <span key={w} className="px-3 py-1 rounded-full text-xs font-bold" style={{ backgroundColor: level.bg, color: level.iconColor }}>{w}</span>
                    ))}
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>

        {/* Mapping reference */}
        <div className="rounded-3xl" style={{ border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
          <div className="rounded-[22px] overflow-hidden" style={{ backgroundColor: '#ffffff' }}>
            <div className="p-5" style={{ borderBottom: '2px solid #bdc8d2', backgroundColor: '#f5f3f3' }}>
              <h3 className="text-base font-extrabold" style={{ color: '#1b1c1c' }}>FSRS Parameter Mapping</h3>
            </div>
            <div className="overflow-x-auto">
            <table className="w-full text-left">
              <thead>
                <tr style={{ borderBottom: '2px solid #efeded' }}>
                  {['Level', 'Initial Stability', 'Initial Difficulty', 'Retention Target', 'First Review'].map(h => (
                    <th key={h} className="px-5 py-3.5 text-xs font-bold uppercase tracking-widest" style={{ color: '#3e4850' }}>{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {[
                  { level: 'BEGINNER', stability: '0.4', difficulty: '4.0', retention: '90%', first: '1 day' },
                  { level: 'INTERMEDIATE', stability: '0.8', difficulty: '5.5', retention: '88%', first: '3 days' },
                  { level: 'ADVANCED', stability: '1.4', difficulty: '7.0', retention: '85%', first: '7 days' },
                  { level: 'EXPERT', stability: '2.0', difficulty: '8.5', retention: '80%', first: '14 days' },
                ].map((row, i) => {
                  const lvl = LEVELS[i];
                  return (
                    <tr key={row.level} style={{ borderTop: '1px solid #efeded' }} onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#f5f3f3')} onMouseLeave={e => (e.currentTarget.style.backgroundColor = '')}>
                      <td className="px-5 py-4">
                        <span className="px-3 py-1 rounded-full text-xs font-bold" style={{ backgroundColor: lvl.badgeBg, color: lvl.badgeColor }}>{row.level}</span>
                      </td>
                      <td className="px-5 py-4 font-bold text-sm" style={{ color: '#1b1c1c' }}>{row.stability}</td>
                      <td className="px-5 py-4 font-bold text-sm" style={{ color: '#1b1c1c' }}>{row.difficulty}</td>
                      <td className="px-5 py-4 font-bold text-sm" style={{ color: '#006590' }}>{row.retention}</td>
                      <td className="px-5 py-4 font-bold text-sm" style={{ color: '#843ab4' }}>{row.first}</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}
