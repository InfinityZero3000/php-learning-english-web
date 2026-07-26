"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import {
  IconDoorEnter,
  IconLogout2,
  IconUserFilled,
  IconMenu2
} from "@tabler/icons-react";
import { useEffect, useMemo, useState } from "react";
import { AppDiamondIcon, AppFlameIcon, navigationIcons } from "@/components/icons/app-icons";
import { NotificationWidget } from "@/components/layout/notifications";
import { Button } from "@/components/ui/button";
import { CatLoader } from "@/components/ui/cat-loader";
import { AuthProvider, useAuth } from "@/features/auth/auth-context";
import { isAuthPath, isProtectedPath, loginHref } from "@/features/auth/route-policy";
import { cn } from "@/lib/utils";
import type { AppUser } from "@/types/api";

const nav = [
  { href: "/", label: "Home", icon: navigationIcons.home },
  { href: "/vocabulary", label: "Words", icon: navigationIcons.words },
  { href: "/flashcards", label: "Flashcards", icon: navigationIcons.flashcards },
  { href: "/quiz", label: "Quiz", icon: navigationIcons.quiz },
  { href: "/progress", label: "Progress", icon: navigationIcons.progress },
  { href: "/import", label: "Import", icon: navigationIcons.import }
];

const titles: Record<string, string> = {
  "/": "Home",
  "/vocabulary": "Words",
  "/flashcards": "Flashcards",
  "/quiz": "Quiz",
  "/progress": "Progress",
  "/import": "Import",
  "/profile": "Profile"
};

export function AppShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  return <AuthProvider checkSession={isProtectedPath(pathname)}><AppShellContent>{children}</AppShellContent></AuthProvider>;
}

