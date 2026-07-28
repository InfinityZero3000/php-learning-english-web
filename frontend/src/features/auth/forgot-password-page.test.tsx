import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ForgotPasswordPage } from "./forgot-password-page";
import { ApiError, auth } from "@/lib/api";

vi.mock("./auth-layout", () => ({
  AuthLayout: ({ children }: { children: React.ReactNode }) => <>{children}</>
}));

vi.mock("@/lib/api", async (importOriginal) => {
  const mod = await importOriginal<typeof import("@/lib/api")>();
  return {
    ...mod,
    auth: {
      ...mod.auth,
      forgotPassword: vi.fn()
    }
  };
});

describe("ForgotPasswordPage", () => {
  beforeEach(() => vi.clearAllMocks());

  it("submits email and shows privacy-safe success", async () => {
    // vitest 4.x importOriginal type inference returns ApiEnvelope<T> instead of T — cast to work around
    vi.mocked(auth.forgotPassword).mockResolvedValue({ message: "backend detail" } as never);
    render(<ForgotPasswordPage />);
    fireEvent.change(screen.getByLabelText("Email"), { target: { value: "a@b.test" } });
    fireEvent.click(screen.getByRole("button", { name: "Send reset link" }));
    await waitFor(() => expect(auth.forgotPassword).toHaveBeenCalledWith("a@b.test"));
    expect(screen.getByRole("status")).toHaveTextContent("If an account exists");
  });

  it("shows ApiError field error and preserves email", async () => {
    // mockImplementation avoids vitest deep-cloning the ApiError instance
    vi.mocked(auth.forgotPassword).mockImplementation(() =>
      Promise.reject(new ApiError(422, "Invalid", { email: ["Invalid email"] }))
    );
    render(<ForgotPasswordPage />);
    fireEvent.change(screen.getByLabelText("Email"), { target: { value: "a@b.test" } });
    fireEvent.click(screen.getByRole("button", { name: "Send reset link" }));
    expect(await screen.findByRole("alert")).toHaveTextContent("Invalid email");
    expect(screen.getByLabelText("Email")).toHaveValue("a@b.test");
  });

  it("shows generic error fallback on non-ApiError and preserves email", async () => {
    vi.mocked(auth.forgotPassword).mockImplementation(() =>
      Promise.reject(new Error("offline"))
    );
    render(<ForgotPasswordPage />);
    fireEvent.change(screen.getByLabelText("Email"), { target: { value: "a@b.test" } });
    fireEvent.click(screen.getByRole("button", { name: "Send reset link" }));
    expect(await screen.findByRole("alert")).toHaveTextContent("Could not reach");
    expect(screen.getByLabelText("Email")).toHaveValue("a@b.test");
  });
});