'use client';

import { useState, useEffect, useRef } from 'react';
import { useRouter } from 'next/navigation';
import { auth, type User } from '@/lib/api';

interface TopBarProps {
  title?: string;
}

export default function TopBar({ title }: TopBarProps) {
  const router = useRouter();
  const [search, setSearch] = useState('');
  const [isLoggedIn, setIsLoggedIn] = useState(false);
  const [userInitial, setUserInitial] = useState('A');
  const [showMenu, setShowMenu] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    auth.me().then((user: User) => {
      setIsLoggedIn(true);
      setUserInitial((user.name || user.email).charAt(0).toUpperCase() || 'A');
    }).catch(() => setIsLoggedIn(false));
  }, []);

  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(e.target as Node)) {
        setShowMenu(false);
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  const handleAvatarClick = () => {
    if (!isLoggedIn) {
      router.push('/login');
    } else {
      setShowMenu(prev => !prev);
    }
  };

  const handleLogout = () => {
    void auth.logout().finally(() => {
      setIsLoggedIn(false);
      setShowMenu(false);
      router.push('/login');
    });
  };

  return (
    <header
      className="fixed top-0 right-0 z-30 flex justify-between items-center px-10 h-20"
      style={{
        width: 'calc(100% - 18rem)',
        backgroundColor: 'rgba(251,249,249,0.92)',
        backdropFilter: 'blur(8px)',
        borderBottom: '2px solid #bdc8d2',
      }}
    >
      {/* Search */}
      <div className="flex items-center gap-6 flex-1">
        {title && (
          <h2 className="text-xl font-bold whitespace-nowrap" style={{ color: '#1b1c1c' }}>
            {title}
          </h2>
        )}
        <div className="relative w-full max-w-md">
          <span className="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-lg"
            style={{ color: '#3e4850' }}>search</span>
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search..."
            className="w-full pl-12 pr-4 py-2.5 rounded-xl text-sm font-medium outline-none transition-all"
            style={{
              border: '2px solid #bdc8d2',
              backgroundColor: '#fbf9f9',
              color: '#1b1c1c',
            }}
            onFocus={(e) => { (e.target as HTMLInputElement).style.borderColor = '#006590'; }}
            onBlur={(e) => { (e.target as HTMLInputElement).style.borderColor = '#bdc8d2'; }}
          />
        </div>
      </div>

      {/* Right actions */}
      <div className="flex items-center gap-2 ml-6">
        <button
          className="p-2.5 rounded-xl transition-colors"
          style={{ color: '#3e4850' }}
          onMouseEnter={(e) => (e.currentTarget.style.backgroundColor = '#e9e8e7')}
          onMouseLeave={(e) => (e.currentTarget.style.backgroundColor = '')}
          title="Streak"
        >
          <span className="material-symbols-outlined">local_fire_department</span>
        </button>
        <button
          className="p-2.5 rounded-xl transition-colors"
          style={{ color: '#3e4850' }}
          onMouseEnter={(e) => (e.currentTarget.style.backgroundColor = '#e9e8e7')}
          onMouseLeave={(e) => (e.currentTarget.style.backgroundColor = '')}
          title="Notifications"
        >
          <span className="material-symbols-outlined">notifications</span>
        </button>

        <div className="w-px h-8 mx-2" style={{ backgroundColor: '#bdc8d2' }} />

        <button
          className="btn-tactile px-4 py-2 rounded-xl font-bold text-sm uppercase tracking-wide"
          style={{
            backgroundColor: '#006590',
            color: '#ffffff',
            borderBottom: '4px solid #004c6e',
          }}
        >
          Quick Start AI
        </button>

        {/* Avatar with dropdown */}
        <div className="relative ml-2" ref={menuRef}>
          <button
            onClick={handleAvatarClick}
            title={isLoggedIn ? 'Account' : 'Sign in'}
            className="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-all"
            style={{
              backgroundColor: isLoggedIn ? '#006590' : '#c8e6ff',
              color: isLoggedIn ? '#ffffff' : '#001e2e',
              border: '2px solid #bdc8d2',
            }}
            onMouseEnter={(e) => (e.currentTarget.style.opacity = '0.85')}
            onMouseLeave={(e) => (e.currentTarget.style.opacity = '1')}
          >
            {userInitial}
          </button>

          {showMenu && isLoggedIn && (
            <div
              className="absolute right-0 top-12 rounded-2xl overflow-hidden shadow-xl min-w-[160px]"
              style={{ backgroundColor: '#ffffff', border: '2px solid #bdc8d2', borderBottom: '4px solid #bdc8d2', zIndex: 50 }}
            >
              <button
                onClick={handleLogout}
                className="w-full flex items-center gap-3 px-4 py-3 text-sm font-bold transition-colors"
                style={{ color: '#ba1a1a' }}
                onMouseEnter={(e) => (e.currentTarget.style.backgroundColor = '#ffdad6')}
                onMouseLeave={(e) => (e.currentTarget.style.backgroundColor = '')}
              >
                <span className="material-symbols-outlined text-base">logout</span>
                Sign out
              </button>
            </div>
          )}
        </div>
      </div>
    </header>
  );
}
