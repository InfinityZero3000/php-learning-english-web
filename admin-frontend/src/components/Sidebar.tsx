'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';

const navGroups = [
  {
    label: null,
    items: [
      { href: '/dashboard', icon: 'dashboard', label: 'Dashboard' },
    ],
  },
  {
    label: 'Content',
    items: [
      { href: '/flashcards', icon: 'style', label: 'Flashcards' },
      { href: '/decks', icon: 'collections_bookmark', label: 'Decks' },
      { href: '/topics', icon: 'topic', label: 'Topics' },
      { href: '/levels', icon: 'signal_cellular_alt', label: 'Levels' },
      { href: '/courses', icon: 'auto_stories', label: 'Courses' },
    ],
  },
  {
    label: 'Users',
    items: [
      { href: '/users', icon: 'group', label: 'Users' },
      { href: '/roles', icon: 'admin_panel_settings', label: 'Roles' },
      { href: '/user-progress', icon: 'bar_chart', label: 'User Progress' },
      { href: '/spaced-repetition', icon: 'repeat', label: 'Spaced Repetition' },
    ],
  },
  {
    label: 'Platform',
    items: [
      { href: '/notifications', icon: 'notifications', label: 'Notifications' },
      { href: '/reports', icon: 'assessment', label: 'Reports' },
      { href: '/analytics', icon: 'analytics', label: 'Analytics' },
      { href: '/audit-logs', icon: 'manage_history', label: 'Audit Logs' },
    ],
  },
];

const bottomItems = [{ href: '/settings', icon: 'settings', label: 'Settings' }];

export default function Sidebar() {
  const pathname = usePathname();
  const isActive = (href: string) => pathname === href || pathname.startsWith(href + '/');

  return (
    <aside
      className="fixed left-0 top-0 h-full z-40 flex flex-col w-72"
      style={{ backgroundColor: '#f5f3f3', borderRight: '2px solid #bdc8d2' }}
    >
      {/* Logo */}
      <div className="px-8 py-6 shrink-0">
        <h1 className="text-3xl font-black" style={{ color: '#006590', letterSpacing: '-0.02em' }}>
          Linguist
        </h1>
        <p className="text-[10px] font-black uppercase tracking-[0.2em] mt-1" style={{ color: '#3e4850', opacity: 0.6 }}>
          Admin Dashboard
        </p>
      </div>

      {/* Nav */}
      <nav className="flex-1 px-4 overflow-y-auto pb-2" style={{ scrollbarWidth: 'thin' }}>
        {navGroups.map((group, gi) => (
          <div key={gi} className={gi > 0 ? 'mt-3' : ''}>
            {group.label && (
              <p
                className="px-5 pt-1 pb-1 text-[10px] font-black uppercase tracking-[0.2em]"
                style={{ color: '#6e7881' }}
              >
                {group.label}
              </p>
            )}
            <div className="space-y-0.5">
              {group.items.map((item) => {
                const active = isActive(item.href);
                return (
                  <Link
                    key={item.href}
                    href={item.href}
                    className="flex items-center gap-3 px-4 py-2.5 mx-1 rounded-xl transition-all"
                    style={
                      active
                        ? { backgroundColor: '#006590', color: '#ffffff', borderBottom: '3px solid #004c6e' }
                        : { color: '#3e4850' }
                    }
                    onMouseEnter={(e) => {
                      if (!active) {
                        (e.currentTarget as HTMLElement).style.backgroundColor = '#e9e8e7';
                        (e.currentTarget as HTMLElement).style.color = '#1b1c1c';
                      }
                    }}
                    onMouseLeave={(e) => {
                      if (!active) {
                        (e.currentTarget as HTMLElement).style.backgroundColor = '';
                        (e.currentTarget as HTMLElement).style.color = '#3e4850';
                      }
                    }}
                  >
                    <span className="material-symbols-outlined" style={{ fontSize: '20px' }}>
                      {item.icon}
                    </span>
                    <span className="text-sm font-bold">{item.label}</span>
                  </Link>
                );
              })}
            </div>
          </div>
        ))}
      </nav>

      {/* Bottom */}
      <div className="px-4 pb-5 pt-3 shrink-0" style={{ borderTop: '2px solid #bdc8d2' }}>
        {bottomItems.map((item) => {
          const active = isActive(item.href);
          return (
            <Link
              key={item.href}
              href={item.href}
              className="flex items-center gap-3 px-4 py-2.5 mx-1 rounded-xl transition-all"
              style={active ? { backgroundColor: '#006590', color: '#ffffff' } : { color: '#3e4850' }}
              onMouseEnter={(e) => {
                if (!active) (e.currentTarget as HTMLElement).style.backgroundColor = '#e9e8e7';
              }}
              onMouseLeave={(e) => {
                if (!active) (e.currentTarget as HTMLElement).style.backgroundColor = '';
              }}
            >
              <span className="material-symbols-outlined" style={{ fontSize: '20px' }}>
                {item.icon}
              </span>
              <span className="text-sm font-bold">{item.label}</span>
            </Link>
          );
        })}
      </div>
    </aside>
  );
}
