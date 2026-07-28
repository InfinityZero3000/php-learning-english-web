import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ResetPasswordPage } from "./reset-password-page";
import { ApiError, auth } from "@/lib/api";

const replace = vi.fn();
let query = "";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace }),
  useSearchParams: () => new URLSearchParams(query)
}));

vi.mock("./auth-layout", () => ({
  AuthLayout: ({ children }: { children: React.ReactNode }) => <>{children}</>
}));

vi.mock("@/lib/api", async (importOriginal) => {
  const mod = await importOriginal<typeof import("@/lib/api")>();
  return {
    ...mod,
    auth: {
      ...mod.auth,
      resetPassword: vi.fn()
    }
  };
});

function fill() {
  fireEvent.change(screen.getByLabelText("New password"), { target: { value: "new-password" } });
  fireEvent.change(screen.getByLabelText("Confirm new password"), { target: { value: "new-password" } });
}

describe("ResetPasswordPage", () => {
  beforeEach(() => {
    query = "token=abc&email=a%40b.test";
    vi.clearAllMocks();
  });

  it("does not submit an incomplete link", () => {
    query = "";
    render(<ResetPasswordPage />);
    screen.getByRole("alert");
    screen.getByRole("link", { name: "Request a new link" });
  });

  it("submits token and navigates to login", async () => {
    vi.mocked(auth.resetPassword).mockResolvedValue({ message: "ok" } as never);
    render(<ResetPasswordPage />);
    fill();
    fireEvent.click(screen.getByRole("button", { name: "Reset password" }));
    await waitFor(() =>
      expect(auth.resetPassword).toHaveBeenCalledWith({
        token: "abc",
        email: "a@b.test",
        password: "new-password",
        password_confirmation: "new-password"
      })
    );
    expect(replace).toHaveBeenCalledWith("/login?reset=1");
  });

  it("shows ApiError field error and preserves passwords", async () => {
    vi.mocked(auth.resetPassword).mockRejectedValue(
      new ApiError(422, "Invalid", { password: ["Password is too short"] })
    );
    render(<ResetPasswordPage />);
    fill();
    fireEvent.click(screen.getByRole("button", { name: "Reset password" }));
    expect(await screen.findByRole("alert")).toHaveTextContent("Password is too short");
    expect(screen.getByLabelText("New password")).toHaveValue("new-password");
  });

  it("shows generic error fallback on non-ApiError and preserves passwords", async () => {
    vi.mocked(auth.resetPassword).mockRejectedValue(new Error("offline"));
    render(<ResetPasswordPage />);
    fill();
    fireEvent.click(screen.getByRole("button", { name: "Reset password" }));
    expect(await screen.findByRole("alert")).toHaveTextContent("Could not reach");
    expect(screen.getByLabelText("New password")).toHaveValue("new-password");
  });
});