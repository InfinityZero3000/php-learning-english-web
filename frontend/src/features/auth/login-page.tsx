"use client";
import { useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { AuthLayout } from "./auth-layout";
import { AuthLink, Field, Notice, messageFor } from "./form-support";
import { ApiError, auth } from "@/lib/api";
import { useAuth } from "./auth-context";
import { safeNext } from "./route-policy";
import { Button } from "@/components/ui/button";

const oauthMessages: Record<string, string> = {
  cancelled: "Sign-in was cancelled.", invalid_state: "Your sign-in session expired. Please try again.",
  email_missing: "The provider did not share an email address.", role_conflict: "This account cannot be used for learner sign-in.",
  provider_failed: "The provider could not complete sign-in.", session_failed: "We could not start your session. Please try again."
};

export function LoginPage() {
  const router = useRouter(), params = useSearchParams(), { refreshUser } = useAuth();
  const [email, setEmail] = useState(""), [password, setPassword] = useState(""), [visible, setVisible] = useState(false), [error, setError] = useState(""), [pending, setPending] = useState(false);
  const banner = params.get("verified") === "1" ? "Email verified. You can sign in now." : params.get("reset") === "1" ? "Password reset. Sign in with your new password." : "";
  const oauth = params.get("oauth_error");
  async function submit(e: React.FormEvent) { e.preventDefault(); setError(""); setPending(true); try { await auth.login(email, password); await refreshUser(); router.replace(safeNext(params.get("next"))); router.refresh(); } catch (cause) { setError(cause instanceof ApiError && cause.status === 403 ? "Please verify your email before signing in." : cause instanceof ApiError && cause.status === 401 ? "Email or password is incorrect." : messageFor(cause)); } finally { setPending(false); } }
  const next = encodeURIComponent(safeNext(params.get("next")));
  return <AuthLayout title="Welcome back" description="Pick up where you left off and keep your English growing.">
    <Notice>{banner}</Notice><Notice error>{oauth ? oauthMessages[oauth] ?? "Sign-in could not be completed." : error}</Notice>
    <form onSubmit={submit} className="space-y-4"><Field id="email" label="Email" type="email" placeholder="name@example.com" autoComplete="email" value={email} onChange={e => setEmail(e.target.value)} required /><div><Field id="password" label="Password" type={visible ? "text" : "password"} placeholder="Enter your password" autoComplete="current-password" value={password} onChange={e => setPassword(e.target.value)} required /><button type="button" onClick={() => setVisible(!visible)} className="mt-2 text-xs font-bold text-primary">{visible ? "Hide" : "Show"} password</button></div><div className="text-right"><AuthLink href="/forgot-password">Forgot password?</AuthLink></div><Button className="w-full" size="lg" disabled={pending}>{pending ? "Signing in..." : "Sign in"}</Button></form>
    <div className="my-6 flex items-center gap-3 text-xs font-bold uppercase text-muted-foreground"><span className="h-px flex-1 bg-border" />or<span className="h-px flex-1 bg-border" /></div>
    <div className="grid gap-3 sm:grid-cols-2"><a className="rounded-xl border-2 bg-white px-3 py-3 text-center font-bold hover:border-primary" href={`/api/v1/auth/oauth/google?next=${next}`}>Google</a><a className="rounded-xl border-2 bg-white px-3 py-3 text-center font-bold hover:border-primary" href={`/api/v1/auth/oauth/facebook?next=${next}`}>Facebook</a></div>
    <p className="mt-7 text-center text-sm">New to Linguist? <AuthLink href="/register">Create an account</AuthLink></p>
  </AuthLayout>;
}
