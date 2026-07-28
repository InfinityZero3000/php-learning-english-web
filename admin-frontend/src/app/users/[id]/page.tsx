'use client';

import { use, useEffect, useState } from 'react';
import Link from 'next/link';
import AdminLayout from '@/components/AdminLayout';
import { adminUsers, type AdminUser } from '@/lib/api';

export default function UserDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const id = Number(use(params).id);
  const [user, setUser] = useState<AdminUser>();
  const [message, setMessage] = useState('');

  useEffect(() => {
    adminUsers.get(id).then(setUser).catch((reason) => setMessage(reason instanceof Error ? reason.message : 'Could not load user.'));
  }, [id]);

  return <AdminLayout title="User detail">
    <div className="mx-auto max-w-3xl space-y-6"><Link href="/users" className="font-bold" style={{ color: '#006590' }}>← Back to users</Link>
      {message ? <p role="alert" className="rounded-xl p-4" style={{ background: '#ffdad6', color: '#93000a' }}>{message}</p> : !user ? <p className="rounded-3xl bg-white p-10 text-center font-bold">Loading user…</p> : <section className="rounded-3xl bg-white p-8" style={{ border: '2px solid #bdc8d2', borderBottomWidth: 4 }}><div className="flex h-20 w-20 items-center justify-center rounded-full text-2xl font-black" style={{ background: '#c8e6ff', color: '#004c6e' }}>{user.name.split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase()}</div><h2 className="mt-5 text-3xl font-black">{user.name}</h2><p className="mt-1" style={{ color: '#3e4850' }}>{user.email}</p><dl className="mt-7 grid gap-4 sm:grid-cols-2"><div className="rounded-2xl p-4" style={{ background: '#f5f3f3' }}><dt className="text-xs font-bold uppercase">Role</dt><dd className="mt-1 text-lg font-black">{user.role.replace('_', ' ')}</dd></div><div className="rounded-2xl p-4" style={{ background: '#f5f3f3' }}><dt className="text-xs font-bold uppercase">Email verified</dt><dd className="mt-1 text-lg font-black">{user.email_verified_at ? 'Yes' : 'No'}</dd></div></dl></section>}
    </div>
  </AdminLayout>;
}
