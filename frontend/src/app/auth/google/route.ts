import { NextRequest, NextResponse } from "next/server";

/**
 * Must stay same-origin: Next.js's own /api/:path* rewrite proxies this to
 * Laravel server-side. Redirecting the browser straight to LARAVEL_API_ORIGIN
 * (a different domain) sets the OAuth "state" session cookie there, but
 * Google's callback lands back on this frontend's domain — a different,
 * state-less session — so Socialite rejects it as invalid_state.
 */
export function GET(request: NextRequest) {
  return NextResponse.redirect(new URL("/api/v1/auth/oauth/google?next=%2F", request.url));
}
