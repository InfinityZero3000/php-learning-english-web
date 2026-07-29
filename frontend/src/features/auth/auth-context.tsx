"use client";

import { createContext, useContext, useEffect, useMemo, useState } from "react";
import { ApiError, auth } from "@/lib/api";
import type { AppUser } from "@/types/api";

export type AuthStatus = "checking" | "guest" | "authenticated" | "unavailable";

type AuthContextValue = {
  status: AuthStatus;
  user: AppUser | null;
  refreshUser: () => Promise<AppUser | null>;
  logout: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);
const SESSION_HINT_KEY = "fsrspring-authenticated";

function hasSessionHint() {
  return document.cookie.split("; ").includes(`${SESSION_HINT_KEY}=1`);
}

function setSessionHint(authenticated: boolean) {
  document.cookie = authenticated
    ? `${SESSION_HINT_KEY}=1; Path=/; SameSite=Lax`
    : `${SESSION_HINT_KEY}=; Path=/; Max-Age=0; SameSite=Lax`;
}

export function AuthProvider({ children, checkSession = false }: { children: React.ReactNode; checkSession?: boolean }) {
  const [status, setStatus] = useState<AuthStatus>("checking");
  const [user, setUser] = useState<AppUser | null>(null);

  async function refreshUser() {
    try {
      const current = await auth.me();
      setSessionHint(true);
      setUser(current);
      setStatus("authenticated");
      return current;
    } catch (error) {
      setUser(null);
      if (error instanceof ApiError && error.status === 401) setSessionHint(false);
      setStatus(error instanceof ApiError && error.status === 401 ? "guest" : "unavailable");
      return null;
    }
  }

  async function logout() {
    await auth.logout().catch(() => undefined);
    setUser(null);
    setSessionHint(false);
    setStatus("guest");
  }

  useEffect(() => {
    if (checkSession || hasSessionHint()) {
      void refreshUser();
    } else {
      setStatus("guest");
    }
  }, [checkSession]);

  const value = useMemo(() => ({ status, user, refreshUser, logout }), [status, user]);
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const value = useContext(AuthContext);
  if (!value) throw new Error("useAuth must be used within AuthProvider");
  return value;
}
