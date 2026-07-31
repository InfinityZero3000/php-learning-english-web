import { NextRequest, NextResponse } from "next/server";

/**
 * Same-origin redirect for the same reason as auth/google/route.ts: a
 * cross-domain hop here loses the OAuth "state" session cookie and Socialite
 * rejects the callback as invalid_state. Also routes through the SPA
 * /api/v1/auth/oauth/facebook endpoint (OAuthController), not the legacy
 * /auth/facebook web route.
 */
export function GET(request: NextRequest) {
  return NextResponse.redirect(new URL("/api/v1/auth/oauth/facebook?next=%2F", request.url));
}
