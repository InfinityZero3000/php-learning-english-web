import { render, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { OAuthCallbackPage } from "./oauth-callback-page";
import { auth } from "@/lib/api";

const replace = vi.fn();
let query = "next=%2Fprofile";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace }),
  useSearchParams: () => new URLSearchParams(query)
}));
vi.mock("./auth-layout", () => ({ AuthLayout: ({ children }: { children: React.ReactNode }) => <>{children}</> }));
vi.mock("@/lib/api", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@/lib/api")>();
  return { ...actual, auth: { ...actual.auth, me: vi.fn() } };
});

describe("OAuthCallbackPage", () => {
  beforeEach(() => vi.clearAllMocks());

  it("confirms the session once and navigates to a safe destination", async () => {
    query = "next=%2Fprofile";
    vi.mocked(auth.me).mockResolvedValue({ id: 1 } as never);
    render(<OAuthCallbackPage />);
    await waitFor(() => expect(replace).toHaveBeenCalledWith("/profile"));
    expect(auth.me).toHaveBeenCalledTimes(1);
  });

  it("rejects raw external destinations", async () => {
    query = "next=https://evil.test";
    vi.mocked(auth.me).mockResolvedValue({ id: 1 } as never);
    render(<OAuthCallbackPage />);
    await waitFor(() => expect(replace).toHaveBeenCalledWith("/"));
  });

  it("maps a failed session check to a stable login error", async () => {
    query = "next=%2Fprofile";
    vi.mocked(auth.me).mockRejectedValue(new Error("unauthenticated"));
    render(<OAuthCallbackPage />);
    await waitFor(() => expect(replace).toHaveBeenCalledWith("/login?oauth_error=session_failed"));
  });
});
