"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { ApiError, auth } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

export function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [submitting, setSubmitting] = useState(false);

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
    <main className="flex min-h-screen items-center justify-center bg-muted/40 p-4">
      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle>Sign in to Linguist</CardTitle>
          <CardDescription>Continue your English learning journey.</CardDescription>
        </CardHeader>
        <CardContent>
          {error && (
            <p role="alert" className="mb-4 rounded-xl border-2 border-destructive/30 bg-destructive/10 p-3 text-sm font-semibold text-destructive">
              {error}
            </p>
          )}
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
        </CardContent>
      </Card>
    </main>
  );
}
