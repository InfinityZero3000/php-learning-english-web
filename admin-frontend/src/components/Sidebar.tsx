'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';

const navGroups = [
  { label: null, items: [{ href: '/dashboard', icon: 'dashboard', label: 'Dashboard' }] },
  { label: 'Content', items: [{ href: '/courses', icon: 'auto_stories', label: 'Courses' }] },
  { label: 'Users', items: [
    { href: '/users', icon: 'group', label: 'Users' },
    { href: '/roles', icon: 'admin_panel_settings', label: 'Roles' },
  ] },
  { label: 'Platform', items: [{ href: '/operations', icon: 'monitor_heart', label: 'AI & Operations' }] },
];

export default function Sidebar({ role, open, onClose }: { role?: string | null; open: boolean; onClose: () => void }) {
  const pathname = usePathname();

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

        <nav className="custom-scrollbar flex-1 overflow-y-auto px-4 pb-4">
          {navGroups.map((group, index) => (
            <div key={group.label ?? 'home'} className={index ? 'mt-5' : ''}>
              {group.label && <p className="px-4 pb-2 text-[10px] font-black uppercase tracking-[0.18em] text-[#6e7881]">{group.label}</p>}
              <div className="space-y-1.5">
                {group.items.filter((item) => !['/operations', '/roles'].includes(item.href) || role === 'super_admin').map((item) => {
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

        <div className="m-4 rounded-xl border-2 border-[#bdc8d2] bg-[#f5f3f3] p-4">
          <p className="text-[10px] font-black uppercase tracking-wider text-[#006590]">Protected access</p>
          <p className="mt-1 text-xs font-semibold leading-relaxed text-[#56636d]">Google whitelist được kiểm tra trên mọi request.</p>
        </div>
      </aside>
    </>
  );
}
