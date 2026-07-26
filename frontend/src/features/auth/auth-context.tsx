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

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [status, setStatus] = useState<AuthStatus>("checking");
  const [user, setUser] = useState<AppUser | null>(null);

  async function refreshUser() {
    try {
      const current = await auth.me();
      setUser(current);
      setStatus("authenticated");
      return current;
    } catch (error) {
      setUser(null);
      setStatus(error instanceof ApiError && error.status === 401 ? "guest" : "unavailable");
      return null;
    }
  }

  async function logout() {
    await auth.logout().catch(() => undefined);
    setUser(null);
    setStatus("guest");
  }

  useEffect(() => {
    void refreshUser();
  }, []);

  const value = useMemo(() => ({ status, user, refreshUser, logout }), [status, user]);
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const value = useContext(AuthContext);
  if (!value) throw new Error("useAuth must be used within AuthProvider");
  return value;
}
