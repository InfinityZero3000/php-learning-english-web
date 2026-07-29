"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { ApiError, auth } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { AuthMessage, AuthShell } from "./auth-shell";

export function LoginPage() {
  const router = useRouter();
  const params = useSearchParams();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const verified = params.get("verified") === "1";
  const oauthError = params.get("error");
  const providerError = oauthError && ["oauth_cancelled", "oauth_email_missing", "oauth_failed"].includes(oauthError)
    ? "Đăng nhập với nhà cung cấp không thành công. Hãy thử lại."
    : "";
  const googleEnabled = process.env.NEXT_PUBLIC_GOOGLE_AUTH_ENABLED === "true";
  const facebookEnabled = process.env.NEXT_PUBLIC_FACEBOOK_AUTH_ENABLED === "true";

  async function submit(event: React.FormEvent) {
    event.preventDefault();
    setError("");
    setSubmitting(true);

    try {
      await auth.login(email, password);
      router.replace("/");
      router.refresh();
    } catch (cause) {
      if (cause instanceof ApiError) {
        setError(
          cause.status === 422
            ? Object.values(cause.errors ?? {}).flat()[0] ?? cause.message
            : cause.status === 403
              ? "Please verify your email before signing in."
              : cause.status === 401
                ? "Email or password is incorrect."
                : cause.message
        );
      } else {
        setError("Could not reach the server. Please try again.");
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <AuthShell title="Đăng nhập Linguist" description="Tiếp tục hành trình học tiếng Anh của bạn.">
          <AuthMessage error={error || providerError} success={verified ? "Email đã được xác minh. Bạn có thể đăng nhập." : ""} />
          <form onSubmit={submit} className="space-y-4">
            <div>
              <label htmlFor="email" className="mb-2 block text-sm font-bold">Email</label>
              <Input
                id="email"
                type="email"
                autoComplete="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                required
              />
            </div>
            <div>
              <label htmlFor="password" className="mb-2 block text-sm font-bold">Password</label>
              <Input
                id="password"
                type="password"
                autoComplete="current-password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                required
              />
            </div>
            <Button type="submit" className="w-full" disabled={submitting}>
              {submitting ? "Signing in..." : "Sign in"}
            </Button>
          </form>
          <div className="mt-4 flex justify-between text-sm"><Link href="/register" className="font-bold text-primary hover:underline">Tạo tài khoản</Link><Link href="/forgot-password" className="font-bold text-primary hover:underline">Quên mật khẩu?</Link></div>
          {googleEnabled || facebookEnabled ? <><div className="my-5 flex items-center gap-3 text-xs font-bold uppercase tracking-wider text-muted-foreground"><span className="h-px flex-1 bg-border" />hoặc<span className="h-px flex-1 bg-border" /></div><div className="space-y-3">{googleEnabled ? <Button asChildCompat="a" variant="outline" className="w-full"><a href="/auth/google">Tiếp tục với Google</a></Button> : null}{facebookEnabled ? <Button asChildCompat="a" variant="outline" className="w-full"><a href="/auth/facebook">Tiếp tục với Facebook</a></Button> : null}</div></> : null}
    </AuthShell>
  );
}
