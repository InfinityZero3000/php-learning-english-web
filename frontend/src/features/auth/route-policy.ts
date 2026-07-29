const protectedPrefixes = ["/profile", "/progress", "/vocabulary", "/quiz", "/import"];
const authPaths = ["/login", "/register", "/verify-email", "/forgot-password", "/reset-password", "/auth/callback"];

export function isAuthPath(pathname: string) {
  return authPaths.some((path) => pathname === path || pathname.startsWith(`${path}/`));
}

export function isProtectedPath(pathname: string) {
  return protectedPrefixes.some((prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`));
}

export function pathWithSearch(pathname: string, search: string) {
  return search ? `${pathname}?${search}` : pathname;
}

export function loginHref(pathname: string, search = "") {
  return `/login?next=${encodeURIComponent(pathWithSearch(pathname, search))}`;
}

export function safeNext(raw: string | null | undefined, origin?: string) {
  if (!raw || !raw.startsWith("/") || raw.startsWith("//") || raw.includes("\\")) return "/";

  try {
    const base = origin ?? (typeof window === "undefined" ? "http://localhost" : window.location.origin);
    const target = new URL(raw, base);
    return target.origin === new URL(base).origin && !isAuthPath(target.pathname)
      ? `${target.pathname}${target.search}${target.hash}`
      : "/";
  } catch {
    return "/";
  }
}
