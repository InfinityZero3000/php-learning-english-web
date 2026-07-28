'use client';

import { useRouter, useSearchParams } from 'next/navigation';
import { Suspense, useEffect, useState } from 'react';
import { auth } from '@/lib/api';

const errors: Record<string, string> = {
  denied: 'Tài khoản Google này không được phép truy cập trang quản trị.',
  configuration: 'Đăng nhập quản trị chưa được cấu hình. Vui lòng liên hệ người vận hành.',
  expired: 'Phiên quản trị đã hết hạn hoặc quyền truy cập đã được thu hồi.',
};

function LoginCard() {
  const params = useSearchParams();
  const router = useRouter();
  const [handoffError, setHandoffError] = useState('');
  const handoff = params.get('handoff');
  const message = errors[params.get('error') ?? ''] || (params.has('forbidden') ? errors.denied : '');

  useEffect(() => {
    if (!handoff) return;
    window.history.replaceState({}, '', '/login');
    auth.completeGoogleAdmin(handoff)
      .then(({ return: target }) => router.replace(target))
      .catch(() => setHandoffError('Không thể hoàn tất phiên Google. Vui lòng thử lại.'));
  }, [handoff, router]);

  return (
    <div className="min-h-screen flex items-center justify-center p-6" style={{ backgroundColor: '#fbf9f9' }}>
      <div className="w-full max-w-md">
        <div className="text-center mb-10">
          <h1 className="text-5xl font-black" style={{ color: '#006590', letterSpacing: '-0.02em' }}>Linguist</h1>
          <p className="text-sm font-bold uppercase tracking-widest mt-2" style={{ color: '#3e4850' }}>Admin Dashboard</p>
        </div>
        <div className="p-8 rounded-3xl" style={{ backgroundColor: '#fff', border: '2px solid #bdc8d2', borderBottom: '6px solid #bdc8d2' }}>
          <p className="text-xs font-black uppercase tracking-[0.2em]" style={{ color: '#006590' }}>Protected access</p>
          <h2 className="text-2xl font-extrabold mt-2">Đăng nhập bằng Google</h2>
          <p className="mt-3 text-sm font-medium" style={{ color: '#3e4850' }}>
            Chỉ email nằm trong whitelist quản trị mới có thể tiếp tục. Trang này không hỗ trợ mật khẩu.
          </p>
          {(message || handoffError) && <div role="alert" className="mt-5 p-3 rounded-xl text-sm font-semibold" style={{ backgroundColor: '#ffdad6', color: '#93000a', border: '1px solid #ba1a1a' }}>{message || handoffError}</div>}
          <a href="/api/v1/auth/oauth/google/admin" className="btn-tactile mt-7 flex w-full items-center justify-center gap-3 rounded-2xl py-4 font-bold" style={{ backgroundColor: '#fff', color: '#1b1c1c', border: '2px solid #bdc8d2', borderBottom: '4px solid #9ca7b0' }}>
            <span className="grid h-7 w-7 place-items-center rounded-full bg-[#4285f4] text-sm font-black text-white">G</span>
            {handoff ? 'Completing Google sign-in…' : 'Continue with Google'}
          </a>
          <p className="mt-6 border-t-2 pt-5 text-center text-xs" style={{ borderColor: '#bdc8d2', color: '#3e4850' }}>
            Quyền được kiểm tra lại ở mỗi request.
          </p>
        </div>
      </div>
    </div>
  );
}

export default function LoginPage() {
  return <Suspense><LoginCard /></Suspense>;
}
