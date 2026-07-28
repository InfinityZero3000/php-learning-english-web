'use client';

import Link from 'next/link';
import { useEffect, useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { auth, type User } from '@/lib/api';

interface TopBarProps {
  title?: string;
  user: User;
  menuOpen: boolean;
  onMenu: () => void;
}

export default function TopBar({ title, user, menuOpen, onMenu }: TopBarProps) {
  const router = useRouter();
  const [showMenu, setShowMenu] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const close = (event: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(event.target as Node)) setShowMenu(false);
    };
    document.addEventListener('mousedown', close);
    return () => document.removeEventListener('mousedown', close);
  }, []);

  function logout() {
    void auth.logout().finally(() => router.replace('/login'));
  }

  return (
    <header className="admin-topbar">
      <div className="flex min-w-0 items-center gap-3">
        <button
          type="button"
          onClick={onMenu}
          className={`admin-icon-button admin-menu-toggle lg:hidden ${menuOpen ? 'admin-menu-toggle-open' : ''}`}
          aria-label={menuOpen ? 'Đóng menu quản trị' : 'Mở menu quản trị'}
          aria-expanded={menuOpen}
        >
          <span className="material-symbols-outlined">{menuOpen ? 'close' : 'menu'}</span>
        </button>
        <div className="min-w-0">
          <p className="hidden text-[10px] font-black uppercase tracking-[0.18em] text-[#006590] sm:block">Control center</p>
          <h2 className="truncate font-display text-xl font-bold text-[#1b1c1c] sm:text-2xl">{title || 'Administration'}</h2>
        </div>
      </div>

      <div className="flex items-center gap-2 sm:gap-3">
        <span className="hidden rounded-full border-2 border-[#88ceff] bg-[#e8f4ff] px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-[#006590] sm:inline-flex">
          {user.role?.replace('_', ' ')}
        </span>
        <Link href="/notifications" className="admin-icon-button" aria-label="Thông báo">
          <span className="material-symbols-outlined">notifications</span>
        </Link>
        <div className="relative" ref={menuRef}>
          <button type="button" onClick={() => setShowMenu((value) => !value)} className="admin-avatar" aria-label="Mở menu tài khoản" aria-expanded={showMenu}>
            {(user.name || user.email).charAt(0).toUpperCase()}
          </button>
          {showMenu && (
            <div className="absolute right-0 top-13 z-50 min-w-56 overflow-hidden rounded-xl border-2 border-[#bdc8d2] bg-white shadow-[0_5px_0_rgba(0,101,144,.16)]">
              <div className="border-b-2 border-[#e3e8ec] px-4 py-3">
                <p className="truncate font-display text-sm font-bold">{user.name}</p>
                <p className="truncate text-xs font-semibold text-[#56636d]">{user.email}</p>
              </div>
              <button type="button" onClick={logout} className="flex w-full items-center gap-3 px-4 py-3 text-sm font-bold text-[#ba1a1a] transition hover:bg-[#fff0ee]">
                <span className="material-symbols-outlined text-lg">logout</span>
                Đăng xuất
              </button>
            </div>
          )}
        </div>
      </div>
    </header>
  );
}
