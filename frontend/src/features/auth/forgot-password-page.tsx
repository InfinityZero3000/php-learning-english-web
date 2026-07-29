"use client";

import Link from "next/link";
import { useState } from "react";
import { auth } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { AuthMessage, AuthShell } from "./auth-shell";
import { messageFor } from "./form-support";

export function ForgotPasswordPage() {
  const [email, setEmail] = useState(""); const [error, setError] = useState(""); const [success, setSuccess] = useState(""); const [submitting, setSubmitting] = useState(false);
  async function submit(event: React.FormEvent) {
    event.preventDefault(); setError(""); setSubmitting(true);
    try { await auth.forgotPassword(email); setSuccess("Nếu tài khoản tồn tại, liên kết đặt lại đã được gửi."); } catch (cause) { setError(messageFor(cause, "Không thể gửi email đặt lại mật khẩu.")); } finally { setSubmitting(false); }
  }
  return <AuthShell title="Quên mật khẩu?" description="Chúng tôi sẽ gửi liên kết đặt lại nếu email này có tài khoản.">
    <AuthMessage error={error} success={success} />
    <form onSubmit={submit} className="space-y-4"><label className="block text-sm font-bold">Email<Input type="email" autoComplete="email" value={email} onChange={(event) => setEmail(event.target.value)} required /></label><Button type="submit" className="w-full" disabled={submitting}>{submitting ? "Đang gửi..." : "Gửi liên kết"}</Button></form>
    <p className="mt-5 text-center text-sm"><Link href="/login" className="font-bold text-primary hover:underline">Quay lại đăng nhập</Link></p>
  </AuthShell>;
}
