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
import { navigationIcons } from "@/components/icons/app-icons";
import { Button } from "@/components/ui/button";
import { CatLoader } from "@/components/ui/cat-loader";
import { api, ApiError, auth } from "@/lib/api";
import { cn } from "@/lib/utils";
import type { AppUser } from "@/types/api";

const nav = [
  { href: "/", label: "Today", icon: navigationIcons.home },
  { href: "/courses", label: "Courses", icon: navigationIcons.flashcards },
  { href: "/assignments", label: "Assignments", icon: navigationIcons.quiz },
  { href: "/review", label: "Flashcards", icon: navigationIcons.flashcards },
  { href: "/vocabulary", label: "Words", icon: navigationIcons.words },
  { href: "/progress", label: "Progress", icon: navigationIcons.progress },
];

const titles: Record<string, string> = {
  "/": "Today",
  "/courses": "Course Path",
  "/assignments": "Assignments",
  "/review": "FSRS Flashcards",
  "/vocabulary": "Words",
  "/progress": "Progress",
  "/profile": "Profile"
};

export function AppShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const router = useRouter();
  const [user, setUser] = useState<AppUser | null>(null);
  const [authChecked, setAuthChecked] = useState(false);
  const [authError, setAuthError] = useState(false);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

  const pageTitle = useMemo(() => titles[pathname] || "FSRSpring", [pathname]);
  const visibleNav = useMemo(() => [
    ...nav,
    ...(user?.role === "teacher" || user?.role === "super_admin"
      ? [{ href: "/teacher", label: "Teacher", icon: navigationIcons.progress }]
      : [])
  ], [user]);

  useEffect(() => {
    let cancelled = false;

    api.me()
      .then((data) => {
        if (!cancelled) setUser(data);
      })
      .catch((error) => {
        if (cancelled) return;
        if (error instanceof ApiError && error.status === 401) {
          if (pathname !== "/login") window.location.replace("/login");
        } else {
          setAuthError(true);
        }
      })
      .finally(() => {
        if (!cancelled) setAuthChecked(true);
      });
    return () => {
      cancelled = true;
    };
  }, [pathname, router]);

  async function logout() {
    await auth.logout().catch(() => undefined);
    router.replace("/login");
    router.refresh();
  }

  if (pathname === "/login") return children;
  if (!authChecked) return <AppShellLoading label="Checking session..." />;
  if (authError) {
    return (
      <main className="flex min-h-screen items-center justify-center p-6 text-center">
        <div>
          <p className="font-bold">Could not reach the server.</p>
          <Button className="mt-4" onClick={() => window.location.reload()}>Retry</Button>
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
          {visibleNav.map((item) => {
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
          {!authChecked ? (
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
          ) : user ? (
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
        </div>
        {isMobileMenuOpen && (
          <nav className="mt-4 flex flex-col gap-2 rounded-xl border-2 border-border bg-card p-4 shadow-sm lg:hidden">
            {visibleNav.map((item) => {
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
