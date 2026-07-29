import { describe, expect, it } from "vitest";
import { isAuthPath, isProtectedPath, loginHref, safeNext } from "./route-policy";

describe("learner route policy", () => {
  it("matches protected route segments only", () => {
    expect(isProtectedPath("/profile")).toBe(true);
    expect(isProtectedPath("/profile/security")).toBe(true);
    expect(isProtectedPath("/profiled")).toBe(false);
    expect(isProtectedPath("/vocabulary")).toBe(false);
  });

  it("preserves and encodes the return URL", () => {
    expect(loginHref("/profile", "tab=password")).toBe("/login?next=%2Fprofile%3Ftab%3Dpassword");
  });

  it.each(["//evil.example", "https://evil.example", "/\\evil.example", "/%5Cevil.example"])(
    "rejects unsafe next value %s",
    (value) => expect(safeNext(decodeURIComponent(value), "https://app.example")).toBe("/")
  );

  it("accepts a same-origin relative path", () => {
    expect(safeNext("/vocabulary?search=hello", "https://app.example")).toBe("/vocabulary?search=hello");
  });

  it.each(["/login", "/register", "/verify-email", "/forgot-password", "/reset-password", "/auth/callback"])("recognizes auth path %s", path => {
    expect(isAuthPath(path)).toBe(true);
    expect(safeNext(`${path}?next=/profile`, "https://app.example")).toBe("/");
  });
});
