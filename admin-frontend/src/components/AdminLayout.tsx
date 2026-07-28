'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Sidebar from './Sidebar';
import TopBar from './TopBar';
import { ApiError, auth, type User } from '@/lib/api';

interface AdminLayoutProps {
  children: React.ReactNode;
  title?: string;
  requiredRole?: 'admin' | 'super_admin';
}

export default function AdminLayout({ children, title, requiredRole }: AdminLayoutProps) {
  const router = useRouter();
  const [state, setState] = useState<'loading' | 'ready' | 'error'>('loading');
  const [message, setMessage] = useState('');
  const [user, setUser] = useState<User>();

  useEffect(() => {
    let cancelled = false;
    auth.adminMe().then((user: User) => {
      if (cancelled) return;
      if (!['admin', 'super_admin'].includes(user.role ?? '')) {
        setMessage('This area is restricted to administrators.');
        void auth.logout().finally(() => router.replace('/login?forbidden=1'));
        return;
      }
      if (requiredRole && user.role !== requiredRole) {
        router.replace('/dashboard?forbidden=1');
        return;
      }
      setUser(user);
      setState('ready');
    }).catch((error) => {
      if (cancelled) return;
      if (error instanceof ApiError && error.status === 401) router.replace('/login?error=expired');
      else {
        setMessage('Could not reach the server. Please retry.');
        setState('error');
      }
    });
    return () => { cancelled = true; };
  }, [requiredRole, router]);

  if (state !== 'ready') {
    return (
      <main className="flex min-h-screen items-center justify-center p-8 text-center">
        <div>
          <p className="font-bold">{message || 'Checking administrator access...'}</p>
          {state === 'error' && <button className="mt-4 rounded-xl bg-[#006590] px-4 py-2 font-bold text-white" onClick={() => window.location.reload()}>Retry</button>}
        </div>
      </main>
    );
  }

  return (
    <div className="flex h-full min-h-screen" style={{ backgroundColor: '#fbf9f9' }}>
      <Sidebar role={user?.role} />
      <main className="flex-1 flex flex-col" style={{ marginLeft: '18rem' }}>
        <TopBar title={title} />
        <div className="flex-1 mt-20 p-8 max-w-7xl mx-auto w-full">
          {children}
        </div>
      </main>
    </div>
  );
}
