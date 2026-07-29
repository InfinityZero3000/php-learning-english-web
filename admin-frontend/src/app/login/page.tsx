'use client';

import { Suspense, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { auth } from '@/lib/api';

function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const isForbidden = searchParams.get('forbidden') === '1';

  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      await auth.login(username, password);
      router.push('/dashboard');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Login failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div
      className="p-8 rounded-3xl"
      style={{
        backgroundColor: '#ffffff',
        border: '2px solid #bdc8d2',
        borderBottom: '6px solid #bdc8d2',
      }}
    >
      <h2 className="text-2xl font-extrabold mb-6" style={{ color: '#1b1c1c' }}>
        Sign in
      </h2>

      {/* Thông báo Cảnh báo khi truy cập không đúng quyền Admin */}
      {isForbidden && (
        <div
          className="mb-4 p-3.5 rounded-xl text-sm font-bold flex items-center gap-2"
          style={{
            backgroundColor: '#fff3cd',
            color: '#664d03',
            border: '1px solid #ffecb5',
          }}
        >
          <span>⚠️</span>
          <span>Tài khoản của bạn không có quyền truy cập trang Quản trị (Admin).</span>
        </div>
      )}

      {/* Thông báo lỗi đăng nhập */}
      {error && (
        <div
          className="mb-4 p-3.5 rounded-xl text-sm font-bold"
          style={{
            backgroundColor: '#ffdad6',
            color: '#93000a',
            border: '1px solid #ba1a1a',
          }}
        >
          {error}
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-5">
        <div>
          <label
            className="block text-xs font-bold uppercase tracking-wider mb-2"
            style={{ color: '#3e4850' }}
          >
            Email
          </label>
          <input
            type="text"
            value={username}
            onChange={(e) => setUsername(e.target.value)}
            required
            className="w-full p-4 rounded-xl text-base font-medium outline-none transition-all"
            style={{
              border: '2px solid #bdc8d2',
              backgroundColor: '#fbf9f9',
              color: '#1b1c1c',
            }}
            onFocus={(e) => (e.target.style.borderColor = '#006590')}
            onBlur={(e) => (e.target.style.borderColor = '#bdc8d2')}
            placeholder="admin@example.com"
          />
        </div>
        <div>
          <label
            className="block text-xs font-bold uppercase tracking-wider mb-2"
            style={{ color: '#3e4850' }}
          >
            Password
          </label>
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            className="w-full p-4 rounded-xl text-base font-medium outline-none transition-all"
            style={{
              border: '2px solid #bdc8d2',
              backgroundColor: '#fbf9f9',
              color: '#1b1c1c',
            }}
            onFocus={(e) => (e.target.style.borderColor = '#006590')}
            onBlur={(e) => (e.target.style.borderColor = '#bdc8d2')}
            placeholder="••••••••"
          />
        </div>
        <button
          type="submit"
          disabled={loading}
          className="btn-tactile w-full py-4 rounded-2xl font-bold text-base uppercase tracking-wider transition-all"
          style={{
            backgroundColor: loading ? '#bdc8d2' : '#006590',
            color: '#ffffff',
            borderBottom: '4px solid #004c6e',
            cursor: loading ? 'not-allowed' : 'pointer',
          }}
        >
          {loading ? 'Signing in...' : 'Sign In'}
        </button>
      </form>

      <div
        className="mt-6 pt-6 text-center"
        style={{ borderTop: '2px solid #bdc8d2' }}
      >
        <p className="text-xs font-medium" style={{ color: '#3e4850' }}>
          Linguist Admin — powered by FSRS
        </p>
      </div>
    </div>
  );
}

export default function LoginPage() {
  return (
    <div
      className="min-h-screen flex items-center justify-center p-6"
      style={{ backgroundColor: '#fbf9f9' }}
    >
      <div className="w-full max-w-md">
        {/* Logo */}
        <div className="text-center mb-10">
          <h1
            className="text-5xl font-black"
            style={{ color: '#006590', letterSpacing: '-0.02em' }}
          >
            Linguist
          </h1>
          <p
            className="text-sm font-bold uppercase tracking-widest mt-2"
            style={{ color: '#3e4850' }}
          >
            Admin Dashboard
          </p>
        </div>

        {/* Form Đăng nhập bọc Suspense */}
        <Suspense fallback={<div className="text-center p-8 font-bold">Loading...</div>}>
          <LoginForm />
        </Suspense>
      </div>
    </div>
  );
}