import { beforeEach, describe, expect, it, vi } from "vitest";
import { ApiError, auth } from "./api";

describe("session API client", () => {
  beforeEach(() => {
    document.cookie = "XSRF-TOKEN=token%20value";
    vi.restoreAllMocks();
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