import { describe, expect, it } from "vitest";
import { NextRequest } from "next/server";
import { GET } from "./route";

describe("GET /auth/google", () => {
  it("redirects same-origin so the OAuth state session cookie survives the round trip", async () => {
    const request = new NextRequest("https://learner.example/auth/google");

    const response = GET(request);

    const location = response.headers.get("location") ?? "";
    expect(location).toBe("https://learner.example/api/v1/auth/oauth/google?next=%2F");
  });
});
