'use client';

import AdminLayout from '@/components/AdminLayout';

const ROLES = [
  {
    key: 'USER',
    label: 'User',
    icon: 'person',
    bg: '#c8e6ff',
    iconColor: '#004c6e',
    badgeBg: '#c8e6ff',
    badgeColor: '#004c6e',
    description: 'Standard learner account. Can access all learning features, manage personal vocabulary, and track their own progress.',
    permissions: [
      { label: 'View & study flashcards', granted: true },
      { label: 'Create personal decks', granted: true },
      { label: 'Take quizzes', granted: true },
      { label: 'View own progress', granted: true },
      { label: 'Manage profile & settings', granted: true },
      { label: 'Moderate content', granted: false },
      { label: 'View other users', granted: false },
      { label: 'Access admin panel', granted: false },
    ],
  },
  {
    key: 'MODERATOR',
    label: 'Moderator',
    icon: 'manage_accounts',
    bg: '#ffdf92',
    iconColor: '#594400',
    badgeBg: '#ffdf92',
    badgeColor: '#594400',
    description: 'Trusted user with content moderation capabilities. Can review and manage vocabulary content but cannot access admin settings.',
    permissions: [
      { label: 'View & study flashcards', granted: true },
      { label: 'Create personal decks', granted: true },
      { label: 'Take quizzes', granted: true },
      { label: 'View own progress', granted: true },
      { label: 'Manage profile & settings', granted: true },
      { label: 'Moderate content', granted: true },
      { label: 'View other users', granted: true },
      { label: 'Access admin panel', granted: false },
    ],
  },
  {
    key: 'ADMIN',
    label: 'Admin',
    icon: 'security',
    bg: '#d8f3dc',
    iconColor: '#1b5e20',
    badgeBg: '#d8f3dc',
    badgeColor: '#1b5e20',
    description: 'Full platform access. Can manage all users, content, system settings, and view platform analytics and audit logs.',
    permissions: [
      { label: 'View & study flashcards', granted: true },
      { label: 'Create personal decks', granted: true },
      { label: 'Take quizzes', granted: true },
      { label: 'View own progress', granted: true },
      { label: 'Manage profile & settings', granted: true },
      { label: 'Moderate content', granted: true },
      { label: 'View other users', granted: true },
      { label: 'Access admin panel', granted: true },
    ],
  },
];

const PERMISSION_MATRIX = [
  { feature: 'Study flashcards', user: true, moderator: true, admin: true },
  { feature: 'Personal decks', user: true, moderator: true, admin: true },
  { feature: 'Quiz participation', user: true, moderator: true, admin: true },
  { feature: 'Own progress stats', user: true, moderator: true, admin: true },
  { feature: 'Profile & reminders', user: true, moderator: true, admin: true },
  { feature: 'Content moderation', user: false, moderator: true, admin: true },
  { feature: 'User list view', user: false, moderator: true, admin: true },
  { feature: 'Lock / unlock users', user: false, moderator: false, admin: true },
  { feature: 'Reset passwords', user: false, moderator: false, admin: true },
  { feature: 'Assign roles', user: false, moderator: false, admin: true },
  { feature: 'Manage flashcards', user: false, moderator: true, admin: true },
  { feature: 'Manage decks & topics', user: false, moderator: true, admin: true },
  { feature: 'System settings', user: false, moderator: false, admin: true },
  { feature: 'View audit logs', user: false, moderator: false, admin: true },
  { feature: 'Analytics & reports', user: false, moderator: false, admin: true },
];

function Check({ granted }: { granted: boolean }) {
  return (
    <span
      className="material-symbols-outlined"
      style={{
        fontSize: '20px',
        fontVariationSettings: granted ? "'FILL' 1" : "'FILL' 0",
        color: granted ? '#1b5e20' : '#bdc8d2',
      }}
    >
      {granted ? 'check_circle' : 'cancel'}
    </span>
  );
}

