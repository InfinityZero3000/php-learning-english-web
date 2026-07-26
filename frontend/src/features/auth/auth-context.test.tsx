import { render, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { AuthProvider } from "@/features/auth/auth-context";
import { auth } from "@/lib/api";

vi.mock("@/lib/api", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@/lib/api")>();
  return { ...actual, auth: { ...actual.auth, me: vi.fn(), logout: vi.fn() } };
});

describe("AuthProvider", () => {
  beforeEach(() => {
    document.cookie = "fsrspring-authenticated=; Path=/; Max-Age=0";
    vi.clearAllMocks();
  });

  it("does not query the current user for a guest on a public page", async () => {
    render(<AuthProvider><div>Public page</div></AuthProvider>);

    await waitFor(() => expect(auth.me).not.toHaveBeenCalled());
  });

  it("checks the session on a protected page", async () => {
    vi.mocked(auth.me).mockResolvedValue({ id: 1 } as never);
    render(<AuthProvider checkSession><div>Profile</div></AuthProvider>);

    await waitFor(() => expect(auth.me).toHaveBeenCalledOnce());
    expect(document.cookie).toContain("fsrspring-authenticated=1");
  });
});
