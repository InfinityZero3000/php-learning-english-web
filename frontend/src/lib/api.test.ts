import { beforeEach, describe, expect, it, vi } from "vitest";
import { ApiError } from "./api";

// Prevent mock cross-contamination: when other test files mock "@​/lib/api",
// vitest 4.x resolves "./api" to the same module, so we must declare our own
// mock that returns the real module.
vi.mock("./api", async (importOriginal) => {
  const mod = await importOriginal<typeof import("./api")>();
  return mod;
});

type AuthType = typeof import("./api").auth;

describe("session API client", () => {
  let auth: AuthType;

  beforeEach(async () => {
    document.cookie = "XSRF-TOKEN=token%20value";
    vi.restoreAllMocks();
    // Dynamic import gets the real (un-mocked) module thanks to our vi.mock above
    const mod = await import("./api");
    auth = mod.auth;
  });

  it("initializes CSRF and sends the decoded token with credentials", async () => {
    const fetchMock = vi.spyOn(globalThis, "fetch")
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ data: { id: 1 } }), {
        status: 200,
        headers: { "Content-Type": "application/json" }
      }));

    await auth.login("learner@example.com", "password");

    expect(fetchMock).toHaveBeenNthCalledWith(1, "/api/v1/csrf-cookie", expect.objectContaining({ credentials: "include" }));
    expect(fetchMock).toHaveBeenNthCalledWith(2, "/api/v1/auth/login", expect.objectContaining({
      credentials: "include",
      headers: expect.objectContaining({ "X-XSRF-TOKEN": "token value" })
    }));
  });

  it("parses Laravel validation errors", async () => {
    vi.spyOn(globalThis, "fetch")
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ message: "Invalid", errors: { email: ["Required"] } }), {
        status: 422,
        headers: { "Content-Type": "application/json" }
      }));

    await expect(auth.login("", "")).rejects.toMatchObject({
      status: 422,
      message: "Invalid",
      errors: { email: ["Required"] }
    } as Partial<ApiError>);
  });

  it("initializes CSRF before resending verification", async () => {
    const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValueOnce(new Response(null, { status: 204 })).mockResolvedValueOnce(new Response(JSON.stringify({ data: { message: "sent" } }), { status: 200, headers: { "Content-Type": "application/json" } }));
    await auth.resendVerification();
    expect(fetchMock).toHaveBeenNthCalledWith(2, "/api/v1/auth/email/resend", expect.objectContaining({ method: "POST" }));
  });
});