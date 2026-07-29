"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useState } from "react";
import { auth, ApiError } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { AuthMessage, AuthShell } from "./auth-shell";

export function ResetPasswordPage() {
  const params = useSearchParams(); const token = params.get("token") ?? ""; const email = params.get("email") ?? "";
  const [password, setPassword] = useState(""); const [confirmation, setConfirmation] = useState(""); const [error, setError] = useState(""); const [success, setSuccess] = useState(""); const [submitting, setSubmitting] = useState(false);
  async function submit(event: React.FormEvent) {
    event.preventDefault(); setError(""); setSubmitting(true);
    try { setSuccess((await auth.resetPassword({ token, email, password, password_confirmation: confirmation })).message); setPassword(""); setConfirmation(""); } catch (cause) { setError(cause instanceof ApiError ? Object.values(cause.errors ?? {}).flat()[0] ?? cause.message : "Không thể đặt lại mật khẩu."); } finally { setSubmitting(false); }
  }
  if (!token || !email) return <AuthShell title="Liên kết không hợp lệ" description="Liên kết đặt lại mật khẩu thiếu thông tin cần thiết."><p role="alert" className="text-sm text-destructive">Hãy yêu cầu một liên kết đặt lại mới.</p><Link href="/forgot-password" className="mt-5 inline-block font-bold text-primary hover:underline">Quên mật khẩu</Link></AuthShell>;
  return <AuthShell title="Đặt lại mật khẩu" description={`Đặt mật khẩu mới cho ${email}.`}><AuthMessage error={error} success={success} /><form onSubmit={submit} className="space-y-4"><label className="block text-sm font-bold">Mật khẩu mới<Input type="password" autoComplete="new-password" minLength={8} value={password} onChange={(event) => setPassword(event.target.value)} required /></label><label className="block text-sm font-bold">Xác nhận mật khẩu<Input type="password" autoComplete="new-password" minLength={8} value={confirmation} onChange={(event) => setConfirmation(event.target.value)} required /></label><Button type="submit" className="w-full" disabled={submitting}>{submitting ? "Đang lưu..." : "Đặt lại mật khẩu"}</Button></form></AuthShell>;
}
