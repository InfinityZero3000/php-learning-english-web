const laravelOrigin = process.env.LARAVEL_API_ORIGIN ?? "http://localhost:8080";

export function GET() {
  return Response.redirect(`${laravelOrigin}/api/v1/auth/oauth/google?next=%2F`);
}
