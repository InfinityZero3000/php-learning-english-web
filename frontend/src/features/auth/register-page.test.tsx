import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { RegisterPage } from "./register-page";
import { ApiError, auth } from "@/lib/api";

const push = vi.fn();
vi.mock("next/navigation", () => ({ useRouter: () => ({ push }) }));

vi.mock("./auth-layout", () => ({
  AuthLayout: ({ children }: { children: React.ReactNode }) => <>{children}</>
}));

vi.mock("@/lib/api", async (importOriginal) => {
  const mod = await importOriginal<typeof import("@/lib/api")>();
  return {
    ...mod,
    auth: {
      ...mod.auth,
      register: vi.fn()
    }
  };
});

function fill() {
  fireEvent.change(screen.getByLabelText("Name"), { target: { value: "Learner" } });
  fireEvent.change(screen.getByLabelText("Email"), { target: { value: "a@b.test" } });
  fireEvent.change(screen.getByLabelText("Password"), { target: { value: "password" } });
  fireEvent.change(screen.getByLabelText("Confirm password"), { target: { value: "password" } });
}

describe("RegisterPage", () => {
  beforeEach(() => vi.clearAllMocks());

  it("registers and carries email to verification", async () => {
    vi.mocked(auth.register).mockResolvedValue({ message: "ok" } as never);
    render(<RegisterPage />);
    fill();
    fireEvent.click(screen.getByRole("button", { name: "Create account" }));
    await waitFor(() =>
      expect(auth.register).toHaveBeenCalledWith("Learner", "a@b.test", "password", "password")
    );
    expect(push).toHaveBeenCalledWith("/verify-email?email=a%40b.test");
  });

  it("rejects mismatched confirmation without a request", () => {
    render(<RegisterPage />);
    fill();
    fireEvent.change(screen.getByLabelText("Confirm password"), { target: { value: "different" } });
    fireEvent.click(screen.getByRole("button", { name: "Create account" }));
    expect(screen.getByRole("alert")).toHaveTextContent("do not match");
    expect(auth.register).not.toHaveBeenCalled();
  });

  it("shows ApiError field error and preserves input", async () => {
    vi.mocked(auth.register).mockRejectedValue(
      new ApiError(422, "Invalid", { email: ["Already registered"] })
    );
    render(<RegisterPage />);
    fill();
    fireEvent.click(screen.getByRole("button", { name: "Create account" }));
    expect(await screen.findByRole("alert")).toHaveTextContent("Already registered");
    expect(screen.getByLabelText("Email")).toHaveValue("a@b.test");
    expect(screen.getByLabelText("Password")).toHaveValue("password");
  });

  it("shows generic error fallback on non-ApiError and preserves input", async () => {
    vi.mocked(auth.register).mockRejectedValue(new Error("offline"));
    render(<RegisterPage />);
    fill();
    fireEvent.click(screen.getByRole("button", { name: "Create account" }));
    expect(await screen.findByRole("alert")).toHaveTextContent("Could not reach");
    expect(screen.getByLabelText("Email")).toHaveValue("a@b.test");
    expect(screen.getByLabelText("Password")).toHaveValue("password");
  });
});