export default function RolesPage() {
  return (
    <AdminLayout title="Roles">
      <div className="space-y-8">
        {/* Header */}
        <div>
          <h2 className="text-2xl font-extrabold" style={{ color: '#1b1c1c', letterSpacing: '-0.01em' }}>Roles & Permissions</h2>
          <p className="text-sm font-medium mt-0.5" style={{ color: '#3e4850' }}>
            System roles are fixed. Assign roles to users from the Users section.
          </p>
        </div>

        {/* Role cards */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {ROLES.map(role => (
            <div key={role.key} className="rounded-3xl" style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
              <div className="p-6">
                <div className="flex items-center gap-4 mb-5">
                  <div className="w-14 h-14 rounded-2xl flex items-center justify-center" style={{ backgroundColor: role.bg }}>
                    <span className="material-symbols-outlined" style={{ color: role.iconColor, fontSize: '26px', fontVariationSettings: "'FILL' 1" }}>{role.icon}</span>
                  </div>
                  <div>
                    <div className="flex items-center gap-2 mb-1">
                      <h3 className="text-xl font-extrabold" style={{ color: '#1b1c1c' }}>{role.label}</h3>
                      <span className="px-2.5 py-0.5 rounded-full text-xs font-bold" style={{ backgroundColor: role.badgeBg, color: role.badgeColor }}>{role.key}</span>
                    </div>
                  </div>
                </div>
                <p className="text-sm font-medium mb-5" style={{ color: '#3e4850' }}>{role.description}</p>
                <div className="space-y-2">
                  {role.permissions.map(perm => (
                    <div key={perm.label} className="flex items-center gap-2.5">
                      <Check granted={perm.granted} />
                      <span className="text-sm font-medium" style={{ color: perm.granted ? '#1b1c1c' : '#bdc8d2' }}>{perm.label}</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          ))}
        </div>

        {/* Permission Matrix */}
        <div className="rounded-3xl" style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2' }}>
          <div className="p-5 flex justify-between items-center rounded-t-[22px]" style={{ borderBottom: '2px solid #bdc8d2', backgroundColor: '#f5f3f3' }}>
            <h3 className="text-base font-extrabold" style={{ color: '#1b1c1c' }}>Permission Matrix</h3>
            <p className="text-xs font-medium" style={{ color: '#6e7881' }}>Full feature access breakdown by role</p>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-left">
              <thead>
                <tr style={{ backgroundColor: '#f5f3f3', borderBottom: '2px solid #efeded' }}>
                  <th className="px-5 py-4 text-xs font-bold uppercase tracking-widest" style={{ color: '#3e4850' }}>Feature</th>
                  {ROLES.map(r => (
                    <th key={r.key} className="px-5 py-4 text-center" style={{ minWidth: '110px' }}>
                      <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold" style={{ backgroundColor: r.badgeBg, color: r.badgeColor }}>
                        <span className="material-symbols-outlined" style={{ fontSize: '14px', fontVariationSettings: "'FILL' 1" }}>{r.icon}</span>
                        {r.label}
                      </span>
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {PERMISSION_MATRIX.map((row, i) => (
                  <tr key={row.feature} style={{ borderTop: '1px solid #efeded', backgroundColor: i % 2 === 0 ? '#ffffff' : '#fafafa' }}
                    onMouseEnter={e => (e.currentTarget.style.backgroundColor = '#f5f3f3')}
                    onMouseLeave={e => (e.currentTarget.style.backgroundColor = i % 2 === 0 ? '#ffffff' : '#fafafa')}>
                    <td className="px-5 py-3.5 text-sm font-bold" style={{ color: '#1b1c1c' }}>{row.feature}</td>
                    <td className="px-5 py-3.5 text-center"><Check granted={row.user} /></td>
                    <td className="px-5 py-3.5 text-center"><Check granted={row.moderator} /></td>
                    <td className="px-5 py-3.5 text-center"><Check granted={row.admin} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Info */}
        <div className="p-5 rounded-2xl flex items-start gap-4" style={{ backgroundColor: '#f5f3f3', border: '2px solid #bdc8d2' }}>
          <span className="material-symbols-outlined mt-0.5" style={{ color: '#6e7881', fontSize: '20px' }}>admin_panel_settings</span>
          <div>
            <p className="text-sm font-bold" style={{ color: '#1b1c1c' }}>Role assignment is done per-user</p>
            <p className="text-sm font-medium mt-0.5" style={{ color: '#3e4850' }}>
              Go to <strong>Users → [select user] → Role</strong> to change a user's role. Only ADMIN accounts can assign roles. Role changes take effect immediately.
            </p>
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}
