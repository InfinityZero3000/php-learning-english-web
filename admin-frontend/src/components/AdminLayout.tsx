'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Sidebar from './Sidebar';
import TopBar from './TopBar';
import { ApiError, auth, type User } from '@/lib/api';

interface AdminLayoutProps {
  children: React.ReactNode;
  title?: string;
}

export default function AdminLayout({ children, title }: AdminLayoutProps) {
  const router = useRouter();
  const [state, setState] = useState<'loading' | 'ready' | 'error'>('loading');
  const [message, setMessage] = useState('');

  useEffect(() => {
    let cancelled = false;
    auth.me().then((user: User) => {
      if (cancelled) return;
      if (user.role !== 'admin') {
        setMessage('This area is restricted to administrators.');
        void auth.logout().finally(() => router.replace('/login?forbidden=1'));
        return;
      }
      setState('ready');
    }).catch((error) => {
      if (cancelled) return;
      if (error instanceof ApiError && error.status === 401) router.replace('/login');
      else {
        setMessage('Could not reach the server. Please retry.');
        setState('error');
      }
    });
    return () => { cancelled = true; };
  }, [router]);

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
      <Sidebar />
      <main className="flex-1 flex flex-col" style={{ marginLeft: '18rem' }}>
        <TopBar title={title} />
        <div className="flex-1 mt-20 p-8 max-w-7xl mx-auto w-full">
          {children}
        </div>
      </main>
    </div>
  );
}
