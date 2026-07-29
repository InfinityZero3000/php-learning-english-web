"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { auth } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { AuthMessage, AuthShell } from "./auth-shell";
import { messageFor } from "./form-support";

export function RegisterPage() {
  const router = useRouter();
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [submitting, setSubmitting] = useState(false);

  async function submit(event: React.FormEvent) {
    event.preventDefault(); setError(""); setSuccess(""); setSubmitting(true);
    if (password !== confirmation) { setError("Mật khẩu xác nhận không khớp."); setSubmitting(false); return; }
    try {
      await auth.register(name, email, password, confirmation);
      router.push(`/verify-email?email=${encodeURIComponent(email)}`);
    } catch (cause) {
      setError(messageFor(cause, "Không thể tạo tài khoản."));
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
