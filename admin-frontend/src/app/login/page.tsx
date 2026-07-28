'use client';

import { useRouter, useSearchParams } from 'next/navigation';
import { Suspense, useEffect, useState } from 'react';
import { auth } from '@/lib/api';

const errors: Record<string, string> = {
  denied: 'Tài khoản Google này không nằm trong whitelist quản trị.',
  configuration: 'Đăng nhập quản trị chưa được cấu hình. Vui lòng liên hệ người vận hành.',
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
      .catch(() => setHandoffError('Không thể hoàn tất phiên Google. Vui lòng đăng nhập lại.'));
  }, [handoff, router]);

  return (
    <main className="relative grid min-h-screen overflow-hidden bg-[#fbf9f9] lg:grid-cols-[1.05fr_.95fr]">
      <section className="relative hidden overflow-hidden bg-[#006590] p-12 text-white lg:flex lg:flex-col lg:justify-between">
        <div className="absolute -right-24 -top-24 h-96 w-96 rounded-full border-[70px] border-white/10" />
        <div className="absolute -bottom-28 -left-16 h-80 w-80 rounded-full bg-[#1cb0f6]/45" />
        <div className="relative z-10">
          <p className="font-display text-4xl font-bold">Linguist</p>
          <p className="mt-2 text-xs font-black uppercase tracking-[0.22em] text-sky-100">Admin control center</p>
        </div>
        <div className="relative z-10 max-w-xl">
          <span className="material-symbols-outlined text-[76px] text-[#ffdf92]">admin_panel_settings</span>
          <h1 className="mt-5 font-display text-5xl font-bold leading-[1.08]">Vận hành việc học,<br />không làm gián đoạn người học.</h1>
          <p className="mt-6 max-w-lg text-lg font-semibold leading-relaxed text-sky-100">Quản lý khóa học, người dùng và hệ thống AI từ một không gian bảo mật riêng.</p>
        </div>
        <p className="relative z-10 text-sm font-bold text-sky-100">Google-only access · Environment whitelist</p>
      </section>

      <section className="flex items-center justify-center p-5 sm:p-10">
        <div className="w-full max-w-md">
          <div className="mb-8 lg:hidden">
            <p className="font-display text-4xl font-bold text-[#006590]">Linguist</p>
            <p className="mt-2 text-[10px] font-black uppercase tracking-[0.2em] text-[#56636d]">Admin control center</p>
          </div>

          <div className="learning-card p-6 sm:p-8">
            <div className="grid h-12 w-12 place-items-center rounded-xl bg-[#e8f4ff] text-[#006590]">
              <span className="material-symbols-outlined text-3xl">shield_lock</span>
            </div>
            <p className="mt-6 text-xs font-black uppercase tracking-[0.18em] text-[#006590]">Protected access</p>
            <h2 className="mt-2 font-display text-3xl font-bold text-[#1b1c1c]">Đăng nhập quản trị</h2>
            <p className="mt-3 text-sm font-semibold leading-relaxed text-[#56636d]">Dùng tài khoản Google nằm trong whitelist admin hoặc super admin. Không hỗ trợ đăng nhập bằng mật khẩu.</p>

            {(message || handoffError) && (
              <div role="alert" className="mt-5 rounded-xl border-2 border-[#ffb4ab] bg-[#fff0ee] p-4 text-sm font-bold text-[#93000a]">{message || handoffError}</div>
            )}

            <a href="/api/v1/auth/oauth/google/admin" aria-disabled={Boolean(handoff)} className="btn-tactile mt-7 flex w-full items-center justify-center gap-3 rounded-xl border-2 border-[#bdc8d2] bg-white px-5 py-4 font-display font-bold text-[#1b1c1c] shadow-[0_4px_0_#9ca7b0] transition hover:border-[#006590] disabled:opacity-60">
              <span className="grid h-8 w-8 place-items-center rounded-full bg-[#4285f4] text-sm font-black text-white">G</span>
              {handoff ? 'Đang hoàn tất đăng nhập…' : 'Tiếp tục với Google'}
            </a>

            <div className="mt-7 flex items-start gap-3 border-t-2 border-[#e3e8ec] pt-5 text-xs font-semibold leading-relaxed text-[#56636d]">
              <span className="material-symbols-outlined text-lg text-[#006590]">verified_user</span>
              Quyền và whitelist được kiểm tra lại trên mỗi API request.
            </div>
          </div>
        </div>
      </section>
    </main>
  );
}

export default function LoginPage() {
  return <Suspense><LoginCard /></Suspense>;
}