function AppShellContent({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const router = useRouter();
  const { status, user, refreshUser, logout: clearSession } = useAuth();
  const [streak, setStreak] = useState(0);
  const [xp] = useState(0);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

  const pageTitle = useMemo(() => titles[pathname] || "FSRSpring", [pathname]);

  const protectedRoute = isProtectedPath(pathname);

  useEffect(() => {
    if (status === "guest" && protectedRoute) {
      router.replace(loginHref(pathname, window.location.search.slice(1)));
    }
  }, [pathname, protectedRoute, router, status]);

  async function logout() {
    await clearSession();
    router.replace("/");
    router.refresh();
  }

  if (isAuthPath(pathname)) return <div className="app-shell">{children}</div>;
  if (protectedRoute && (status === "checking" || status === "guest")) {
    return <AppShellLoading label="Checking session..." />;
  }
  if (protectedRoute && status === "unavailable") {
    return (
      <main className="flex min-h-screen items-center justify-center p-6 text-center">
        <div>
          <p className="font-bold">Could not reach the server.</p>
          <Button className="mt-4" onClick={() => void refreshUser()}>Retry</Button>
        </div>
      </main>
    );
  }

  return (
    <div className="app-shell min-h-screen text-foreground">
      <aside className="fixed left-0 top-0 z-40 hidden h-full w-64 flex-col border-r-2 border-border bg-card p-6 lg:flex">
        <Link href="/" className="mb-10 flex items-center">
          <span className="font-display text-[32px] font-bold leading-tight tracking-normal text-primary">Linguist</span>
        </Link>
        <nav className="flex flex-1 flex-col gap-2">
          {nav.map((item) => {
            const active = item.href === "/" ? pathname === "/" : pathname.startsWith(item.href);
            const Icon = item.icon;
            return (
              <Link
                key={item.href}
                href={item.href}
                className={cn(
                  "flex items-center gap-4 rounded-xl px-4 py-3 font-display text-[15px] font-bold uppercase tracking-[0.05em] transition",
                  active
                    ? "border-2 border-primary bg-accent text-primary"
                    : "text-[#404a52] hover:bg-muted hover:text-primary"
                )}
              >
                <Icon className="h-6 w-6 shrink-0" stroke={2} fill="none" />
                {item.label}
              </Link>
            );
          })}
        </nav>
        <div className="border-t-2 border-border pt-6">
          {status === "checking" ? (
            <div className="space-y-4" aria-label="Checking login status">
              <div className="flex items-center gap-2">
                <div className="h-10 w-10 shrink-0 animate-pulse rounded-full border-2 border-border bg-muted" />
                <div className="min-w-0 flex-1 space-y-2">
                  <div className="h-4 w-28 animate-pulse rounded bg-muted" />
                  <div className="h-3 w-36 animate-pulse rounded bg-muted" />
                </div>
              </div>
              <div className="h-10 w-full animate-pulse rounded-xl bg-muted" />
            </div>
          ) : status === "authenticated" && user ? (
            <div className="space-y-6">
              <Link href="/profile" className="flex items-center gap-2">
                <Avatar user={user} size="lg" />
                <span className="min-w-0">
                  <span className="block max-w-[145px] truncate font-display text-[15px] font-bold leading-tight text-foreground">{user.name || "Learner"}</span>
                  <span className="block max-w-[145px] truncate text-[12px] font-bold leading-tight text-[#404a52]">{user.email}</span>
                </span>
              </Link>
              <button
                type="button"
                onClick={logout}
                className="flex items-center justify-center gap-2 rounded-xl px-4 py-2 font-display text-[17px] font-bold uppercase tracking-[0.08em] text-[#c01f1f] transition hover:bg-red-50"
              >
                <IconLogout2 className="h-6 w-6" stroke={2} />
                Logout
              </button>
            </div>
          ) : (
            <Button asChildCompat="a" className="w-full">
              <a href="/login">
                <IconDoorEnter className="h-5 w-5" stroke={2} />
                Login
              </a>
            </Button>
          )}
        </div>
      </aside>

      <header className="sticky top-0 z-30 border-b-2 border-border bg-card px-4 py-4 lg:fixed lg:left-64 lg:right-0 lg:flex lg:h-20 lg:items-center lg:justify-between lg:px-12 lg:py-0">
        <div className="flex items-center justify-between gap-4 lg:contents">
          <div className="flex items-center gap-3 lg:contents">
            <button
              className="lg:hidden p-1 text-[#404a52] hover:text-primary transition"
              onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
              aria-label="Toggle mobile menu"
            >
              <IconMenu2 className="h-6 w-6" stroke={2} />
            </button>
            <h1 className="font-display text-2xl font-bold text-foreground">{pageTitle}</h1>
          </div>
          <div className="flex items-center gap-6">
            <button className="flex items-center gap-2 transition hover:opacity-80" aria-label="Daily streak">
              <AppFlameIcon className="text-[34px] text-[#f4bf00]" />
              <span className="font-display text-[24px] font-bold leading-none text-[#f4bf00]">{streak}</span>
            </button>
            <button className="flex items-center gap-2 transition hover:opacity-80" aria-label="XP">
              <span className="flex items-center justify-center rounded-lg bg-[#1cb0f6] p-[3px]">
                <AppDiamondIcon className="text-[28px] text-white" />
              </span>
              <span className="font-display text-[24px] font-bold leading-none text-[#1cb0f6]">{xp}</span>
            </button>
            <NotificationWidget />
          </div>
        </div>
        {isMobileMenuOpen && (
          <nav className="mt-4 flex flex-col gap-2 rounded-xl border-2 border-border bg-card p-4 shadow-sm lg:hidden">
            {nav.map((item) => {
              const active = item.href === "/" ? pathname === "/" : pathname.startsWith(item.href);
              const Icon = item.icon;
              return (
                <Link
                  key={item.href}
                  href={item.href}
                  onClick={() => setIsMobileMenuOpen(false)}
                  className={cn(
                    "flex items-center gap-4 rounded-xl px-4 py-3 font-display text-[15px] font-bold uppercase tracking-[0.05em] transition",
                    active
                      ? "border-2 border-primary bg-accent text-primary"
                      : "text-[#404a52] hover:bg-muted hover:text-primary"
                  )}
                >
                  <Icon className="h-6 w-6 shrink-0" stroke={2} fill="none" />
                  {item.label}
                </Link>
              );
            })}
          </nav>
        )}
      </header>

      <main className="px-4 py-6 lg:ml-64 lg:pt-28 xl:px-12">{children}</main>
    </div>
  );
}

export function AppShellLoading({ label = "Loading..." }: { label?: string }) {
  return (
    <div className="app-loading-overlay">
      <div className="app-loading-panel">
        <CatLoader
          size={280}
          label={label}
          className="gap-4 py-0"
          labelClassName="rounded-full bg-white/72 px-4 py-1.5 font-display text-[0.82rem] font-bold uppercase tracking-[0.04em] text-primary shadow-sm"
        />
      </div>
    </div>
  );
}

function Avatar({ user, size }: { user: AppUser; size: "sm" | "lg" }) {
  const box = size === "lg" ? "h-10 w-10 border-2" : "h-9 w-9 border-2";

  if (user.avatarUrl) {
    return (
      <img
        src={user.avatarUrl}
        alt={user.name || user.email}
        referrerPolicy="no-referrer"
        className={cn(box, "shrink-0 rounded-full border-primary object-cover")}
      />
    );
  }

  return (
    <span className={cn(box, "flex shrink-0 items-center justify-center rounded-full border-primary bg-accent text-primary")}>
      <IconUserFilled className={size === "lg" ? "h-5 w-5" : "h-4 w-4"} />
    </span>
  );
}
