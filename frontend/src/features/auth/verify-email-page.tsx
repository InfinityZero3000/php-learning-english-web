"use client";

import Link from "next/link";
import { useState } from "react";
import { auth } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { AuthMessage, AuthShell } from "./auth-shell";
import { messageFor } from "./form-support";

export function VerifyEmailPage() {
  const [error, setError] = useState(""); const [success, setSuccess] = useState(""); const [sending, setSending] = useState(false);
  async function resend() { setError(""); setSending(true); try { setSuccess((await auth.resendVerification()).message); } catch (cause) { setError(messageFor(cause, "Không thể gửi lại email.")); } finally { setSending(false); } }
  return <AuthShell title="Xác minh email" description="Mở email của bạn và nhấn liên kết xác minh để đăng nhập."><AuthMessage error={error} success={success} /><Button type="button" className="w-full" variant="outline" onClick={resend} disabled={sending}>{sending ? "Đang gửi..." : "Gửi lại email xác minh"}</Button><p className="mt-5 text-center text-sm"><Link href="/login" className="font-bold text-primary hover:underline">Quay lại đăng nhập</Link></p></AuthShell>;
}
