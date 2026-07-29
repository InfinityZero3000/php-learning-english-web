import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { VerifyEmailPage } from "./verify-email-page";
import { ApiError, auth } from "@/lib/api";

vi.mock("next/navigation", () => ({ useSearchParams: () => new URLSearchParams("email=a%40b.test") }));
vi.mock("./auth-layout", () => ({ AuthLayout: ({ children }: { children: React.ReactNode }) => <>{children}</> }));
vi.mock("@/lib/api", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@/lib/api")>();
  return { ...actual, auth: { ...actual.auth, resendVerification: vi.fn() } };
});

describe("VerifyEmailPage", () => {
  beforeEach(() => vi.clearAllMocks());

  it("resends verification without a payload", async () => {
    vi.mocked(auth.resendVerification).mockResolvedValue({ message: "ok" });
    render(<VerifyEmailPage />);
    fireEvent.click(screen.getByRole("button", { name: "Gửi lại email xác minh" }));
    await waitFor(() => expect(auth.resendVerification).toHaveBeenCalledWith());
    screen.getByRole("status");
  });

  it.each([new ApiError(422, "Session expired"), new Error("offline")])(
    "shows an error and leaves resend available for retry",
    async (error) => {
      vi.mocked(auth.resendVerification).mockRejectedValue(error);
      render(<VerifyEmailPage />);
      fireEvent.click(screen.getByRole("button", { name: "Gửi lại email xác minh" }));
      expect(await screen.findByRole("alert")).toHaveTextContent(error instanceof ApiError ? "Session expired" : "Không thể gửi lại");
      fireEvent.click(screen.getByRole("button", { name: "Gửi lại email xác minh" }));
      await waitFor(() => expect(auth.resendVerification).toHaveBeenCalledTimes(2));
    },
  );
});
