export const adminNavigation = [
  {
    label: 'Overview',
    items: [
      { href: '/dashboard', icon: 'dashboard', label: 'Dashboard' },
      { href: '/analytics', icon: 'query_stats', label: 'Analytics' },
    ],
  },
  {
    label: 'Content',
    items: [
      { href: '/courses', icon: 'auto_stories', label: 'Courses' },
      { href: '/levels', icon: 'layers', label: 'Levels' },
      { href: '/topics', icon: 'topic', label: 'Topics' },
      { href: '/flashcards', icon: 'translate', label: 'Vocabulary' },
      { href: '/decks', icon: 'style', label: 'Decks' },
      { href: '/import', icon: 'cloud_download', label: 'Import Jobs' },
      { href: '/content', icon: 'dynamic_feed', label: 'Content Feed' },
    ],
  },
  {
    label: 'Learning',
    items: [
      { href: '/quizzes', icon: 'quiz', label: 'Quizzes' },
      { href: '/spaced-repetition', icon: 'event_repeat', label: 'Spaced Repetition' },
      { href: '/user-progress', icon: 'school', label: 'Learner Progress' },
      { href: '/reports', icon: 'bar_chart', label: 'Reports' },
    ],
  },
  {
    label: 'People',
    items: [{ href: '/users', icon: 'group', label: 'Users' }],
  },
  {
    label: 'Account',
    items: [
      { href: '/notifications', icon: 'notifications', label: 'Notifications' },
      { href: '/settings', icon: 'settings', label: 'Settings' },
    ],
  },
  {
    label: 'Super Admin',
    role: 'super_admin',
    items: [
      { href: '/super-admin', icon: 'shield', label: 'Super Dashboard' },
      { href: '/roles', icon: 'admin_panel_settings', label: 'Roles & Teacher Scope' },
      { href: '/operations', icon: 'monitor_heart', label: 'AI, Monitoring & Controls' },
      { href: '/audit-logs', icon: 'history', label: 'Audit Trail' },
    ],
  },
];

export const adminAliases = {
  '/progress': '/user-progress',
  '/quiz': '/quizzes',
  '/vocabulary': '/flashcards',
  '/vocabulary-sets': '/decks',
};

export function navigationForRole(role) {
  return adminNavigation.filter((group) => !group.role || group.role === role);
}
