'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useLayoutEffect, useRef } from 'react';
import { navigationForRole } from '@/lib/admin-navigation.mjs';

const SCROLL_KEY = 'admin-sidebar-scroll-top';

export default function Sidebar({ role, open, onClose }: { role?: string | null; open: boolean; onClose: () => void }) {
  const pathname = usePathname();
  const navGroups = navigationForRole(role);
  const navigationRef = useRef<HTMLElement>(null);

  useLayoutEffect(() => {
    const navigation = navigationRef.current;
    if (navigation) navigation.scrollTop = Number(sessionStorage.getItem(SCROLL_KEY) ?? 0);
  }, [role]);

  return (
    <>
      {open && <button type="button" className="fixed inset-0 z-40 bg-black/30 lg:hidden" onClick={onClose} aria-label="Đóng menu" />}
      <aside className={`admin-sidebar ${open ? 'translate-x-0' : '-translate-x-full'} lg:translate-x-0`}>
        <div className="flex items-start justify-between px-6 pb-7 pt-6">
          <Link href="/dashboard" onClick={onClose}>
            <span className="font-display text-[30px] font-bold leading-none text-[#006590]">Linguist</span>
            <span className="mt-2 block text-[10px] font-black uppercase tracking-[0.19em] text-[#56636d]">Admin control center</span>
          </Link>
          <button type="button" onClick={onClose} className="admin-icon-button lg:hidden" aria-label="Đóng menu quản trị">
            <span className="material-symbols-outlined">close</span>
          </button>
        </div>

        <nav
          ref={navigationRef}
          aria-label="Admin navigation"
          className="custom-scrollbar min-h-0 flex-1 overflow-y-auto px-4 pb-4"
          onScroll={(event) => sessionStorage.setItem(SCROLL_KEY, String(event.currentTarget.scrollTop))}
        >
          {navGroups.map((group, index) => (
            <div key={group.label} className={index ? 'mt-5' : ''}>
              <p className="px-4 pb-2 text-[10px] font-black uppercase tracking-[0.18em] text-[#6e7881]">{group.label}</p>
              <div className="space-y-1.5">
                {group.items.map((item) => {
                  const active = pathname === item.href || pathname.startsWith(`${item.href}/`);
                  return (
                    <Link key={item.href} href={item.href} onClick={onClose} className={`admin-nav-item ${active ? 'admin-nav-item-active' : ''}`}>
                      <span className="material-symbols-outlined text-[22px]">{item.icon}</span>
                      <span>{item.label}</span>
                    </Link>
                  );
                })}
              </div>
            </div>
          ))}
        </nav>

        <div className="mx-4 mb-4 rounded-xl border-2 border-[#bdc8d2] bg-[#f5f3f3] px-4 py-3">
          <p className="text-[10px] font-black uppercase tracking-wider text-[#006590]">Protected access</p>
          <p className="mt-1 text-xs font-semibold text-[#56636d]">{role === 'super_admin' ? 'Super Admin' : 'Admin'} · Google whitelist</p>
        </div>
      </aside>
    </>
  );
}
