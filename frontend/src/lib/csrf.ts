export function xsrfToken() {
  if (typeof document === "undefined") return null;
  const token = document.cookie
    .split("; ")
    .find((cookie) => cookie.startsWith("XSRF-TOKEN="))
    ?.slice("XSRF-TOKEN=".length);

  return token ? decodeURIComponent(token) : null;
}

export async function initializeCsrf() {
  const response = await fetch("/api/v1/csrf-cookie", {
    credentials: "include",
    headers: { Accept: "application/json" }
  });

  if (!response.ok) throw new Error("Could not initialize a secure session.");
}
