"use client";

import Link from "next/link";
import { useState } from "react";
import { auth, ApiError } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { AuthMessage, AuthShell } from "./auth-shell";

export function RegisterPage() {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [submitting, setSubmitting] = useState(false);

  async function submit(event: React.FormEvent) {
    event.preventDefault(); setError(""); setSuccess(""); setSubmitting(true);
    try {
      const result = await auth.register(name, email, password, confirmation);
      setSuccess(result.message); setPassword(""); setConfirmation("");
    } catch (cause) {
      setError(cause instanceof ApiError ? Object.values(cause.errors ?? {}).flat()[0] ?? cause.message : "Không thể tạo tài khoản.");
    } finally { setSubmitting(false); }
  }

  return <AuthShell title="Tạo tài khoản" description="Bắt đầu lộ trình học tiếng Anh của bạn.">
    <AuthMessage error={error} success={success} />
    <form onSubmit={submit} className="space-y-4">
      <label className="block text-sm font-bold">Tên<Input autoComplete="name" value={name} onChange={(event) => setName(event.target.value)} required /></label>
      <label className="block text-sm font-bold">Email<Input type="email" autoComplete="email" value={email} onChange={(event) => setEmail(event.target.value)} required /></label>
      <label className="block text-sm font-bold">Mật khẩu<Input type="password" autoComplete="new-password" minLength={8} value={password} onChange={(event) => setPassword(event.target.value)} required /></label>
      <label className="block text-sm font-bold">Xác nhận mật khẩu<Input type="password" autoComplete="new-password" minLength={8} value={confirmation} onChange={(event) => setConfirmation(event.target.value)} required /></label>
      <Button type="submit" className="w-full" disabled={submitting}>{submitting ? "Đang tạo..." : "Tạo tài khoản"}</Button>
    </form>
    <p className="mt-5 text-center text-sm text-muted-foreground">Đã có tài khoản? <Link href="/login" className="font-bold text-primary hover:underline">Đăng nhập</Link></p>
  </AuthShell>;
}
