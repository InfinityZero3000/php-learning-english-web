import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { LoginPage } from "./login-page";
import { auth } from "@/lib/api";

const replace = vi.fn();
const refresh = vi.fn();

vi.mock("next/navigation", () => ({ useRouter: () => ({ replace, refresh }) }));
vi.mock("@/lib/api", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@/lib/api")>();
  return { ...actual, auth: { ...actual.auth, login: vi.fn() } };
});

describe("learner login", () => {
  beforeEach(() => vi.clearAllMocks());

  it("submits credentials and navigates on success", async () => {
    vi.mocked(auth.login).mockResolvedValue({ id: 1 } as never);
    render(<LoginPage />);

    fireEvent.change(screen.getByLabelText("Email"), { target: { value: "learner@example.com" } });
    fireEvent.change(screen.getByLabelText("Password"), { target: { value: "password" } });
    fireEvent.click(screen.getByRole("button", { name: "Sign in" }));

    await waitFor(() => expect(auth.login).toHaveBeenCalledWith("learner@example.com", "password"));
    expect(replace).toHaveBeenCalledWith("/");
  });

  it("shows a transport error", async () => {
    vi.mocked(auth.login).mockRejectedValue(new Error("offline"));
    render(<LoginPage />);
    fireEvent.change(screen.getByLabelText("Email"), { target: { value: "learner@example.com" } });
    fireEvent.change(screen.getByLabelText("Password"), { target: { value: "password" } });
    fireEvent.click(screen.getByRole("button", { name: "Sign in" }));
    expect(await screen.findByRole("alert")).toHaveTextContent("Could not reach the server");
  });
});
